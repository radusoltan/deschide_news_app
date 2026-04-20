#!/usr/bin/env bash
# ==============================================================================
# Supervisor config reconciliation — repo ↔ system
#
# Sprint 57 T57.07 (ADR-023 D1 + D3, revised post-schedule-verification).
#
# Usage
# -----
#
#   scripts/supervisor/sync.sh [--dry-run|--apply] [--reason="..."] \
#                              [--accept-reconcile=<csv>]
#
# Modes
# -----
#
#   --dry-run              Default. Read-only. Prints per-file verdict table +
#                          overall drift summary. Exit 0 unless pre-flight
#                          refuses.
#
#   --apply                Executes reconciliation. Requires --reason for
#                          the changelog entry. Dispatches each file by its
#                          computed verdict category (see below).
#
# Verdict categories (computed per file, inside-out)
# --------------------------------------------------
#
#   IDENTICAL              File exists on both sides with identical content.
#                          → no-op.
#
#   SYSTEM-ONLY            File exists only under /etc/supervisor/conf.d/.
#                          → promote to repo (non-sudo write into
#                            apps/backend/config/supervisor/).
#
#   REPO-ONLY              File exists only under apps/backend/config/supervisor/.
#                          → install on system (sudo cp into
#                            /etc/supervisor/conf.d/ with .pre-sync-TS.bak
#                            safety copy of any prior state).
#
#   OVERLAP-DIFF-FULL      File differs; per FULL_REPLACE_STRATEGY the operator
#                          must explicitly ack via --accept-reconcile=<filename>.
#                          → full-file replace (direction per strategy map).
#
#   OVERLAP-DIFF-HYBRID    File differs; per HYBRID_MERGE_STRATEGY certain
#                          fields come from repo, others from system.
#                          → render hybrid under /tmp/sync-hybrid-<file>.conf,
#                            show three-way diff, then sudo cp to system.
#
# Hybrid merge strategy map (declarative, per-file)
# -------------------------------------------------
#
# Each entry lists `<field>_from_<side>` directives. Recognised fields are:
#   command, autostart, autorestart, numprocs.
# Add a new hybrid case by appending to HYBRID_MERGE_STRATEGY; no new code path
# is required unless the field vocabulary needs to grow.
#
# Post-apply sequence
# -------------------
#
#   1. sudo supervisorctl reread
#   2. sudo supervisorctl update
#   3. re-run in --dry-run mode and verify 0 remaining drift
#   4. append changelog entry to
#      /mnt/c/Users/Radu/DeschideVault/50_Audit/supervisor-sync-YYYY-MM-DD.md
#   5. emit supervisorctl status before/after snapshot pair
#
# Safety
# ------
#
#   - Pre-flight refuses if any supervisor program is FATAL or BACKOFF.
#   - Pre-flight refuses if run from outside the repo root.
#   - On cp failure mid-apply, previously-touched system files revert from
#     the .pre-sync-TS.bak snapshots created at the start of the apply pass.
#   - Never runs `supervisorctl restart` without explicit operator request;
#     `reread + update` lets supervisord decide what to start/stop per autostart.
#
# Exit codes
# ----------
#   0  success (dry-run or apply)
#   1  invalid arguments
#   2  pre-flight refused (FATAL/BACKOFF or wrong cwd)
#   3  reconciliation needed operator decision (OVERLAP-DIFF-FULL without
#      --accept-reconcile)
#   4  apply failure (rollback attempted; inspect changelog for state)
# ==============================================================================

set -euo pipefail

# ------------------------------------------------------------------------------
# Config
# ------------------------------------------------------------------------------

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
REPO_DIR="${REPO_ROOT}/apps/backend/config/supervisor"
SYSTEM_DIR="/etc/supervisor/conf.d"
CHANGELOG_DIR="/mnt/c/Users/Radu/DeschideVault/50_Audit"
TIMESTAMP="$(date '+%Y%m%d_%H%M%S')"
CHANGELOG_FILE="${CHANGELOG_DIR}/supervisor-sync-$(date '+%Y-%m-%d').md"

