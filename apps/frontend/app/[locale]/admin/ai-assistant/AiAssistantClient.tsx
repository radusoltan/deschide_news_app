'use client';

import { useState, useEffect, useRef, useCallback } from 'react';
import { featureFlags } from '@/lib/config/feature-flags';

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? '';
const MERCURE_URL = process.env.NEXT_PUBLIC_MERCURE_URL ?? 'http://localhost:3000/.well-known/mercure';

// ============================================================================
// Types
// ============================================================================

interface Conversation {
  id: string;
  title: string | null;
  agentType: string | null;
  status: string;
  messageCount: number;
  createdAt: string;
}

interface Message {
  id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  agentType: string | null;
  model: string | null;
  tokensUsed: number | null;
  createdAt: string;
}

interface Template {
  id: string;
  name: string;
  description: string | null;
  agentType: string;
  promptTemplate: string;
  requiredFields: string[];
}

interface ChatResponse {
  conversationId: string;
  messageId: string;
  content: string;
  agentType: string;
  model: string | null;
  tokensUsed: number | null;
}

function createHeaders(token: string): HeadersInit {
  return {
    'Content-Type': 'application/json',
    Authorization: `Bearer ${token}`,
  };
}

// ============================================================================
// Agent Badge Colors
// ============================================================================

