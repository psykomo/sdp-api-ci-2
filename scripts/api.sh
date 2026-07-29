#!/usr/bin/env bash
# Manual REST API helper for sdp-api-ci-2
#
# Prerequisites:
#   php spark serve --port 8082   # match BASE_URL below
#   ./scripts/api.sh login
#   ./scripts/api.sh inmates      # list (note the "s")
#   ./scripts/api.sh inmate 1     # show one by id
#
# Demo user (from DemoAuthSeeder):
#   email:    operator@sdp.local
#   password: password
# Org ids (single topology SQLite seed):
#   1 = KW-DKI,  2 = LP-CIPINANG,  3 = RT-SALEMBA
#
# Override defaults:
#   BASE_URL=http://localhost:8082 EMAIL=... PASSWORD=... ORG_ID=2 ./scripts/api.sh login
#   ORG_ID=3 ./scripts/api.sh inmates

set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8082}"
EMAIL="${EMAIL:-operator@sdp.local}"
PASSWORD="${PASSWORD:-password}"
ORG_ID="${ORG_ID:-2}"
TOKEN_FILE="${TOKEN_FILE:-/tmp/sdp-api-token}"

pretty() {
  local body="$1"
  if [[ -z "$body" ]]; then
    echo "(empty response body)" >&2
    return 0
  fi
  if command -v jq >/dev/null 2>&1; then
    echo "$body" | jq .
  else
    echo "$body"
  fi
}

# Perform HTTP request; print JSON body; exit non-zero on curl/network failure.
# Usage: request METHOD URL [curl -H / -d args...]
request() {
  local method="$1" url="$2"
  shift 2
  local tmp code
  tmp="$(mktemp)"
  code="$(curl -sS -o "$tmp" -w "%{http_code}" -X "$method" "$url" "$@" || true)"

  if [[ -z "$code" || "$code" == "000" ]]; then
    echo "Request failed — is the API running at ${BASE_URL} ?" >&2
    echo "Start it with: php spark serve --port ${BASE_URL##*:}" >&2
    rm -f "$tmp"
    exit 1
  fi

  echo "HTTP ${code}" >&2
  local body
  body="$(cat "$tmp")"
  rm -f "$tmp"
  pretty "$body"

  if [[ "$code" -ge 400 ]]; then
    return 1
  fi
}

auth_headers() {
  if [[ ! -f "$TOKEN_FILE" ]]; then
    echo "No token yet. Run: $0 login" >&2
    exit 1
  fi
  TOKEN="$(tr -d '\n\r' <"$TOKEN_FILE")"
  if [[ -z "$TOKEN" || "$TOKEN" == "null" ]]; then
    echo "Token file is empty. Run: $0 login" >&2
    exit 1
  fi
}

cmd_ping() {
  echo "GET ${BASE_URL}/api/v1/ping" >&2
  request GET "${BASE_URL}/api/v1/ping" -H "Accept: application/json"
}

cmd_login() {
  echo "POST ${BASE_URL}/api/v1/auth/login  (${EMAIL})" >&2
  local tmp code RESP
  tmp="$(mktemp)"
  code="$(curl -sS -o "$tmp" -w "%{http_code}" -X POST "${BASE_URL}/api/v1/auth/login" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "{\"email\":\"${EMAIL}\",\"password\":\"${PASSWORD}\"}" || true)"
  RESP="$(cat "$tmp")"
  rm -f "$tmp"

  if [[ -z "$code" || "$code" == "000" ]]; then
    echo "Login failed — is the API running at ${BASE_URL} ?" >&2
    exit 1
  fi

  echo "HTTP ${code}" >&2
  pretty "$RESP"

  if command -v jq >/dev/null 2>&1; then
    TOKEN="$(echo "$RESP" | jq -r '.data.token // empty')"
  else
    TOKEN="$(echo "$RESP" | sed -n 's/.*"token"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -1)"
  fi

  if [[ -z "$TOKEN" ]]; then
    echo "Login failed — no token in response." >&2
    exit 1
  fi

  printf '%s' "$TOKEN" >"$TOKEN_FILE"
  echo "" >&2
  echo "Token saved to ${TOKEN_FILE}" >&2
  echo "Next: $0 inmates     # list  |  $0 inmate <id>  # show one" >&2
  echo "Using ORG_ID=${ORG_ID}  BASE_URL=${BASE_URL}" >&2
}