# Hybrid merge strategy — per-file field origin mapping.
# Format: "<field>_from_<side>" where side ∈ {repo, system}.
declare -A HYBRID_MERGE_STRATEGY=(
    ["messenger-translations.conf"]="command_from_repo,autostart_from_system,autorestart_from_system"
)

# Full-replace strategy — per-file winner for OVERLAP-DIFF-FULL verdict.
# Operator must explicitly ack via --accept-reconcile=<filename>.
declare -A FULL_REPLACE_STRATEGY=(
    ["messenger-async.conf"]="system"
)

# ------------------------------------------------------------------------------
# Globals set by arg parsing
# ------------------------------------------------------------------------------

MODE="dry-run"
REASON=""
declare -A ACCEPTED_RECONCILE

# Track files modified by apply pass for rollback.
# Parallel arrays: MODIFIED_SYSTEM_FILES[i] + MODIFIED_SYSTEM_BAKS[i].
declare -a MODIFIED_SYSTEM_FILES=()
declare -a MODIFIED_SYSTEM_BAKS=()

# ------------------------------------------------------------------------------
# Helpers
# ------------------------------------------------------------------------------

fail() { printf 'ERROR: %s\n' "$*" >&2; exit "${2:-1}"; }

parse_args() {
    for arg in "$@"; do
        case "$arg" in
            --dry-run) MODE="dry-run" ;;
            --apply)   MODE="apply" ;;
            --reason=*) REASON="${arg#--reason=}" ;;
            --accept-reconcile=*)
                IFS=',' read -ra _names <<< "${arg#--accept-reconcile=}"
                for n in "${_names[@]}"; do ACCEPTED_RECONCILE["$n"]=1; done
                ;;
            -h|--help) sed -n '/^# Usage/,/^# ===/p' "$0" | head -60; exit 0 ;;
            *) fail "Unknown argument: $arg" 1 ;;
        esac
    done

    if [[ "$MODE" == "apply" && -z "$REASON" ]]; then
        fail "--apply requires --reason=\"...\"" 1
    fi
}

preflight() {
    # Cwd sanity — script must be invoked from repo root (or a path that
    # resolves to it). The REPO_ROOT is computed from $0 so this check is
    # effectively "the repo layout is as expected at script path".
    if [[ ! -d "${REPO_DIR}" ]]; then
        fail "Repo supervisor dir not found at ${REPO_DIR}" 2
    fi
    if [[ ! -d "${SYSTEM_DIR}" ]]; then
        fail "System supervisor dir not found at ${SYSTEM_DIR}" 2
    fi

    # Refuse if supervisor is in a degraded state. FATAL or BACKOFF programs
    # suggest unresolved prior failures; touching configs now risks compounding.
    local bad_states
    bad_states="$(sudo -n supervisorctl status 2>/dev/null \
        | awk '{print $2}' \
        | grep -E '^(FATAL|BACKOFF)$' || true)"
    if [[ -n "$bad_states" ]]; then
        printf 'Pre-flight blocked: supervisor has FATAL/BACKOFF programs:\n' >&2
        sudo -n supervisorctl status | grep -E 'FATAL|BACKOFF' >&2
        exit 2
    fi
}

# List .conf files (filter out .bak / .pre-* convention suffixes).
list_system_confs() {
    sudo -n find "${SYSTEM_DIR}" -maxdepth 1 -type f -name '*.conf' \
        -not -name '*.bak*' -not -name '*.pre-*' \
        -printf '%f\n' | sort
}

list_repo_confs() {
    find "${REPO_DIR}" -maxdepth 1 -type f -name '*.conf' -printf '%f\n' | sort
}

# Classify a single file into a verdict category.
# Emits one token on stdout from the set:
#   IDENTICAL | SYSTEM-ONLY | REPO-ONLY | OVERLAP-DIFF-FULL | OVERLAP-DIFF-HYBRID
classify() {
    local name="$1"
    local repo_path="${REPO_DIR}/${name}"
    local system_path="${SYSTEM_DIR}/${name}"

    local has_repo=0 has_system=0
    [[ -f "$repo_path" ]] && has_repo=1
    sudo -n test -f "$system_path" && has_system=1

    if (( has_repo && has_system )); then
        if sudo -n diff -q "$system_path" "$repo_path" >/dev/null 2>&1; then
            echo "IDENTICAL"
        elif [[ -n "${HYBRID_MERGE_STRATEGY[$name]:-}" ]]; then
            echo "OVERLAP-DIFF-HYBRID"
        else
            echo "OVERLAP-DIFF-FULL"
        fi
    elif (( has_system )); then
        echo "SYSTEM-ONLY"
    elif (( has_repo )); then
        echo "REPO-ONLY"
    else
        echo "MISSING"
    fi
}