const AGENT_COLORS: Record<string, { bg: string; text: string; label: string }> = {
  vault: { bg: 'bg-teal-100 dark:bg-teal-900/30', text: 'text-teal-700 dark:text-teal-300', label: 'Vault' },
  content: { bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-700 dark:text-orange-300', label: 'Content' },
  translation: { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-700 dark:text-blue-300', label: 'Translation' },
  briefing: { bg: 'bg-amber-100 dark:bg-amber-900/30', text: 'text-amber-700 dark:text-amber-300', label: 'Briefing' },
  seo: { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-700 dark:text-green-300', label: 'SEO' },
};

const PROVIDER_COLORS: Record<string, { bg: string; text: string; label: string }> = {
  anthropic: { bg: 'bg-purple-50 dark:bg-purple-900/20', text: 'text-purple-600 dark:text-purple-400', label: 'Claude' },
  gemini: { bg: 'bg-emerald-50 dark:bg-emerald-900/20', text: 'text-emerald-600 dark:text-emerald-400', label: 'Gemini' },
};

/** Map model string to provider key */
function getProviderFromModel(model: string | null): string | null {
  if (!model) return null;
  if (model.startsWith('claude-') || model.startsWith('anthropic')) return 'anthropic';
  if (model.startsWith('gemini-')) return 'gemini';
  return null;
}

/** Categories that are powered by Gemini */
const GEMINI_CATEGORIES = new Set(['translation', 'seo']);

const CATEGORY_LABELS: Record<string, string> = {
  research: 'Cercetare',
  content: 'Conținut',
  translation: 'Traduceri',
  briefing: 'Briefing',
  seo: 'SEO',
};

// ============================================================================
// Component
// ============================================================================

export default function AiAssistantClient({
  locale,
  token,
}: {
  locale: string;
  token: string;
}) {
  const aiChatEnabled = featureFlags.aiChatEnabled;

  // State
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [currentConvId, setCurrentConvId] = useState<string | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [templates, setTemplates] = useState<Record<string, Template[]>>({});
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [typing, setTyping] = useState<string | null>(null);
  const [showTemplates, setShowTemplates] = useState(false);
  const [showSidebar, setShowSidebar] = useState(false);
  const [templateTab, setTemplateTab] = useState('research');
  const [templateFields, setTemplateFields] = useState<Record<string, string>>({});
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null);

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  // ============================================================================
  // Data Fetching
  // ============================================================================

  const loadConversations = useCallback(async () => {
    if (!aiChatEnabled) return;

    try {
      const res = await fetch(`${API_URL}/api/ai/conversations`, {
        headers: createHeaders(token),
      });
      if (res.ok) {
        const data = await res.json();
        setConversations(data.items ?? []);
      }
    } catch { /* ignore */ }
  }, [aiChatEnabled, token]);

  const loadTemplates = useCallback(async () => {
    if (!aiChatEnabled) return;

    try {
      const res = await fetch(`${API_URL}/api/ai/templates`, {
        headers: createHeaders(token),
      });
      if (res.ok) {
        const data = await res.json();
        setTemplates(data.categories ?? {});
      }
    } catch { /* ignore */ }
  }, [aiChatEnabled, token]);

  const loadMessages = useCallback(async (convId: string) => {
    if (!aiChatEnabled) return;

    try {
      const res = await fetch(`${API_URL}/api/ai/conversations/${convId}/messages`, {
        headers: createHeaders(token),
      });
      if (res.ok) {
        const data = await res.json();
        setMessages(data.items ?? []);
      }
    } catch { /* ignore */ }
  }, [aiChatEnabled, token]);

  useEffect(() => {
    if (!aiChatEnabled) return;

    loadConversations();
    loadTemplates();
  }, [aiChatEnabled, loadConversations, loadTemplates]);

  // ============================================================================
  // Mercure SSE Subscription
  // ============================================================================

  useEffect(() => {
    if (!aiChatEnabled || !currentConvId) return;

    const topic = encodeURIComponent(`/ai/conversations/${currentConvId}`);
    const url = `${MERCURE_URL}?topic=${topic}`;

    let es: EventSource;
    try {
      es = new EventSource(url, { withCredentials: true });

      es.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data);
          if (data.type === 'typing') {
            setTyping(data.agentType);
          } else if (data.type === 'message') {
            setTyping(null);
            setMessages((prev) => {
              const exists = prev.some((m) => m.id === data.data.messageId);
              if (exists) return prev;
              return [...prev, {
                id: data.data.messageId,
                role: data.data.role,
                content: data.data.content,
                agentType: data.data.agentType,
                model: data.data.model,
                tokensUsed: data.data.tokensUsed,
                createdAt: data.data.createdAt,
              }];
            });
          } else if (data.type === 'error') {
            setTyping(null);
          }
        } catch { /* ignore parse errors */ }
      };

      es.onerror = () => {
        // EventSource auto-reconnects
      };
    } catch {
      // SSE not available, fallback to polling via REST
    }

    return () => {
      try { es?.close(); } catch { /* ignore */ }
    };
  }, [aiChatEnabled, currentConvId]);

  // Auto-scroll
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, typing]);

  // ============================================================================
  // Send Message
  // ============================================================================

  const sendMessage = async (overrideMessage?: string, templateId?: string, fields?: Record<string, string>) => {
    const text = overrideMessage ?? input.trim();
    if (!aiChatEnabled || !text || loading) return;

    setLoading(true);
    setInput('');
    setShowTemplates(false);
    setSelectedTemplate(null);
    setTemplateFields({});

    // Optimistic: add user message to UI
    const tempUserMsg: Message = {
      id: `temp-${Date.now()}`,
      role: 'user',
      content: text,
      agentType: null,
      model: null,
      tokensUsed: null,
      createdAt: new Date().toISOString(),
    };
    setMessages((prev) => [...prev, tempUserMsg]);

    try {
      const body: Record<string, unknown> = { message: text };
      if (currentConvId) body.conversationId = currentConvId;
      if (templateId) body.templateId = templateId;
      if (fields && Object.keys(fields).length > 0) body.templateFields = fields;

      const res = await fetch(`${API_URL}/api/ai/chat`, {
        method: 'POST',
        headers: createHeaders(token),
        body: JSON.stringify(body),
      });

      if (!res.ok) {
        const err = await res.json().catch(() => ({ error: 'Request failed' }));
        throw new Error(err.error ?? err.message ?? `HTTP ${res.status}`);
      }

      const data: ChatResponse = await res.json();

      // Set conversation if new
      if (!currentConvId) {
        setCurrentConvId(data.conversationId);
        loadConversations();
      }

      // Add assistant message (if not already added by Mercure)
      setMessages((prev) => {
        const exists = prev.some((m) => m.id === data.messageId);
        if (exists) return prev;
        return [...prev, {
          id: data.messageId,
          role: 'assistant',
          content: data.content,
          agentType: data.agentType,
          model: data.model,
          tokensUsed: data.tokensUsed,
          createdAt: new Date().toISOString(),
        }];
      });
    } catch (error) {
      setMessages((prev) => [...prev, {
        id: `error-${Date.now()}`,
        role: 'assistant',
        content: `Eroare: ${error instanceof Error ? error.message : 'A apărut o eroare'}`,
        agentType: null,
        model: null,
        tokensUsed: null,
        createdAt: new Date().toISOString(),
      }]);
    } finally {
      setLoading(false);
      setTyping(null);
    }
  };

  // ============================================================================
  // Conversation Management
  // ============================================================================

  const selectConversation = (conv: Conversation) => {
    if (!aiChatEnabled) return;

    setCurrentConvId(conv.id);
    loadMessages(conv.id);
    setShowSidebar(false);
  };

  const newConversation = () => {
    setCurrentConvId(null);
    setMessages([]);
    setShowSidebar(false);
  };

  // ============================================================================
  // Template Handling
  // ============================================================================

  const submitTemplate = (template: Template) => {
    if (!aiChatEnabled) return;

    // Check all required fields are filled
    const missing = template.requiredFields.filter((f) => !templateFields[f]?.trim());
    if (missing.length > 0) return;

    // Build message from template
    let prompt = template.promptTemplate;
    for (const [key, value] of Object.entries(templateFields)) {
      prompt = prompt.replaceAll(`{${key}}`, value);
    }

    sendMessage(prompt, template.id, templateFields);
  };

  // ============================================================================
  // Key Handling
  // ============================================================================

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      sendMessage();
    }
  };

  // Auto-resize textarea
  const handleInputChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    setInput(e.target.value);
    const el = e.target;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
  };

  // ============================================================================
  // Render
  // ============================================================================

  if (!aiChatEnabled) {
    return (
      <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
        AI Assistant este suspendat temporar conform ADR-025.
      </div>
    );
  }

  return (
    <div className="flex h-[calc(100vh-6rem)] -mt-2 gap-0 rounded-lg overflow-hidden border border-border dark:border-border-dark bg-surface dark:bg-surface-dark">
      {/* Conversation Sidebar */}
      <div className={`${showSidebar ? 'fixed inset-0 z-50 flex' : 'hidden'} md:relative md:flex w-72 flex-shrink-0 flex-col border-r border-border dark:border-border-dark bg-surface-sunken dark:bg-surface-sunken-dark`}>
        {/* Sidebar Header */}
        <div className="p-3 border-b border-border dark:border-border-dark flex items-center justify-between">
          <h2 className="text-sm font-semibold text-primary dark:text-primary-dark">Conversații</h2>
          <button
            onClick={newConversation}
            className="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
            title="Conversație nouă"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
          </button>
        </div>

        {/* Conversation List */}
        <div className="flex-1 overflow-y-auto">
          {conversations.length === 0 ? (
            <p className="p-4 text-sm text-secondary dark:text-gray-400 text-center">
              Nicio conversație încă
            </p>
          ) : (
            conversations.map((conv) => (
              <button
                key={conv.id}
                onClick={() => selectConversation(conv)}
                className={`w-full text-left p-3 border-b border-border/50 dark:border-border-dark/50 hover:bg-surface dark:hover:bg-surface-dark transition-colors ${
                  currentConvId === conv.id ? 'bg-blue-50 dark:bg-blue-900/20' : ''
                }`}
              >
                <div className="flex items-center gap-2 mb-1">
                  {conv.agentType && (
                    <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium ${AGENT_COLORS[conv.agentType]?.bg ?? ''} ${AGENT_COLORS[conv.agentType]?.text ?? ''}`}>
                      {AGENT_COLORS[conv.agentType]?.label ?? conv.agentType}
                    </span>
                  )}
                  <span className="text-[10px] text-secondary dark:text-gray-500">
                    {new Date(conv.createdAt).toLocaleDateString(locale)}
                  </span>
                </div>
                <p className="text-sm text-primary dark:text-primary-dark truncate">
                  {conv.title ?? 'Conversație nouă'}
                </p>
              </button>
            ))
          )}
        </div>

        {/* Mobile close */}
        <button
          onClick={() => setShowSidebar(false)}
          className="md:hidden p-3 border-t border-border dark:border-border-dark text-sm text-secondary"
        >
          Închide
        </button>
      </div>

      {/* Chat Area */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Chat Header */}
        <div className="flex items-center gap-3 p-3 border-b border-border dark:border-border-dark">
          <button
            onClick={() => setShowSidebar(true)}
            className="md:hidden p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <svg className="w-5 h-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <div className="flex items-center gap-2">
            <svg className="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.456-2.456L14.25 6l1.035-.259a3.375 3.375 0 002.456-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
            </svg>
            <h1 className="text-lg font-semibold text-primary dark:text-primary-dark">
              AI Assistant
            </h1>
          </div>
        </div>

        {/* Messages */}
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {messages.length === 0 && !loading && (
            <div className="flex flex-col items-center justify-center h-full text-center">
              <svg className="w-16 h-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
              </svg>
              <h2 className="text-lg font-medium text-primary dark:text-primary-dark mb-2">
                Asistent AI pentru Redacție
              </h2>
              <p className="text-sm text-secondary dark:text-gray-400 max-w-md">
                Trimite un mesaj sau alege un template pentru a începe. Asistentul va direcționa cererea ta la agentul potrivit.
              </p>
            </div>
          )}

          {messages.map((msg) => (
            <div key={msg.id} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
              <div className={`max-w-[80%] rounded-xl px-4 py-3 ${
                msg.role === 'user'
                  ? 'bg-blue-600 text-white'
                  : 'bg-surface-sunken dark:bg-surface-elevated-dark text-primary dark:text-primary-dark'
              }`}>
                {msg.role === 'assistant' && msg.agentType && (
                  <div className="flex items-center gap-1.5 mb-2">
                    <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium ${AGENT_COLORS[msg.agentType]?.bg ?? 'bg-gray-100'} ${AGENT_COLORS[msg.agentType]?.text ?? 'text-gray-600'}`}>
                      {AGENT_COLORS[msg.agentType]?.label ?? msg.agentType}
                    </span>
                    {(() => {
                      const provider = getProviderFromModel(msg.model);
                      if (!provider) return null;
                      const pc = PROVIDER_COLORS[provider];
                      return (
                        <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium ${pc.bg} ${pc.text}`}>
                          {pc.label}
                        </span>
                      );
                    })()}
                  </div>
                )}
                <div className="text-sm whitespace-pre-wrap break-words">{msg.content}</div>
              </div>
            </div>
          ))}

          {/* Typing Indicator */}
          {typing && (
            <div className="flex justify-start">
              <div className="bg-surface-sunken dark:bg-surface-elevated-dark rounded-xl px-4 py-3">
                <div className="flex items-center gap-2">
                  <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium ${AGENT_COLORS[typing]?.bg ?? ''} ${AGENT_COLORS[typing]?.text ?? ''}`}>
                    {AGENT_COLORS[typing]?.label ?? typing}
                  </span>
                  <span className="flex gap-1">
                    <span className="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                    <span className="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                    <span className="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
                  </span>
                </div>
              </div>
            </div>
          )}

          <div ref={messagesEndRef} />
        </div>

        {/* Template Picker Overlay */}
        {showTemplates && (
          <div className="border-t border-border dark:border-border-dark bg-surface-sunken dark:bg-surface-sunken-dark p-4 max-h-80 overflow-y-auto">
            {/* Tabs */}
            <div className="flex gap-1 mb-3">
              {Object.keys(templates).map((cat) => (
                <button
                  key={cat}
                  onClick={() => { setTemplateTab(cat); setSelectedTemplate(null); setTemplateFields({}); }}
                  className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex flex-col items-center gap-0.5 ${
                    templateTab === cat
                      ? 'bg-blue-600 text-white'
                      : 'bg-surface dark:bg-surface-dark text-secondary dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'
                  }`}
                >
                  <span>{CATEGORY_LABELS[cat] ?? cat}</span>
                  {GEMINI_CATEGORIES.has(cat) && (
                    <span className={`text-[9px] leading-none ${templateTab === cat ? 'text-blue-200' : 'text-emerald-500 dark:text-emerald-400'}`}>
                      Gemini
                    </span>
                  )}
                </button>
              ))}
            </div>

            {/* Template List or Fields Form */}
            {selectedTemplate ? (
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-medium text-primary dark:text-primary-dark">{selectedTemplate.name}</h3>
                  <button onClick={() => { setSelectedTemplate(null); setTemplateFields({}); }} className="text-xs text-secondary hover:text-primary dark:hover:text-primary-dark">
                    Înapoi
                  </button>
                </div>
                {selectedTemplate.requiredFields.map((field) => (
                  <div key={field}>
                    <label className="block mb-1 text-xs font-medium text-secondary dark:text-gray-400">{field}</label>
                    <input
                      type="text"
                      value={templateFields[field] ?? ''}
                      onChange={(e) => setTemplateFields((prev) => ({ ...prev, [field]: e.target.value }))}
                      className="form-input text-sm"
                      placeholder={`Introdu ${field}...`}
                    />
                  </div>
                ))}
                <button
                  onClick={() => submitTemplate(selectedTemplate)}
                  disabled={selectedTemplate.requiredFields.some((f) => !templateFields[f]?.trim())}
                  className="btn-primary text-xs disabled:opacity-50"
                >
                  Trimite
                </button>
              </div>
            ) : (
              <div className="space-y-2">
                {(templates[templateTab] ?? []).map((tpl) => (
                  <button
                    key={tpl.id}
                    onClick={() => {
                      if (tpl.requiredFields.length > 0) {
                        setSelectedTemplate(tpl);
                        setTemplateFields({});
                      } else {
                        sendMessage(tpl.promptTemplate, tpl.id, {});
                      }
                    }}
                    className="w-full text-left p-3 rounded-lg bg-surface dark:bg-surface-dark hover:bg-gray-50 dark:hover:bg-surface-elevated-dark border border-border dark:border-border-dark transition-colors"
                  >
                    <div className="flex items-center gap-2 mb-1">
                      <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium ${AGENT_COLORS[tpl.agentType]?.bg ?? ''} ${AGENT_COLORS[tpl.agentType]?.text ?? ''}`}>
                        {AGENT_COLORS[tpl.agentType]?.label ?? tpl.agentType}
                      </span>
                    </div>
                    <p className="text-sm font-medium text-primary dark:text-primary-dark">{tpl.name}</p>
                    {tpl.description && (
                      <p className="text-xs text-secondary dark:text-gray-400 mt-0.5">{tpl.description}</p>
                    )}
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Input Area */}
        <div className="border-t border-border dark:border-border-dark p-3">
          <div className="flex items-end gap-2">
            {/* Template button */}
            <button
              onClick={() => setShowTemplates(!showTemplates)}
              className={`flex-shrink-0 p-2 rounded-lg transition-colors ${
                showTemplates
                  ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'
                  : 'text-secondary hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
              }`}
              title="Template-uri"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
              </svg>
            </button>

            {/* Textarea */}
            <textarea
              ref={textareaRef}
              value={input}
              onChange={handleInputChange}
              onKeyDown={handleKeyDown}
              rows={1}
              placeholder="Scrie un mesaj... (Ctrl+Enter pentru a trimite)"
              disabled={loading}
              className="flex-1 resize-none form-input text-sm !py-2.5 !rounded-xl max-h-40"
            />

            {/* Send button */}
            <button
              onClick={() => sendMessage()}
              disabled={!input.trim() || loading}
              className="flex-shrink-0 p-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              {loading ? (
                <svg className="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
              ) : (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" />
                </svg>
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