cmd_inmates() {
  auth_headers
  SEARCH="${1:-}"
  URL="${BASE_URL}/api/v1/inmates?perPage=20"
  if [[ -n "$SEARCH" ]]; then
    URL="${URL}&search=$(python3 -c "import urllib.parse,sys; print(urllib.parse.quote(sys.argv[1]))" "$SEARCH" 2>/dev/null || echo "$SEARCH")"
  fi

  echo "GET ${URL}  (X-Org-Id: ${ORG_ID})" >&2
  request GET "$URL" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}"
}

cmd_inmate() {
  # No id → list (people often type "inmate" when they mean list)
  if [[ -z "${1:-}" ]]; then
    echo "Note: 'inmate' without id lists all. Use: $0 inmate <id> for one." >&2
    cmd_inmates
    return
  fi

  auth_headers
  ID="$1"
  echo "GET ${BASE_URL}/api/v1/inmates/${ID}  (X-Org-Id: ${ORG_ID})" >&2
  request GET "${BASE_URL}/api/v1/inmates/${ID}" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}"
}

cmd_create_inmate() {
  auth_headers
  REG="${1:-REG-$(date +%s)}"
  NAME="${2:-Budi Santoso}"

  echo "POST ${BASE_URL}/api/v1/inmates  reg=${REG}" >&2
  request POST "${BASE_URL}/api/v1/inmates" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}" \
    -d "{
      \"registration_number\": \"${REG}\",
      \"full_name\": \"${NAME}\",
      \"gender\": \"L\"
    }"
}

cmd_update_inmate() {
  auth_headers
  if [[ -z "${1:-}" ]]; then
    echo "Usage: $0 update-inmate <id> [full_name]" >&2
    exit 1
  fi
  ID="$1"
  NAME="${2:-Updated Name}"

  echo "PUT ${BASE_URL}/api/v1/inmates/${ID}" >&2
  request PUT "${BASE_URL}/api/v1/inmates/${ID}" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}" \
    -d "{\"full_name\": \"${NAME}\"}"
}

cmd_delete_inmate() {
  auth_headers
  if [[ -z "${1:-}" ]]; then
    echo "Usage: $0 delete-inmate <id>" >&2
    exit 1
  fi
  ID="$1"
  echo "DELETE ${BASE_URL}/api/v1/inmates/${ID}" >&2
  request DELETE "${BASE_URL}/api/v1/inmates/${ID}" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}"
}

cmd_release_inmate() {
  auth_headers
  if [[ -z "${1:-}" ]]; then
    echo "Usage: $0 release-inmate <id>" >&2
    exit 1
  fi
  ID="$1"
  TODAY="$(date +%Y-%m-%d)"

  echo "POST ${BASE_URL}/api/v1/inmates/${ID}/releases" >&2
  request POST "${BASE_URL}/api/v1/inmates/${ID}/releases" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}" \
    -d "{
      \"release_type\": \"bebas_murni\",
      \"release_date\": \"${TODAY}\",
      \"notes\": \"Manual API test release\"
    }"
}

