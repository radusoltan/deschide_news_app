---
adr: 31
title: Multi-Agent Orchestration Discipline
status: Accepted
date_proposed: 2026-04-24
date_decided: 2026-04-24
supersedes: none
related: "ADR-024 (LLM routing), ADR-025 (suspension), ADR-026 (v1.4.0 recovery)"
author: "Radu (orchestrator) + Claude (proposed)"
---

# ADR-031 — Multi-Agent Orchestration Discipline

## Status

Accepted (decided 2026-04-24).

## Context

Sprint 58-recovery (2026-04-24) a fost primul sprint orchestrat cu **4 agenți 
AI distincti** (Claude Code Opus 4.7, Claude Code Sonnet, Codex, Gemini CLI) 
peste 7 zile, cu scope cross-cutting (backend + frontend + documentație + 
git operations). Sprint-ul a livrat v1.4.0 (tag e566e78) cu 5 Known Issues 
transparent documentate.

În cursul sprint-ului au apărut **pattern-uri metodologice** care merită 
codificate pentru sprinturile viitoare. Unele au confirmat ipoteze, altele 
au surprins. Fără codificare, fiecare sprint viitor riscă să re-învețe 
aceleași lecții.

## Problema

Orchestrarea multi-agent introduce provocări care NU există în single-agent 
workflows:

1. **Agent-task matching incorect** — folosirea agentului greșit pentru un 
   task (ex: Gemini pe evidence-bound audit) cauzează erori silențioase, 
   nu refuzuri explicite
2. **Checkpoint discipline** — agenții pot continua autonom peste boundaries 
   critice dacă prompt-ul nu impune STOP explicit
3. **Information handoff drift** — state-ul realității pe disc se poate 
   diverge de memory/documentation între zile, fără detectare automată
4. **Tool quota limits** — un agent poate deveni indisponibil mid-sprint 
   (Day 6 Gemini quota)
5. **Premise false propagate** — orchestratorul poate recomanda decizii 
   bazate pe memory stale, care apoi se propagă la agenți

## Decizii

### D1 — Agent-Task Matching Matrix

| Task Type | Primary Agent | Fallback | Interzis |
|---|---|---|---|
| Backend PHP/Symfony implementation | Claude Code (Opus/Sonnet) | — | Gemini, Codex |
| Frontend TypeScript/React implementation | Codex | Claude Code Opus | Gemini |
| Git operations (merge, reconcile, tag) | Claude Code | — | Gemini, Codex |
| Evidence-bound reconstruction (backfill, audit against sources) | Claude Code Sonnet | Claude Code Opus | **Gemini CLI** |
| Open-ended synthesis (research, creative writing) | Gemini, Claude Code Opus | — | — |
| Translation (per ADR-024 am.12) | **Gemini CLI exclusive** | — | Claude, Codex |
| Documentation drafting (ADR, runbook) | Claude Code Sonnet | Claude Code Opus | Gemini without CC verify |
| Schema migrations, DB ops | Claude Code | — | Gemini, Codex |
| Diagnostic-heavy (multi-root-cause) | Claude Code Opus | Sonnet | Gemini |

**Rule of thumb**: dacă fabrication-of-plausible-content = silent corruption 
pe acest task, Gemini e **exclus**. Dacă creative completion = valoare, 
Gemini e acceptabil.

### D2 — Checkpoint Discipline

Toate prompt-urile multi-phase (>2 tasks) trebuie să conțină:

1. **STOP points explicit** după fiecare task critic, cu formulare:
   ```
   STOP point N: raportează [X, Y, Z]. Aștept OK explicit în chat înainte 
   de următorul task. NU continua autonom.
   ```