# ------------------------------------------------------------------------------
# Hybrid merge — render a file that takes `<field>_from_repo` lines from repo,
# `<field>_from_system` lines from system, and everything else from system
# (the operational side is the default, hybrid is the exception).
# ------------------------------------------------------------------------------

render_hybrid() {
    local name="$1"
    local out_path="$2"
    local strategy="${HYBRID_MERGE_STRATEGY[$name]}"

    local repo_path="${REPO_DIR}/${name}"
    local system_tmp
    system_tmp="$(mktemp)"
    sudo -n cat "${SYSTEM_DIR}/${name}" > "$system_tmp"

    # Build a list of directives: field → which side to pull from.
    declare -A field_side
    IFS=',' read -ra _directives <<< "$strategy"
    for d in "${_directives[@]}"; do
        local field="${d%%_from_*}"
        local side="${d##*_from_}"
        field_side["$field"]="$side"
    done

    # Start from system as base; overlay repo-side fields.
    cp "$system_tmp" "$out_path"

    for field in "${!field_side[@]}"; do
        local side="${field_side[$field]}"
        local src_path
        case "$side" in
            repo)   src_path="$repo_path" ;;
            system) src_path="$system_tmp" ;;
            *) fail "Unknown side '$side' in hybrid strategy for $name" 1 ;;
        esac

        # Extract the target line from src and patch it into out_path.
        local src_line
        src_line="$(grep -E "^${field}=" "$src_path" | head -1)"
        if [[ -z "$src_line" ]]; then
            fail "Hybrid merge: field '$field' not found in $side for $name" 1
        fi
        # Replace the corresponding line in out_path.
        sed -i "s|^${field}=.*|${src_line}|" "$out_path"
    done

    rm -f "$system_tmp"
}

# ------------------------------------------------------------------------------
# Apply handlers (per-verdict)
# ------------------------------------------------------------------------------

apply_promote_to_repo() {
    local name="$1"
    sudo -n cat "${SYSTEM_DIR}/${name}" > "${REPO_DIR}/${name}"
    printf '  + promoted %s → repo\n' "$name"
}

apply_install_to_system() {
    local name="$1"
    local system_path="${SYSTEM_DIR}/${name}"
    # Capture any prior system state (shouldn't exist for REPO-ONLY but be safe).
    if sudo -n test -f "$system_path"; then
        local bak="${system_path}.pre-sync-${TIMESTAMP}.bak"
        sudo -n cp -p "$system_path" "$bak"
        MODIFIED_SYSTEM_FILES+=("$system_path")
        MODIFIED_SYSTEM_BAKS+=("$bak")
    fi
    sudo -n cp -p "${REPO_DIR}/${name}" "$system_path"
    sudo -n chown root:root "$system_path"
    sudo -n chmod 644 "$system_path"
    printf '  + installed %s → system\n' "$name"
}

apply_overlap_full() {
    local name="$1"
    local winner="${FULL_REPLACE_STRATEGY[$name]:-}"
    [[ -z "$winner" ]] && fail "No FULL_REPLACE_STRATEGY for $name" 4

    local system_path="${SYSTEM_DIR}/${name}"
    local repo_path="${REPO_DIR}/${name}"
    case "$winner" in
        system)
            # System wins — promote system → repo (overwrite repo).
            sudo -n cat "$system_path" > "$repo_path"
            printf '  * replaced repo/%s ← system (full)\n' "$name"
            ;;
        repo)
            # Repo wins — install repo → system (overwrite system, with .bak).
            local bak="${system_path}.pre-sync-${TIMESTAMP}.bak"
            sudo -n cp -p "$system_path" "$bak"
            MODIFIED_SYSTEM_FILES+=("$system_path")
            MODIFIED_SYSTEM_BAKS+=("$bak")
            sudo -n cp -p "$repo_path" "$system_path"
            sudo -n chown root:root "$system_path"
            sudo -n chmod 644 "$system_path"
            printf '  * replaced system/%s ← repo (full)\n' "$name"
            ;;
        *)
            fail "FULL_REPLACE_STRATEGY[$name]='$winner' invalid (must be 'repo' or 'system')" 4
            ;;
    esac
}

