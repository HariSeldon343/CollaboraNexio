#!/usr/bin/env bash
# =============================================================================
# CollaboraNexio — Cloudflare cache purge (bash)
#
# Usage:
#   tools/cloudflare_purge.sh                # purge default UI assets
#   tools/cloudflare_purge.sh --all          # purge entire zone
#   tools/cloudflare_purge.sh --dry-run      # show what would purge
#   tools/cloudflare_purge.sh url1 url2 ...  # purge specific URLs
#
# Auth (set in shell or in tools/.cloudflare.env):
#   CF_API_TOKEN=your-zone-cache-purge-token
#   CF_ZONE_ID=nexiosolution-zone-id
#
# Generate token at https://dash.cloudflare.com/profile/api-tokens with
# permission: Zone -> Cache Purge -> Purge.
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${ENV_FILE:-$SCRIPT_DIR/.cloudflare.env}"

# Load .cloudflare.env if present
if [[ -f "$ENV_FILE" ]]; then
    echo "[cf-purge] Loading $ENV_FILE" >&2
    # shellcheck disable=SC1090
    set -a; source "$ENV_FILE"; set +a
fi

: "${CF_API_TOKEN:?CF_API_TOKEN not set (env or tools/.cloudflare.env)}"
: "${CF_ZONE_ID:?CF_ZONE_ID not set (env or tools/.cloudflare.env)}"

ALL=false
DRY_RUN=false
FILES=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --all)     ALL=true; shift ;;
        --dry-run) DRY_RUN=true; shift ;;
        --help|-h)
            sed -n '2,/^# ===/p' "$0" | sed 's/^# \?//'
            exit 0
            ;;
        *) FILES+=("$1"); shift ;;
    esac
done

# Default URL list (UI redesign assets + page entry points)
DEFAULT_FILES=(
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/styles.css"
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/components.css"
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/dashboard.css"
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/filemanager.css"
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/filemanager_enhanced.css"
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/calendar.css"
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/shifts.css"
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/workflow.css"
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/sidebar-responsive.css"
    "https://app.nexiosolution.it/CollaboraNexio/assets/css/company_filter.css"
    "https://app.nexiosolution.it/CollaboraNexio/assets/js/app.js"
    "https://app.nexiosolution.it/CollaboraNexio/assets/js/company_filter.js"
    "https://app.nexiosolution.it/CollaboraNexio/dashboard.php"
    "https://app.nexiosolution.it/CollaboraNexio/files.php"
    "https://app.nexiosolution.it/CollaboraNexio/calendar.php"
    "https://app.nexiosolution.it/CollaboraNexio/tasks.php"
    "https://app.nexiosolution.it/CollaboraNexio/ticket.php"
    "https://app.nexiosolution.it/CollaboraNexio/turni.php"
    "https://app.nexiosolution.it/CollaboraNexio/aziende.php"
    "https://app.nexiosolution.it/CollaboraNexio/utenti.php"
    "https://app.nexiosolution.it/CollaboraNexio/audit_log.php"
    "https://app.nexiosolution.it/CollaboraNexio/configurazioni.php"
)

# Build JSON payload (no jq dependency — manual escape)
build_files_json() {
    local out="" first=true
    for u in "$@"; do
        local esc="${u//\\/\\\\}"; esc="${esc//\"/\\\"}"
        if $first; then first=false; else out+=","; fi
        out+="\"$esc\""
    done
    echo "{\"files\":[$out]}"
}

if $ALL; then
    PAYLOAD='{"purge_everything":true}'
    ACTION="PURGE EVERYTHING (entire zone)"
else
    if [[ ${#FILES[@]} -eq 0 ]]; then FILES=("${DEFAULT_FILES[@]}"); fi
    PAYLOAD="$(build_files_json "${FILES[@]}")"
    ACTION="Purge ${#FILES[@]} URLs"
fi

echo "[cf-purge] Zone: $CF_ZONE_ID" >&2
echo "[cf-purge] Action: $ACTION" >&2

if $DRY_RUN; then
    echo "[cf-purge] DRY RUN — payload:" >&2
    echo "$PAYLOAD"
    exit 0
fi

URL="https://api.cloudflare.com/client/v4/zones/${CF_ZONE_ID}/purge_cache"

resp="$(curl -fsS -X POST "$URL" \
    -H "Authorization: Bearer $CF_API_TOKEN" \
    -H "Content-Type: application/json" \
    --data "$PAYLOAD" 2>&1)" || {
    echo "[cf-purge] HTTP error:" >&2
    echo "$resp" >&2
    exit 1
}

# Light parse without jq
if echo "$resp" | grep -q '"success":true'; then
    purge_id="$(echo "$resp" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)"
    echo "[cf-purge] OK — purge id: $purge_id"
    echo "[cf-purge] Edge propagation typically completes in ~30s." >&2
    exit 0
else
    echo "[cf-purge] FAILED:" >&2
    echo "$resp" >&2
    exit 1
fi
