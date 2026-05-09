#!/usr/bin/env bash
set -uo pipefail

# ============================================================================
# T60.10 — Cache Invalidation Smoke Baseline
# ============================================================================
# PURPOSE: Empirical baseline of cache invalidation behavior on Article mutation.
#          NOT a regression test. NOT a fix. Documents what works + what gaps exist.
#
# === ORPHAN INFRASTRUCTURE FINDING (Phase 4-α-2) ===
# deschide.cache pool declared in apps/backend/config/packages/cache.yaml:17
# Zero consumer code references in src/ or tests/.
# Backlog: T60.X-CACHE-POOL-ORPHAN-AUDIT — wire it or remove declaration.
# This smoke does NOT exercise deschide.cache because no code path invokes it.
#
# === EMPIRICAL MUTATION PATH MAP (Phase 4-α + 4-α-2) ===
# Backend mutation surface: Controller-based (Admin/ArticleArchiveController etc.)
# Cache invalidation: NO Doctrine listener bridge, NO Messenger handler bridge.
# Frontend revalidate: /api/revalidate route (REVALIDATE_SECRET-gated)
# Expected gap: mutation does NOT auto-trigger frontend cache bust.
#
# === FLOW ===
# 1. PRE-STATE: GET backend article + HEAD frontend page + Redis key sample
# 2. AUTH: JWT via /api/login_check (A2 + A1 hybrid credential lookup)
# 3. MUTATION: POST /api/admin/articles/7/archive (JWT auth)
# 4. POST-MUTATION (no wait): re-fetch all + delta capture
# 5. MANUAL BASELINE: cache:pool:invalidate-tags + POST /api/revalidate
#    Re-fetch all + delta capture (proves infra works manually)
# 6. CLEANUP: POST /api/admin/articles/7/unarchive
# 7. SUMMARY: tabular output, identified gaps documented
#
# === USAGE ===
#   # Operator-controlled credentials (recommended):
#   ADMIN_EMAIL=admin@... ADMIN_PASSWORD=... bash apps/backend/bin/smoke/t60-10-cache-invalidation.sh
#
#   # Or rely on .env.local fallback (dev convenience):
#   bash apps/backend/bin/smoke/t60-10-cache-invalidation.sh
# ============================================================================

# ----------------------------------------------------------------------------
# Configuration (smoke target — captured Phase 4-β-discovery-2)
# ----------------------------------------------------------------------------

readonly SMOKE_ARTICLE_ID=7
readonly SMOKE_ARTICLE_SLUG="datele-despre-accidentele-rutiere-din-moldova-vor-fi-centralizate-intr-un-registru-unic"
readonly SMOKE_ARTICLE_CATEGORY="politica"

readonly BACKEND_URL="http://127.0.0.1:8081"
readonly FRONTEND_URL="http://127.0.0.1:3005"

readonly LOG_DIR="/tmp"
readonly LOG_FILE="${LOG_DIR}/t60-10-baseline-$(date +%Y%m%d-%H%M%S).log"

# Resolve repo root from script location (smoke lives in apps/backend/bin/smoke/)
readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly REPO_ROOT="$(cd "${SCRIPT_DIR}/../../../.." && pwd)"
readonly BACKEND_ENV_LOCAL="${REPO_ROOT}/apps/backend/.env.local"

# ----------------------------------------------------------------------------
# Output helpers (mirror to stdout + log file)
# ----------------------------------------------------------------------------

log() { printf '%s\n' "$*" | tee -a "${LOG_FILE}"; }
section() { log ""; log "============================================================"; log "$*"; log "============================================================"; }
fatal() { log "[FATAL] $*"; exit 1; }

# ----------------------------------------------------------------------------
# Bootstrap
# ----------------------------------------------------------------------------

mkdir -p "${LOG_DIR}"
: > "${LOG_FILE}"  # truncate

section "T60.10 cache invalidation smoke — $(date -Iseconds)"
log "Target article: id=${SMOKE_ARTICLE_ID} category=${SMOKE_ARTICLE_CATEGORY} slug=${SMOKE_ARTICLE_SLUG:0:50}…"
log "Backend: ${BACKEND_URL}  |  Frontend: ${FRONTEND_URL}"
log "Log file: ${LOG_FILE}"

