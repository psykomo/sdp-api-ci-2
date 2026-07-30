#!/usr/bin/env bash
# Manual REST API helper for sdp-api-ci-2
#
# Prerequisites:
#   php spark serve --port 8080   # or match BASE_URL
#   ./scripts/api.sh login
#
# Demo user (DemoAuthSeeder on db_sdp):
#   email:    operator@sdp.local
#   password: password
#
# Org ids after seed on MariaDB (codes map to legacy ID_UPT for units):
#   Look up: docker exec sdp-mariadb mariadb -usdp -psdp_local db_sdp \
#     -e "SELECT id, code, name FROM organizations"
#   Typical: KW-DKI (all UPT), 093, 604, …
#
# R0 / R1 examples:
#   ./scripts/api.sh login
#   ORG_ID=1 ./scripts/api.sh wbp
#   ORG_ID=1 ./scripts/api.sh wbp-show 571202001150013
#   ORG_ID=1 ./scripts/api.sh referensi jenis-registrasi
#   ORG_ID=1 ./scripts/api.sh get /api/v1/referensi/lookups?group=Agama
#   ORG_ID=1 ./scripts/api.sh post /api/v1/auth/login '{"email":"operator@sdp.local","password":"password"}'
#
# Override:
#   BASE_URL=http://localhost:8080 ORG_ID=1 ./scripts/api.sh wbp

set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8082}"
EMAIL="${EMAIL:-operator@sdp.local}"
PASSWORD="${PASSWORD:-password}"
ORG_ID="${ORG_ID:-1}"
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

urlencode() {
  python3 -c "import urllib.parse,sys; print(urllib.parse.quote(sys.argv[1], safe=''))" "$1" 2>/dev/null \
    || echo "$1"
}