apply_overlap_hybrid() {
    local name="$1"
    local system_path="${SYSTEM_DIR}/${name}"
    local repo_path="${REPO_DIR}/${name}"
    local hybrid_out="/tmp/sync-hybrid-${name}"

    render_hybrid "$name" "$hybrid_out"

    printf '  * rendered hybrid %s → %s\n' "$name" "$hybrid_out"
    printf '    strategy: %s\n' "${HYBRID_MERGE_STRATEGY[$name]}"

    # Install hybrid to system (with .bak) AND promote to repo so both sides
    # converge to the same merged content. That way subsequent dry-runs show
    # IDENTICAL for this file.
    local bak="${system_path}.pre-sync-${TIMESTAMP}.bak"
    sudo -n cp -p "$system_path" "$bak"
    MODIFIED_SYSTEM_FILES+=("$system_path")
    MODIFIED_SYSTEM_BAKS+=("$bak")
    sudo -n cp -p "$hybrid_out" "$system_path"
    sudo -n chown root:root "$system_path"
    sudo -n chmod 644 "$system_path"
    cp -p "$hybrid_out" "$repo_path"
    printf '  + installed hybrid %s → system + repo\n' "$name"
}

rollback_system_changes() {
    printf '\nROLLBACK: reverting %d system files from .bak snapshots\n' \
        "${#MODIFIED_SYSTEM_FILES[@]}" >&2
    local i=0
    while (( i < ${#MODIFIED_SYSTEM_FILES[@]} )); do
        local f="${MODIFIED_SYSTEM_FILES[$i]}"
        local b="${MODIFIED_SYSTEM_BAKS[$i]}"
        if sudo -n test -f "$b"; then
            sudo -n cp -p "$b" "$f"
            printf '  reverted %s\n' "$f" >&2
        fi
        i=$((i + 1))
    done
}

# ------------------------------------------------------------------------------
# Main dispatch
# ------------------------------------------------------------------------------

# Compute the union of filenames across both sides.
collect_filenames() {
    { list_repo_confs; list_system_confs; } | sort -u
}

run_dry() {
    printf 'Supervisor config drift report (dry-run)\n'
    printf '=========================================\n'
    printf 'Repo:   %s\n' "$REPO_DIR"
    printf 'System: %s\n\n' "$SYSTEM_DIR"

    local -A counts=(
        [IDENTICAL]=0 [SYSTEM-ONLY]=0 [REPO-ONLY]=0
        [OVERLAP-DIFF-FULL]=0 [OVERLAP-DIFF-HYBRID]=0
    )
    printf '%-55s %s\n' 'File' 'Verdict'
    printf '%-55s %s\n' '----' '-------'
    while IFS= read -r name; do
        local verdict
        verdict="$(classify "$name")"
        printf '%-55s %s\n' "$name" "$verdict"
        counts["$verdict"]=$(( counts["$verdict"] + 1 ))
    done < <(collect_filenames)

    printf '\nSummary:\n'
    for key in IDENTICAL SYSTEM-ONLY REPO-ONLY OVERLAP-DIFF-FULL OVERLAP-DIFF-HYBRID; do
        printf '  %-22s %d\n' "$key" "${counts[$key]}"
    done

    local drift=$(( counts[SYSTEM-ONLY] + counts[REPO-ONLY] \
                  + counts[OVERLAP-DIFF-FULL] + counts[OVERLAP-DIFF-HYBRID] ))
    printf '\nTotal drift: %d files\n' "$drift"
}

run_apply() {
    printf 'Supervisor config reconciliation (apply)\n'
    printf '========================================\n'
    printf 'Reason: %s\n' "$REASON"
    printf 'Timestamp: %s\n\n' "$TIMESTAMP"

    # Pre-apply snapshot for changelog.
    local pre_status
    pre_status="$(sudo -n supervisorctl status 2>/dev/null || true)"

    local trap_err
    trap_err() {
        printf 'Apply failed.\n' >&2
        rollback_system_changes
        exit 4
    }
    trap trap_err ERR

    while IFS= read -r name; do
        local verdict
        verdict="$(classify "$name")"
        printf 'Processing %s → %s\n' "$name" "$verdict"

        case "$verdict" in
            IDENTICAL) : ;; # no-op
            SYSTEM-ONLY) apply_promote_to_repo "$name" ;;
            REPO-ONLY)   apply_install_to_system "$name" ;;
            OVERLAP-DIFF-FULL)
                if [[ -z "${ACCEPTED_RECONCILE[$name]:-}" ]]; then
                    printf 'REFUSE: %s is OVERLAP-DIFF-FULL; pass --accept-reconcile=%s to ack\n' \
                        "$name" "$name" >&2
                    rollback_system_changes
                    exit 3
                fi
                apply_overlap_full "$name"
                ;;
            OVERLAP-DIFF-HYBRID) apply_overlap_hybrid "$name" ;;
            *) fail "Unknown verdict '$verdict' for $name" 4 ;;
        esac
    done < <(collect_filenames)

    trap - ERR

    printf '\nReread + update supervisor:\n'
    sudo -n supervisorctl reread
    sudo -n supervisorctl update

    local post_status
    post_status="$(sudo -n supervisorctl status 2>/dev/null || true)"

    printf '\nPost-apply drift check:\n'
    local post_drift
    post_drift="$(run_dry_silent_drift_count)"
    printf '  remaining drift: %s\n' "$post_drift"

    write_changelog "$pre_status" "$post_status" "$post_drift"

    printf '\nApply complete.\n'
}

