---
name: process-press-emails
description: Procesează email-urile de presă din Zoho Mail și creează articole automat.
allowed-tools:
  - mcp__zoho-mail__list_emails
  - mcp__zoho-mail__get_email_content
  - mcp__zoho-mail__mark_as_read
  - mcp__zoho-mail__move_to_folder
  - Bash
  - Read
context: fork
agent: email-press-redactor
model: claude-sonnet-4-5
---

Procesează toate email-urile necitite din folderul "Press / Comunicate" din Zoho Mail.

Pentru fiecare email valid:
1. Extrage comunicatul de presă
2. Redactează un articol jurnalistic neutru în limba română
3. Creează articolul via API cu `status: "new"` și `sourceEmail` setat
4. Marchează email-ul ca procesat

Raportează la final câte email-uri au fost procesate și câte articole au fost create.