# Perform HTTP request; print JSON body; exit non-zero on HTTP >= 400.
# Usage: request METHOD URL [curl -H / -d args...]
request() {
  local method="$1" url="$2"
  shift 2
  local tmp code body
  tmp="$(mktemp)"
  code="$(curl -sS -o "$tmp" -w "%{http_code}" -X "$method" "$url" "$@" || true)"

  if [[ -z "$code" || "$code" == "000" ]]; then
    echo "Request failed — is the API running at ${BASE_URL} ?" >&2
    echo "Start it with: php spark serve --host 127.0.0.1 --port ${BASE_URL##*:}" >&2
    rm -f "$tmp"
    exit 1
  fi

  echo "HTTP ${code}" >&2
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

# Resolve path: allow "api/v1/foo" or "/api/v1/foo" or full URL
resolve_url() {
  local path="$1"
  if [[ "$path" == http://* || "$path" == https://* ]]; then
    echo "$path"
  elif [[ "$path" == /* ]]; then
    echo "${BASE_URL}${path}"
  else
    echo "${BASE_URL}/${path}"
  fi
}

# ---------------------------------------------------------------------------
# Generic GET / POST (authenticated when token exists; pass --public for none)
# ---------------------------------------------------------------------------

cmd_get() {
  local path="${1:-}"
  if [[ -z "$path" ]]; then
    echo "Usage: $0 get <path> [query...]" >&2
    echo "  $0 get /api/v1/wbp" >&2
    echo "  $0 get /api/v1/referensi/lookups?group=Agama" >&2
    exit 1
  fi
  shift || true

  local url
  url="$(resolve_url "$path")"
  # append extra query pieces if any: get /api/v1/wbp search=foo
  if [[ $# -gt 0 ]]; then
    local q
    q="$(IFS='&'; echo "$*")"
    if [[ "$url" == *\?* ]]; then
      url="${url}&${q}"
    else
      url="${url}?${q}"
    fi
  fi

  auth_headers
  echo "GET ${url}  (X-Org-Id: ${ORG_ID})" >&2
  request GET "$url" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}"
}

cmd_post() {
  local path="${1:-}"
  local body="${2:-}"
  if [[ -z "$path" ]]; then
    echo "Usage: $0 post <path> [json-body]" >&2
    echo "  $0 post /api/v1/auth/login '{\"email\":\"operator@sdp.local\",\"password\":\"password\"}'" >&2
    echo "  $0 post /api/v1/wbp '{\"nama_lengkap\":\"…\"}'" >&2
    exit 1
  fi

  local url
  url="$(resolve_url "$path")"

  # Login path does not need token
  if [[ "$path" == *"/auth/login"* ]]; then
    echo "POST ${url}" >&2
    if [[ -n "$body" ]]; then
      request POST "$url" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d "$body"
    else
      request POST "$url" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d "{\"email\":\"${EMAIL}\",\"password\":\"${PASSWORD}\"}"
    fi
    return
  fi

  auth_headers
  if [[ -z "$body" ]]; then
    body="{}"
  fi
  echo "POST ${url}  (X-Org-Id: ${ORG_ID})" >&2
  request POST "$url" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}" \
    -d "$body"
}

cmd_put() {
  local path="${1:-}"
  local body="${2:-}"
  if [[ -z "$path" ]]; then
    echo "Usage: $0 put <path> [json-body]" >&2
    echo "  $0 put /api/v1/wbp/registrasi/<ID_PERKARA> '{\"keterangan\":\"…\"}'" >&2
    exit 1
  fi
  auth_headers
  if [[ -z "$body" ]]; then
    body="{}"
  fi
  local url
  url="$(resolve_url "$path")"
  echo "PUT ${url}  (X-Org-Id: ${ORG_ID})" >&2
  request PUT "$url" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}" \
    -d "$body"
}

# ---------------------------------------------------------------------------
# Core
# ---------------------------------------------------------------------------

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
  echo "Next: ORG_ID=${ORG_ID} $0 wbp" >&2
  echo "      ORG_ID=${ORG_ID} $0 referensi jenis-registrasi" >&2
  echo "Using ORG_ID=${ORG_ID}  BASE_URL=${BASE_URL}" >&2
}

# ---------------------------------------------------------------------------
# R1 Wbp (legacy identitas)
# ---------------------------------------------------------------------------

cmd_wbp() {
  auth_headers
  SEARCH="${1:-}"
  URL="${BASE_URL}/api/v1/wbp?perPage=20"
  if [[ -n "$SEARCH" ]]; then
    URL="${URL}&search=$(urlencode "$SEARCH")"
  fi

  echo "GET ${URL}  (X-Org-Id: ${ORG_ID})" >&2
  request GET "$URL" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}"
}

cmd_wbp_show() {
  if [[ -z "${1:-}" ]]; then
    echo "Usage: $0 wbp-show <NOMOR_INDUK>" >&2
    echo "  $0 wbp-show 571202001150013" >&2
    exit 1
  fi
  auth_headers
  local id
  id="$(urlencode "$1")"
  echo "GET ${BASE_URL}/api/v1/wbp/${id}  (X-Org-Id: ${ORG_ID})" >&2
  request GET "${BASE_URL}/api/v1/wbp/${id}" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}"
}

cmd_wbp_create() {
  auth_headers
  local name="${1:-Test WBP $(date +%H%M%S)}"
  local extra="${2:-}"
  local body
  body="$(printf '{"nama_lengkap":"%s","id_jenis_kelamin":"L","alamat":"Alamat uji API","nik":"3174%010d"}' \
    "$name" "$((RANDOM % 1000000000))")"
  if [[ -n "$extra" ]]; then
    body="$extra"
  fi
  echo "POST ${BASE_URL}/api/v1/wbp  (X-Org-Id: ${ORG_ID})" >&2
  request POST "${BASE_URL}/api/v1/wbp" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}" \
    -d "$body"
}

cmd_wbp_update() {
  auth_headers
  if [[ -z "${1:-}" ]]; then
    echo "Usage: $0 wbp-update <NOMOR_INDUK> [nama_lengkap]" >&2
    exit 1
  fi
  local id name body
  id="$(urlencode "$1")"
  name="${2:-Updated via api.sh}"
  body="$(printf '{"nama_lengkap":"%s"}' "$name")"
  echo "PUT ${BASE_URL}/api/v1/wbp/${id}" >&2
  request PUT "${BASE_URL}/api/v1/wbp/${id}" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}" \
    -d "$body"
}

cmd_wbp_delete() {
  auth_headers
  if [[ -z "${1:-}" ]]; then
    echo "Usage: $0 wbp-delete <NOMOR_INDUK>" >&2
    exit 1
  fi
  local id
  id="$(urlencode "$1")"
  echo "DELETE ${BASE_URL}/api/v1/wbp/${id}" >&2
  request DELETE "${BASE_URL}/api/v1/wbp/${id}" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}"
}

# R3 registrasi create
# Usage: registrasi <NOMOR_INDUK> [ID_REG] [JSON extra overrides via env BODY=...]
cmd_registrasi() {
  auth_headers
  if [[ -z "${1:-}" ]]; then
    echo "Usage: $0 registrasi <NOMOR_INDUK> [ID_REG=BI]" >&2
    echo "  Or: BODY='{...full json...}' $0 registrasi" >&2
    exit 1
  fi
  local ni reg body
  if [[ -n "${BODY:-}" ]]; then
    body="$BODY"
  else
    ni="$1"
    reg="${2:-BI}"
    body="$(cat <<EOF
{
  "nomor_induk": "${ni}",
  "id_reg": "${reg}",
  "id_status": "STA",
  "id_sub_status": "SSA1",
  "nmr_reg_gol": "${reg}.API/$(date +%Y)",
  "tgl_msk_lapas": "$(date +%Y-%m-%d)",
  "kejahatan": [
    {
      "pasal_utama": "DUMMY-PASAL",
      "uu_kejahatan": "UU Test",
      "is_kejahatan_utama": 1
    }
  ],
  "hukuman": {
    "id_jenis_hukuman": "PID",
    "thn_kurung": 1,
    "bln_kurung": 0,
    "hr_kurung": 0,
    "tgl_putusan": "$(date +%Y-%m-%d)",
    "nmr_putusan": "API-TEST/1"
  }
}
EOF
)"
  fi
  echo "POST ${BASE_URL}/api/v1/wbp/registrasi  (X-Org-Id: ${ORG_ID})" >&2
  request POST "${BASE_URL}/api/v1/wbp/registrasi" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}" \
    -d "$body"
}

# R6 list / show registrasi
cmd_registrasi_list() {
  auth_headers
  local search="${1:-}"
  local url="${BASE_URL}/api/v1/wbp/registrasi?perPage=20"
  if [[ -n "$search" ]]; then
    url="${url}&search=$(urlencode "$search")"
  fi
  echo "GET ${url}  (X-Org-Id: ${ORG_ID})" >&2
  request GET "$url" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}"
}

cmd_registrasi_show() {
  if [[ -z "${1:-}" ]]; then
    echo "Usage: $0 registrasi-show <ID_PERKARA>" >&2
    exit 1
  fi
  auth_headers
  local id
  id="$(urlencode "$1")"
  echo "GET ${BASE_URL}/api/v1/wbp/registrasi/${id}  (X-Org-Id: ${ORG_ID})" >&2
  request GET "${BASE_URL}/api/v1/wbp/registrasi/${id}" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}"
}

# R4 update registrasi
# Usage: registrasi-update <ID_PERKARA> [keterangan]
#    or: BODY='{...}' $0 registrasi-update <ID_PERKARA>
cmd_registrasi_update() {
  auth_headers
  if [[ -z "${1:-}" ]]; then
    echo "Usage: $0 registrasi-update <ID_PERKARA> [keterangan]" >&2
    echo "  Or: BODY='{\"nmr_reg_gol\":\"…\"}' $0 registrasi-update <ID_PERKARA>" >&2
    exit 1
  fi
  local id body
  id="$(urlencode "$1")"
  if [[ -n "${BODY:-}" ]]; then
    body="$BODY"
  else
    local ket="${2:-Updated via api.sh R4}"
    body="$(printf '{"keterangan":"%s"}' "$ket")"
  fi
  echo "PUT ${BASE_URL}/api/v1/wbp/registrasi/${id}  (X-Org-Id: ${ORG_ID})" >&2
  request PUT "${BASE_URL}/api/v1/wbp/registrasi/${id}" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}" \
    -d "$body"
}

# Aliases for old names
cmd_inmates() { cmd_wbp "$@"; }
cmd_inmate() {
  if [[ -z "${1:-}" ]]; then
    cmd_wbp
    return
  fi
  cmd_wbp_show "$@"
}

# ---------------------------------------------------------------------------
# R0 Referensi
# ---------------------------------------------------------------------------

cmd_referensi() {
  auth_headers
  local sub="${1:-}"
  shift || true

  case "$sub" in
    jenis-registrasi|jenis)
      local qs=""
      if [[ "${1:-}" == "all" ]]; then qs="?active=0"; fi
      if [[ "${1:-}" == "tahanan" ]]; then qs="?is_tahanan=1"; fi
      if [[ "${1:-}" == "napi" ]]; then qs="?is_tahanan=0"; fi
      echo "GET ${BASE_URL}/api/v1/referensi/jenis-registrasi${qs}" >&2
      request GET "${BASE_URL}/api/v1/referensi/jenis-registrasi${qs}" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${TOKEN}" \
        -H "X-Org-Id: ${ORG_ID}"
      ;;
    groups)
      echo "GET ${BASE_URL}/api/v1/referensi/groups" >&2
      request GET "${BASE_URL}/api/v1/referensi/groups" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${TOKEN}" \
        -H "X-Org-Id: ${ORG_ID}"
      ;;
    lookups)
      local group="${1:-}"
      if [[ -z "$group" ]]; then
        echo "Usage: $0 referensi lookups <group>" >&2
        echo "  $0 referensi lookups Agama" >&2
        exit 1
      fi
      echo "GET ${BASE_URL}/api/v1/referensi/lookups?group=$(urlencode "$group")" >&2
      request GET "${BASE_URL}/api/v1/referensi/lookups?group=$(urlencode "$group")" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${TOKEN}" \
        -H "X-Org-Id: ${ORG_ID}"
      ;;
    lookup)
      if [[ -z "${1:-}" ]]; then
        echo "Usage: $0 referensi lookup <ID_LOOKUP>" >&2
        exit 1
      fi
      echo "GET ${BASE_URL}/api/v1/referensi/lookups/$(urlencode "$1")" >&2
      request GET "${BASE_URL}/api/v1/referensi/lookups/$(urlencode "$1")" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${TOKEN}" \
        -H "X-Org-Id: ${ORG_ID}"
      ;;
    upt)
      local search="${1:-}"
      local url="${BASE_URL}/api/v1/referensi/upt"
      if [[ -n "$search" ]]; then
        url="${url}?search=$(urlencode "$search")"
      fi
      echo "GET ${url}" >&2
      request GET "$url" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${TOKEN}" \
        -H "X-Org-Id: ${ORG_ID}"
      ;;
    ""|help)
      cat <<EOF
Usage: $0 referensi <subcommand>

  jenis-registrasi [all|tahanan|napi]
  groups
  lookups <group>          e.g. Agama
  lookup <ID_LOOKUP>       e.g. ISM
  upt [search]
EOF
      ;;
    *)
      echo "Unknown referensi subcommand: $sub" >&2
      exit 1
      ;;
  esac
}

# ---------------------------------------------------------------------------
# Other
# ---------------------------------------------------------------------------

cmd_users() {
  auth_headers
  echo "GET ${BASE_URL}/api/v1/users  (X-Org-Id: ${ORG_ID})" >&2
  request GET "${BASE_URL}/api/v1/users?perPage=20" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "X-Org-Id: ${ORG_ID}"
}

cmd_flow() {
  cmd_login
  echo "" >&2
  cmd_referensi jenis-registrasi
  echo "" >&2
  cmd_wbp
}

usage() {
  cat <<EOF
Usage: $0 <command> [args]

Auth / health:
  ping                          Health check (no auth)
  login                         Login and save Bearer token

Generic HTTP (after login, unless path is auth/login):
  get  <path> [k=v…]            Authenticated GET
  post <path> [json-body]       Authenticated POST (login path is public)
  put  <path> [json-body]       Authenticated PUT

R1/R2 Wbp (legacy identitas):
  wbp [search]                  List WBP
  wbp-show <NOMOR_INDUK>        Show one WBP + perkara
  wbp-create [nama]             Create identitas (needs unit ORG_ID e.g. 093)
  wbp-update <NOMOR_INDUK> [nama]
  wbp-delete <NOMOR_INDUK>      Soft-delete (no active perkara)
  registrasi <NOMOR_INDUK> [ID_REG]   R3 create perkara+history+kejahatan+hukuman
  registrasi-list [search]            R6 list active perkara
  registrasi-show <ID_PERKARA>        R6 show detail
  registrasi-update <ID_PERKARA> […]  R4 edit (or BODY=json)
  inmates / inmate …            Aliases for wbp / wbp-show

R0 Referensi:
  referensi jenis-registrasi [all|tahanan|napi]
  referensi groups
  referensi lookups <group>
  referensi lookup <ID_LOOKUP>
  referensi upt [search]

Other:
  users                         List API users in org
  flow                          login → jenis-registrasi → wbp list

Environment:
  BASE_URL     default http://localhost:8080
  EMAIL        default operator@sdp.local
  PASSWORD     default password
  ORG_ID       default 1 (often KW-DKI after seed — check organizations table)
  TOKEN_FILE   default /tmp/sdp-api-token

Examples:
  $0 login
  ORG_ID=1 $0 wbp
  ORG_ID=1 $0 wbp-show 571202001150013
  ORG_ID=1 $0 referensi lookups Agama
  ORG_ID=1 $0 get /api/v1/referensi/jenis-registrasi
  ORG_ID=1 $0 post /api/v1/wbp '{}'
  $0 flow
EOF
}

main() {
  CMD="${1:-}"
  shift || true

  case "$CMD" in
    ping)             cmd_ping "$@" ;;
    login)            cmd_login "$@" ;;
    get)              cmd_get "$@" ;;
    post)             cmd_post "$@" ;;
    put)              cmd_put "$@" ;;
    wbp)              cmd_wbp "$@" ;;
    wbp-show)         cmd_wbp_show "$@" ;;
    wbp-create|create-wbp|create-inmate) cmd_wbp_create "$@" ;;
    wbp-update|update-wbp|update-inmate) cmd_wbp_update "$@" ;;
    wbp-delete|delete-wbp|delete-inmate) cmd_wbp_delete "$@" ;;
    registrasi|reg)   cmd_registrasi "$@" ;;
    registrasi-list|reg-list) cmd_registrasi_list "$@" ;;
    registrasi-show|reg-show) cmd_registrasi_show "$@" ;;
    registrasi-update|reg-update) cmd_registrasi_update "$@" ;;
    inmates)          cmd_inmates "$@" ;;
    inmate)           cmd_inmate "$@" ;;
    referensi|ref)    cmd_referensi "$@" ;;
    users)            cmd_users "$@" ;;
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