run_dry_silent_drift_count() {
    local count=0
    while IFS= read -r name; do
        local v
        v="$(classify "$name")"
        case "$v" in
            SYSTEM-ONLY|REPO-ONLY|OVERLAP-DIFF-FULL|OVERLAP-DIFF-HYBRID)
                count=$(( count + 1 )) ;;
        esac
    done < <(collect_filenames)
    echo "$count"
}

write_changelog() {
    local pre_status="$1"
    local post_status="$2"
    local post_drift="$3"

    if [[ ! -d "$CHANGELOG_DIR" ]]; then
        printf 'WARN: changelog dir %s not accessible; skipping append.\n' \
            "$CHANGELOG_DIR" >&2
        return 0
    fi

    {
        if [[ ! -f "$CHANGELOG_FILE" ]]; then
            printf '# Supervisor sync changelog — %s\n\n' "$(date '+%Y-%m-%d')"
            printf 'Append-only log of `scripts/supervisor/sync.sh --apply` runs.\n\n'
            printf '%s\n\n' '---'
        fi
        printf '## %s (%s)\n\n' "$(date '+%H:%M:%S')" "$TIMESTAMP"
        printf '**Reason:** %s\n\n' "$REASON"
        printf '**Touched files (%d):**\n\n' "${#MODIFIED_SYSTEM_FILES[@]}"
        local i=0
        while (( i < ${#MODIFIED_SYSTEM_FILES[@]} )); do
            # Leading `-` in printf format is parsed as option; use %s arg.
            printf '%s `%s` (.bak at `%s`)\n' \
                '-' "${MODIFIED_SYSTEM_FILES[$i]}" "${MODIFIED_SYSTEM_BAKS[$i]}"
            i=$((i + 1))
        done
        printf '\n**Post-apply drift:** %s file(s)\n\n' "$post_drift"
        printf '**Pre-apply supervisorctl status:**\n\n```\n%s\n```\n\n' "$pre_status"
        printf '**Post-apply supervisorctl status:**\n\n```\n%s\n```\n\n' "$post_status"
        printf '%s\n\n' '---'
    } >> "$CHANGELOG_FILE"

    printf 'Changelog appended to %s\n' "$CHANGELOG_FILE"
}

# ------------------------------------------------------------------------------

parse_args "$@"
preflight

case "$MODE" in
    dry-run) run_dry ;;
    apply)   run_apply ;;
esac