cmd_transfer_inmate() {
  auth_headers
  if [[ -z "${1:-}" ]]; then
    echo "Usage: $0 transfer-inmate <id> [to_org_id]" >&2
    exit 1
  fi
  ID="$1"
  TO_ORG="${2:-3}"
  NOW="$(date '+%Y-%m-%d %H:%M:%S')"

  echo "POST ${BASE_URL}/api/v1/inmates/${ID}/transfers  → org ${TO_ORG}" >&2
  request POST "${BASE_URL}/api/v1/inmates/${ID}/transfers" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}" \
    -d "{
      \"to_organization_id\": ${TO_ORG},
      \"reason\": \"Manual API test transfer\",
      \"transferred_at\": \"${NOW}\"
    }"
}

cmd_users() {
  auth_headers
  echo "GET ${BASE_URL}/api/v1/users  (X-Org-Id: ${ORG_ID})" >&2
  request GET "${BASE_URL}/api/v1/users?perPage=20" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}"
}

cmd_visits() {
  auth_headers
  echo "GET ${BASE_URL}/api/v1/visits  (X-Org-Id: ${ORG_ID})" >&2
  request GET "${BASE_URL}/api/v1/visits?perPage=20" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}"
}

cmd_create_visit() {
  auth_headers
  INMATE_ID="${1:-}"
  NOW="$(date '+%Y-%m-%d %H:%M:%S')"
  BODY="\"visitor_name\": \"Siti Aminah\", \"visited_at\": \"${NOW}\", \"status\": \"scheduled\""
  if [[ -n "$INMATE_ID" ]]; then
    BODY="\"inmate_id\": ${INMATE_ID}, ${BODY}"
  fi

  echo "POST ${BASE_URL}/api/v1/visits" >&2
  request POST "${BASE_URL}/api/v1/visits" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}" \
    -d "{${BODY}}"
}

cmd_flow() {
  # One-shot: login → create inmate → list inmates
  cmd_login
  echo "" >&2
  cmd_create_inmate "REG-DEMO-$(date +%s)" "Demo WBP"
  echo "" >&2
  cmd_inmates
}

usage() {
  cat <<EOF
Usage: $0 <command> [args]

Commands:
  ping                          Health check (no auth)
  login                         Login and save Bearer token
  inmates [search]              List inmates  ← use this after login
  inmate [id]                   Show one inmate (no id = same as list)
  create-inmate [reg] [name]    Create inmate
  update-inmate <id> [name]     Update inmate name
  delete-inmate <id>            Delete inmate
  release-inmate <id>           Release inmate (bebas_murni)
  transfer-inmate <id> [to]     Transfer inmate (default to org 3)
  users                         List users in org
  visits                        List visits
  create-visit [inmate_id]      Create visit
  flow                          login → create inmate → list

Environment:
  BASE_URL     default http://localhost:8082
  EMAIL        default operator@sdp.local
  PASSWORD     default password
  ORG_ID       default 2 (LP-CIPINANG)
  TOKEN_FILE   default /tmp/sdp-api-token

Examples:
  $0 login
  $0 inmates
  $0 create-inmate REG-001 "Budi Santoso"
  $0 inmate 1
  ORG_ID=3 $0 inmates
  $0 flow
EOF
}

main() {
  CMD="${1:-}"
  shift || true

  case "$CMD" in
    ping)             cmd_ping "$@" ;;
    login)            cmd_login "$@" ;;
    inmates)          cmd_inmates "$@" ;;
    inmate)           cmd_inmate "$@" ;;
    create-inmate)    cmd_create_inmate "$@" ;;
    update-inmate)    cmd_update_inmate "$@" ;;
    delete-inmate)    cmd_delete_inmate "$@" ;;
    release-inmate)   cmd_release_inmate "$@" ;;
    transfer-inmate)  cmd_transfer_inmate "$@" ;;
    users)            cmd_users "$@" ;;
    visits)           cmd_visits "$@" ;;
    create-visit)     cmd_create_visit "$@" ;;
    flow)             cmd_flow "$@" ;;
    -h|--help|help|"") usage ;;
    *)
      echo "Unknown command: $CMD" >&2
      usage >&2
      exit 1
      ;;
  esac
}

main "$@"
