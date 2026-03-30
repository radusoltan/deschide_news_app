#!/usr/bin/env node

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

// --- Config from env ---
const CONFIG = {
  clientId: process.env.ZOHO_CLIENT_ID,
  clientSecret: process.env.ZOHO_CLIENT_SECRET,
  refreshToken: process.env.ZOHO_REFRESH_TOKEN,
  accountId: process.env.ZOHO_ACCOUNT_ID || "4108690000000008001",
  inboxFolderId: process.env.ZOHO_INBOX_FOLDER_ID || "4108690000000008013",
  accountsUrl: process.env.ZOHO_ACCOUNTS_URL || "https://accounts.zoho.com",
  mailBaseUrl: "https://mail.zoho.com/api",
};

let accessToken = process.env.ZOHO_ACCESS_TOKEN || null;

// --- Token refresh ---
async function refreshAccessToken() {
  const res = await fetch(`${CONFIG.accountsUrl}/oauth/v2/token`, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      refresh_token: CONFIG.refreshToken,
      client_id: CONFIG.clientId,
      client_secret: CONFIG.clientSecret,
      grant_type: "refresh_token",
    }),
  });
  const data = await res.json();
  if (data.access_token) {
    accessToken = data.access_token;
    return accessToken;
  }
  throw new Error(`Token refresh failed: ${JSON.stringify(data)}`);
}

// --- API helper with auto-refresh ---
async function zohoFetch(path, options = {}) {
  if (!accessToken) await refreshAccessToken();

  const url = `${CONFIG.mailBaseUrl}/accounts/${CONFIG.accountId}${path}`;
  const headers = {
    Authorization: `Zoho-oauthtoken ${accessToken}`,
    ...options.headers,
  };

  let res = await fetch(url, { ...options, headers });
  let body = await res.text();

  // Auto-refresh on 401
  if (res.status === 401) {
    await refreshAccessToken();
    headers.Authorization = `Zoho-oauthtoken ${accessToken}`;
    res = await fetch(url, { ...options, headers });
    body = await res.text();
  }

  try {
    return JSON.parse(body);
  } catch {
    return { raw: body, status: res.status };
  }
}

// --- MCP Server ---
const server = new McpServer({
  name: "zoho-mail",
  version: "1.0.0",
});

// Tool 1: list_emails
server.tool(
  "list_emails",
  "List emails from a Zoho Mail folder. Returns subject, sender, date, messageId.",
  {
    folderId: z
      .string()
      .optional()
      .describe("Folder ID (default: Inbox)"),
    limit: z
      .number()
      .optional()
      .describe("Max emails to return (default: 20, max: 100)"),
    start: z
      .number()
      .optional()
      .describe("Start index for pagination (default: 0)"),
  },
  async ({ folderId, limit, start }) => {
    const folder = folderId || CONFIG.inboxFolderId;
    const params = new URLSearchParams({
      limit: String(limit || 20),
      start: String(start || 0),
    });
    const result = await zohoFetch(
      `/messages/view?folderId=${folder}&${params}`
    );

    const emails = (result.data || []).map((m) => ({
      messageId: m.messageId,
      subject: m.subject,
      sender: m.sender,
      from: m.fromAddress,
      receivedTime: m.receivedTime,
      hasAttachment: m.hasAttachment === "1",
      status: m.status,
      summary: m.summary?.substring(0, 200),
    }));

    return {
      content: [
        {
          type: "text",
          text: JSON.stringify({ count: emails.length, emails }, null, 2),
        },
      ],
    };
  }
);

// Tool 2: get_email_content
server.tool(
  "get_email_content",
  "Get the full content of a specific email. Requires folderId + messageId. Returns subject, body (HTML).",
  {
    messageId: z.string().describe("The message ID to fetch"),
    folderId: z
      .string()
      .optional()
      .describe("Folder ID (default: Inbox). REQUIRED for content endpoint."),
  },
  async ({ messageId, folderId }) => {
    const folder = folderId || CONFIG.inboxFolderId;

    // Use the /content endpoint which returns the full HTML body
    const result = await zohoFetch(
      `/folders/${folder}/messages/${messageId}/content`
    );
    const msg = result.data || {};

    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(
            {
              messageId: msg.messageId,
              subject: msg.subject,
              from: msg.fromAddress,
              to: msg.toAddress,
              cc: msg.ccAddress,
              date: msg.date || msg.receivedTime,
              content: msg.content,
            },
            null,
            2
          ),
        },
      ],
    };
  }
);

// Tool 3: search_emails
server.tool(
  "search_emails",
  "Search emails in Zoho Mail. Supports Zoho search syntax: from:addr, subject:text, content:text, etc.",
  {
    searchKey: z
      .string()
      .describe(
        'Search query (e.g. "from:newsfeed@ipn.md", "subject:comunicat")'
      ),
    folderId: z
      .string()
      .optional()
      .describe("Folder ID to search in (default: Inbox)"),
    limit: z
      .number()
      .optional()
      .describe("Max results (default: 20)"),
  },
  async ({ searchKey, folderId, limit }) => {
    const folder = folderId || CONFIG.inboxFolderId;
    const params = new URLSearchParams({
      limit: String(limit || 20),
    });
    // Zoho search endpoint
    const result = await zohoFetch(
      `/messages/search?folderId=${folder}&searchKey=${encodeURIComponent(searchKey)}&${params}`
    );

    const emails = (result.data || []).map((m) => ({
      messageId: m.messageId,
      subject: m.subject,
      sender: m.sender,
      from: m.fromAddress,
      receivedTime: m.receivedTime,
      summary: m.summary?.substring(0, 200),
    }));

    return {
      content: [
        {
          type: "text",
          text: JSON.stringify({ count: emails.length, emails }, null, 2),
        },
      ],
    };
  }
);

// Tool 4: mark_as_read
server.tool(
  "mark_as_read",
  "Mark one or more emails as read.",
  {
    messageId: z
      .string()
      .describe("Message ID (or comma-separated IDs) to mark as read"),
  },
  async ({ messageId }) => {
    const result = await zohoFetch(`/messages`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        mode: "markAsRead",
        messageId: messageId.split(",").map((id) => id.trim()),
      }),
    });

    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(
            { success: result.status?.code === 200, detail: result },
            null,
            2
          ),
        },
      ],
    };
  }
);

// Tool 5: move_to_folder
server.tool(
  "move_to_folder",
  "Move an email to a different folder.",
  {
    messageId: z.string().describe("Message ID to move"),
    destFolderId: z.string().describe("Destination folder ID"),
  },
  async ({ messageId, destFolderId }) => {
    const result = await zohoFetch(`/messages`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        mode: "move",
        messageId: [messageId],
        destfolderId: destFolderId,
      }),
    });

    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(
            { success: result.status?.code === 200, detail: result },
            null,
            2
          ),
        },
      ],
    };
  }
);

// Tool 6: list_folders
server.tool(
  "list_folders",
  "List all mail folders with their IDs.",
  {},
  async () => {
    const result = await zohoFetch(`/folders`);
    const folders = (result.data || []).map((f) => ({
      folderId: f.folderId,
      folderName: f.folderName,
      folderType: f.folderType,
      path: f.path,
    }));

    return {
      content: [
        {
          type: "text",
          text: JSON.stringify({ folders }, null, 2),
        },
      ],
    };
  }
);

// --- Start ---
const transport = new StdioServerTransport();
await server.connect(transport);