2. **Criterii pentru fail-fast STOP** (ex: "dacă smoke test eșuează, STOP 
   imediat, NU încerca fix improvizat")
3. **Checkpoint order enforcement** — dacă task B depinde de PASS pe task A, 
   spune explicit "B NU pornește fără OK-ul A".

Anti-pattern observat (Day 7 initial): 7c a rulat autonom chiar când 7b 
a raportat blockers. Fix: STOP-uri explicit între ele.

### D3 — Discovery-First Pattern

Fiecare sprint/zi nouă pornește cu:

1. **Read-only empirical check** al realității pe disc (NU read from memory/ADR)
2. **Diff realitate vs memory/docs**
3. **Raport discrepanțe** către orchestrator înainte de orice implementation

Anti-pattern: "memory zice X, deci procedăm pe baza lui X" fără verify 
empiric. Sprint 58 initial tag recommendation (v1.3.0) a suferit de asta — 
memory era stale, realitatea avea orfani v1.3.x.

### D4 — Agent Quota Resilience

Pentru sprinturi multi-zi, orchestratorul trebuie:

1. **Identifică SPOFs** (single points of failure) — task-uri unde doar un 
   agent poate face, și care e quota-limited
2. **Plan fallback mapping** — dacă agent X depășește quota, cine preia task Y
3. **Memory-preserve pentru handoff** — agent-ul replacement trebuie să 
   poată relua cu context complet (discover ce a făcut predecessor-ul din 
   commits/vault, nu "ghicește")

Implementat Day 6: Gemini quota exceeded → CC Sonnet takeover cu prompt 
complet rewritten + discipline clarification.

### D5 — Memory Hygiene

Orchestratorul menține memory system:

1. **Update memory IMEDIAT** după decizii arhitecturale (ADR accept, tag 
   release, pivot major)
2. **Memory entries sub 500 chars** — force concision, previne bloat
3. **Memory entries actionable** — nu trivia istorică, ci context care 
   afectează decizii viitoare
4. **Mark memory ca stale** când realitatea diverge (nu aștepta să fie 
   "overwritten", proactive replace)

Implementat Sprint 58-recovery: memory #30 updated 3 ori pe parcursul 
sprint-ului reflectând state reală (paper-only → in-progress → shipped).

### D6 — Two-Repo Commit Convention

Pentru proiecte cu code monorepo + docs vault (Deschide.md pattern):

1. **Code changes** → monorepo commits pe feature branch
2. **Documentation changes** (ADR, audit logs, runbooks) → vault commits pe main
3. **Marker commits în monorepo** pentru sprinturi pur-doc (ca recovery Day 1)
4. **Convenție naming** commits: `chore(sprint-X): day N - {scope}` în ambele 
   repo-uri cu același mesaj prefix

Beneficiu: doc changes nu murdăresc code history, code changes nu 
inundă vault. Grep-uri cleaner per scope.

### D7 — Release Gate Coherence

Tag-urile (v1.x.y) nu sunt gate-uri perfection. Tag-ul e:

- ✅ v1.4.0 = ADR-025 implemented + translations operational + zero 
  regressions vs baseline + Known Issues documented transparent
- ❌ v1.4.0 = all bugs fixed + all debt paid + CI green + no Known Issues

**Principle**: scope-recovery sprint tag-uiește când scope-ul e closed, nu 
când codebase-ul e perfect. Known Issues = honest debt, nu obstacol.

Paper-only gates (CI broken from infra stale) sunt acceptabile dacă 
local verification comprehensive (tsc + lint + unit + integration + smoke).

Anti-pattern: "nu tag-uim până nu e totul perfect" → nu tag-uim niciodată → 
sprint pierdut → morale team hit.

### D8 — Orchestrator Responsibilities

Orchestratorul (human + Claude Desktop / chat interface) are responsabilitate:

1. **Decisions architectural** (ADR-uri, tag strategy, scope gating)
2. **Agent-task matching** per D1
3. **Prompt design** cu discipline D2-D3
4. **Checkpoint reviews** — fiecare agent report reviewed înainte de green-light
5. **Memory hygiene** per D5
6. **Cross-agent synthesis** — când rapoarte diverge, orchestrator reconciliază

Orchestratorul **NU face**:

- Implementation directly (excepție: micro-fixes <5 min)
- Agent impersonation ("dacă aș fi CC, aș face X")
- Skip checkpoints sub presiune timeline

## Consequences

### Positive

1. **Sprint discipline replicable** across future sprints
2. **Agent fabrication incidents detectabile** prin pattern matching (D1)
3. **Sprint recovery-uri viitoare** pot referenți ADR-031 ca foundation
4. **Onboarding agenți noi** (ex: viitor Claude 5, viitor Codex v2) — matrix D1 
   poate fi updated fără refactor complet

### Negative

1. **Overhead de orchestration**: fiecare prompt multi-phase cere 30+ linii extra 
   de discipline boilerplate
2. **Checkpoint reviews** ocupă orchestrator bandwidth (nu pot rula "fire and 
   forget" prompt-uri)
3. **Agent fallback planning** adaugă complexity la sprint planning

### Neutral

1. Matrix D1 va necesita updates periodice pe măsură ce capabilities evoluează
2. D7 discipline (Known Issues transparent) cere maturity team pentru a nu 
   fi folosită ca excuse pentru debt accumulation

## Implementation

1. Acest ADR se adoptă ca **foundational pentru sprinturi 59+**
2. Orchestrator (Radu + Claude Desktop) referențiază ADR-031 când design 
   prompts multi-phase
3. Review periodic (every 5 sprints) pentru updates matrix D1

## Evidence

Sprint 58-recovery (2026-04-24) incidents codificate aici:

- **D1 fabrication**: Gemini Day 6 — 5 fake tags, 1 fake SHA, 10 omitted 
  commits în backfill. CC Sonnet strict pe same prompt.
- **D2 sequential break**: Day 7c a rulat autonom când Day 7b raportase 
  blockers. Fix: explicit STOP-uri implementate în Day 7e/7d prompts.
- **D3 discovery gap**: v1.3.0 tag recommendation inițială bazată pe memory 
  stale. Day 1 CC discovery prins orfani v1.3.x.
- **D4 quota**: Gemini quota exceeded Day 6 mid-sprint. CC Sonnet takeover 
  reușit.
- **D5 memory**: 3 update-uri memory #30 pe parcursul sprint-ului.
- **D6 two-repo**: aplicat Day 1-7, zero conflict între vault și monorepo.
- **D7 CI broken**: Option B decision — document Known Issue, proceed tag. 
  v1.4.0 shipped cu CI red, zero user impact.

## References

- Sprint 58-recovery reports: `vault/50_Audit/sprint-58-recovery-day*-*.md`
- ADR-024 am.12: Gemini translation exclusivity
- ADR-025: Editorial pipeline suspension intent
- ADR-026: v1.4.0 recovery tag strategy
- Memory #2 (Gemini tool discipline gap)
- Memory #30 (Sprint 58-recovery shipped)