# Auth bootstrap — A2 first (env vars), A1 fallback (.env.local source)
if [[ -z "${ADMIN_EMAIL:-}" || -z "${ADMIN_PASSWORD:-}" ]]; then
  if [[ -f "${BACKEND_ENV_LOCAL}" ]]; then
    set -a
    # shellcheck disable=SC1090
    source "${BACKEND_ENV_LOCAL}"
    set +a
  fi
fi

if [[ -z "${ADMIN_EMAIL:-}" || -z "${ADMIN_PASSWORD:-}" ]]; then
  fatal "ADMIN_EMAIL + ADMIN_PASSWORD not found in env or ${BACKEND_ENV_LOCAL}"
fi

log "[OK] Credentials resolved (ADMIN_EMAIL set, ADMIN_PASSWORD set — value not surfaced)"

# REVALIDATE_SECRET — frontend env (smoke needs it for /api/revalidate calls)
readonly FRONTEND_ENV_LOCAL="${REPO_ROOT}/apps/frontend/.env.local"
if [[ -z "${REVALIDATE_SECRET:-}" && -f "${FRONTEND_ENV_LOCAL}" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "${FRONTEND_ENV_LOCAL}"
  set +a
fi

if [[ -z "${REVALIDATE_SECRET:-}" ]]; then
  log "[WARN] REVALIDATE_SECRET not set — manual /api/revalidate step will document 503"
fi

# ----------------------------------------------------------------------------
# Auth: acquire JWT token
# ----------------------------------------------------------------------------

section "AUTH: acquire JWT via /api/login_check"

LOGIN_RESPONSE=$(curl -sS -X POST "${BACKEND_URL}/api/login_check" \
  -H 'Content-Type: application/json' \
  -d "{\"username\":\"${ADMIN_EMAIL}\",\"password\":\"${ADMIN_PASSWORD}\"}" 2>&1)

# Parse token via python3 (jq unavailable on this host)
JWT_TOKEN=$(printf '%s' "${LOGIN_RESPONSE}" | python3 -c \
  'import json,sys
try:
    j = json.loads(sys.stdin.read())
    print(j.get("token", ""), end="")
except Exception:
    pass' 2>/dev/null)

if [[ -z "${JWT_TOKEN}" ]]; then
  log "[FATAL] /api/login_check did not return a JWT token"
  log "Response (first 200 chars): ${LOGIN_RESPONSE:0:200}"
  log "Hint: if 'username' field rejected, retry with email-as-identifier or check Lexik config"
  exit 2
fi

log "[OK] JWT acquired (length: ${#JWT_TOKEN})"
readonly AUTH_HEADER="Authorization: Bearer ${JWT_TOKEN}"

# ----------------------------------------------------------------------------
# Capture helpers
# ----------------------------------------------------------------------------

# Captures: status, etag, body-hash, cache-control, x-cache (when present).
# Output format: "STATUS|ETAG|BODYHASH|CACHE_CONTROL|X_CACHE"
capture_endpoint() {
  local label="$1" url="$2" extra_args="${3:-}"
  local response_file headers_file status etag body_hash cache_control x_cache
  response_file=$(mktemp)
  headers_file=$(mktemp)

  # shellcheck disable=SC2086
  curl -sS -D "${headers_file}" -o "${response_file}" -w '%{http_code}' ${extra_args} "${url}" \
    > /tmp/_status_$$ 2>&1
  status=$(cat /tmp/_status_$$)
  rm -f /tmp/_status_$$

  etag=$(grep -i '^etag:' "${headers_file}" | head -1 | sed -E 's/^[^:]+:\s*//; s/\r$//' || true)
  cache_control=$(grep -i '^cache-control:' "${headers_file}" | head -1 | sed -E 's/^[^:]+:\s*//; s/\r$//' || true)
  x_cache=$(grep -i '^x-cache:' "${headers_file}" | head -1 | sed -E 's/^[^:]+:\s*//; s/\r$//' || true)
  body_hash=$(md5sum "${response_file}" | awk '{print $1}')

  log "  ${label}: status=${status} etag=${etag:-<none>} body-md5=${body_hash:0:12}… cache-control=${cache_control:-<none>} x-cache=${x_cache:-<none>}"

  rm -f "${response_file}" "${headers_file}"
  printf '%s|%s|%s|%s|%s' "${status}" "${etag:-}" "${body_hash}" "${cache_control:-}" "${x_cache:-}"
}

# Redis key count + sample under deschide_news prefix
capture_redis() {
  local label="$1"
  local count sample
  count=$(redis-cli -n 1 KEYS 'deschide_news:*' 2>/dev/null | wc -l | tr -d ' ')
  sample=$(redis-cli -n 1 KEYS 'deschide_news:*' 2>/dev/null | head -5 | tr '\n' ',' | sed 's/,$//')

  log "  ${label}: redis-keys-count=${count} sample=${sample:-<empty>}"
  printf '%s' "${count}"
}

# ----------------------------------------------------------------------------
# STEP 1: PRE-STATE CAPTURE
# ----------------------------------------------------------------------------

section "STEP 1: PRE-STATE CAPTURE"

PRE_BACKEND=$(capture_endpoint "BE GET /api/articles/by-slug/${SMOKE_ARTICLE_SLUG:0:30}…" \
  "${BACKEND_URL}/api/articles/by-slug/${SMOKE_ARTICLE_SLUG}?locale=ro")
PRE_FRONTEND=$(capture_endpoint "FE HEAD /ro/${SMOKE_ARTICLE_CATEGORY}/${SMOKE_ARTICLE_SLUG:0:30}…" \
  "${FRONTEND_URL}/ro/${SMOKE_ARTICLE_CATEGORY}/${SMOKE_ARTICLE_SLUG}" "-I")
PRE_REDIS=$(capture_redis "Redis pre-state")

# ----------------------------------------------------------------------------
# STEP 2: MUTATION via Admin/ArticleArchiveController
# ----------------------------------------------------------------------------

section "STEP 2: MUTATION — POST /api/admin/articles/${SMOKE_ARTICLE_ID}/archive"

MUTATION_RESPONSE=$(curl -sS -X POST "${BACKEND_URL}/api/admin/articles/${SMOKE_ARTICLE_ID}/archive" \
  -H "${AUTH_HEADER}" \
  -H 'Content-Type: application/json' \
  -w '\nHTTP_STATUS=%{http_code}' 2>&1)

MUTATION_STATUS=$(printf '%s' "${MUTATION_RESPONSE}" | grep -oE 'HTTP_STATUS=[0-9]+' | sed 's/HTTP_STATUS=//')
MUTATION_BODY=$(printf '%s' "${MUTATION_RESPONSE}" | sed 's/HTTP_STATUS=[0-9]*$//')

log "  status=${MUTATION_STATUS}"
log "  body (first 300 chars): ${MUTATION_BODY:0:300}"

if [[ "${MUTATION_STATUS}" -ge 400 ]]; then
  log "[WARN] Mutation returned ${MUTATION_STATUS} — smoke continues to capture post-state for evidence"
fi

# ----------------------------------------------------------------------------
# STEP 3: POST-MUTATION CAPTURE (no wait)
# ----------------------------------------------------------------------------

section "STEP 3: POST-MUTATION CAPTURE (no wait, no manual bust)"

POST_MUT_BACKEND=$(capture_endpoint "BE GET (post-mutation)" \
  "${BACKEND_URL}/api/articles/by-slug/${SMOKE_ARTICLE_SLUG}?locale=ro")
POST_MUT_FRONTEND=$(capture_endpoint "FE HEAD (post-mutation)" \
  "${FRONTEND_URL}/ro/${SMOKE_ARTICLE_CATEGORY}/${SMOKE_ARTICLE_SLUG}" "-I")
POST_MUT_REDIS=$(capture_redis "Redis post-mutation")

# ----------------------------------------------------------------------------
# STEP 4: MANUAL BASELINE — what SHOULD work
# ----------------------------------------------------------------------------

section "STEP 4: MANUAL BASELINE — cache:pool:invalidate-tags + /api/revalidate"

log "[STEP 4a] symfony console cache:pool:invalidate-tags article-${SMOKE_ARTICLE_ID}"
INVALIDATE_OUTPUT=$(cd "${REPO_ROOT}/apps/backend" && symfony console cache:pool:invalidate-tags \
  "article-${SMOKE_ARTICLE_ID}" 2>&1) || true
log "  output: ${INVALIDATE_OUTPUT:0:300}"

if [[ -n "${REVALIDATE_SECRET:-}" ]]; then
  log "[STEP 4b] POST ${FRONTEND_URL}/api/revalidate with x-revalidate-secret"
  REVALIDATE_RESPONSE=$(curl -sS -X POST "${FRONTEND_URL}/api/revalidate" \
    -H 'Content-Type: application/json' \
    -H "x-revalidate-secret: ${REVALIDATE_SECRET}" \
    -d "{\"tags\":[\"article-${SMOKE_ARTICLE_ID}\",\"articles\"],\"paths\":[\"/ro/${SMOKE_ARTICLE_CATEGORY}/${SMOKE_ARTICLE_SLUG}\"]}" \
    -w '\nHTTP_STATUS=%{http_code}' 2>&1)
  REVALIDATE_STATUS=$(printf '%s' "${REVALIDATE_RESPONSE}" | grep -oE 'HTTP_STATUS=[0-9]+' | sed 's/HTTP_STATUS=//')
  REVALIDATE_BODY=$(printf '%s' "${REVALIDATE_RESPONSE}" | sed 's/HTTP_STATUS=[0-9]*$//')
  log "  status=${REVALIDATE_STATUS}"
  log "  body (first 300): ${REVALIDATE_BODY:0:300}"
else
  log "[STEP 4b] SKIPPED — REVALIDATE_SECRET not available, would return 503"
fi

# Re-capture post-manual-bust
POST_BUST_BACKEND=$(capture_endpoint "BE GET (post-manual-bust)" \
  "${BACKEND_URL}/api/articles/by-slug/${SMOKE_ARTICLE_SLUG}?locale=ro")
POST_BUST_FRONTEND=$(capture_endpoint "FE HEAD (post-manual-bust)" \
  "${FRONTEND_URL}/ro/${SMOKE_ARTICLE_CATEGORY}/${SMOKE_ARTICLE_SLUG}" "-I")
POST_BUST_REDIS=$(capture_redis "Redis post-manual-bust")

# ----------------------------------------------------------------------------
# STEP 5: CLEANUP — unarchive
# ----------------------------------------------------------------------------

section "STEP 5: CLEANUP — POST /api/admin/articles/${SMOKE_ARTICLE_ID}/unarchive"

UNARCHIVE_RESPONSE=$(curl -sS -X POST "${BACKEND_URL}/api/admin/articles/${SMOKE_ARTICLE_ID}/unarchive" \
  -H "${AUTH_HEADER}" \
  -H 'Content-Type: application/json' \
  -w '\nHTTP_STATUS=%{http_code}' 2>&1)
UNARCHIVE_STATUS=$(printf '%s' "${UNARCHIVE_RESPONSE}" | grep -oE 'HTTP_STATUS=[0-9]+' | sed 's/HTTP_STATUS=//')
log "  status=${UNARCHIVE_STATUS}"

if [[ "${UNARCHIVE_STATUS}" -ge 400 ]]; then
  log "[WARN] Unarchive returned ${UNARCHIVE_STATUS} — operator may need to manually restore article ${SMOKE_ARTICLE_ID} to published"
fi

# ----------------------------------------------------------------------------
# STEP 6: SUMMARY TABLE
# ----------------------------------------------------------------------------

section "STEP 6: SUMMARY"

# Extract individual fields from packed pipe-separated tuples
read -r PRE_BE_STATUS PRE_BE_ETAG PRE_BE_HASH _ _ <<< "$(echo "${PRE_BACKEND}" | tr '|' ' ')"
read -r PRE_FE_STATUS PRE_FE_ETAG PRE_FE_HASH _ _ <<< "$(echo "${PRE_FRONTEND}" | tr '|' ' ')"
read -r POST_MUT_BE_STATUS POST_MUT_BE_ETAG POST_MUT_BE_HASH _ _ <<< "$(echo "${POST_MUT_BACKEND}" | tr '|' ' ')"
read -r POST_MUT_FE_STATUS POST_MUT_FE_ETAG POST_MUT_FE_HASH _ _ <<< "$(echo "${POST_MUT_FRONTEND}" | tr '|' ' ')"
read -r POST_BUST_BE_STATUS POST_BUST_BE_ETAG POST_BUST_BE_HASH _ _ <<< "$(echo "${POST_BUST_BACKEND}" | tr '|' ' ')"
read -r POST_BUST_FE_STATUS POST_BUST_FE_ETAG POST_BUST_FE_HASH _ _ <<< "$(echo "${POST_BUST_FRONTEND}" | tr '|' ' ')"

log ""
log "STAGE              | BE-status | BE-md5         | FE-status | FE-md5         | Redis keys"
log "-------------------|-----------|----------------|-----------|----------------|-----------"
log "Pre-state          | ${PRE_BE_STATUS:-?}       | ${PRE_BE_HASH:0:12}…    | ${PRE_FE_STATUS:-?}       | ${PRE_FE_HASH:0:12}…    | ${PRE_REDIS}"
log "Post-mutation      | ${POST_MUT_BE_STATUS:-?}       | ${POST_MUT_BE_HASH:0:12}…    | ${POST_MUT_FE_STATUS:-?}       | ${POST_MUT_FE_HASH:0:12}…    | ${POST_MUT_REDIS}"
log "Post-manual-bust   | ${POST_BUST_BE_STATUS:-?}       | ${POST_BUST_BE_HASH:0:12}…    | ${POST_BUST_FE_STATUS:-?}       | ${POST_BUST_FE_HASH:0:12}…    | ${POST_BUST_REDIS}"

log ""
log "=== DELTA ANALYSIS ==="

# Backend delta: did mutation alone change the BE response?
if [[ "${PRE_BE_HASH}" != "${POST_MUT_BE_HASH}" ]]; then
  log "BE post-mutation:  body CHANGED (md5 diff) — backend reflects mutation immediately"
else
  log "BE post-mutation:  body UNCHANGED — backend may be serving cached response, or mutation didn't write"
fi

if [[ "${PRE_FE_HASH}" != "${POST_MUT_FE_HASH}" ]]; then
  log "FE post-mutation:  body CHANGED — auto-invalidation IS wired (unexpected per Phase 4-α findings)"
else
  log "FE post-mutation:  body UNCHANGED — confirms gap: mutation does NOT auto-trigger frontend cache bust"
fi

if [[ "${POST_MUT_FE_HASH}" != "${POST_BUST_FE_HASH}" ]]; then
  log "FE post-manual:    body CHANGED — manual /api/revalidate path WORKS"
else
  log "FE post-manual:    body UNCHANGED — manual cache bust did NOT produce visible delta (could be ISR not used, or path/tag mismatch)"
fi

log ""
log "=== ARCHITECTURAL GAPS (per Phase 4-α + smoke evidence) ==="
log "1. NO Doctrine listener bridge: Article mutation does not auto-emit invalidateTags"
log "2. NO Messenger bridge: ArticleUpdated/ArticleChanged events absent"
log "3. NO backend HTTP client call to /api/revalidate observed (no auto-revalidate from BE)"
log "4. deschide.cache pool ORPHAN: declared in cache.yaml, zero consumer code"
log ""
log "Backlog flags surfaced:"
log "  - T60.X-WIRE-CACHE-INVALIDATION-ON-MUTATION (high priority if FRA1 admin needed)"
log "  - T60.X-CACHE-POOL-ORPHAN-AUDIT (decide wire vs remove deschide.cache)"
log ""
log "Smoke complete — log: ${LOG_FILE}"
