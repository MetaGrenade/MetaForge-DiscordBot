#!/usr/bin/env bash
# Validate MetaForge Discord companion env before running Laravel or the gateway bot.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODE="${1:-all}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

errors=0
warns=0

fail() {
  echo -e "${RED}✗${NC} $1" >&2
  errors=$((errors + 1))
}

warn() {
  echo -e "${YELLOW}!${NC} $1" >&2
  warns=$((warns + 1))
}

ok() {
  echo -e "${GREEN}✓${NC} $1"
}

require_var() {
  local name="$1"
  local value="$2"
  if [[ -z "${value// }" ]]; then
    fail "${name} is empty"
  else
    ok "${name} is set"
  fi
}

load_dotenv() {
  local file="$1"
  if [[ ! -f "$file" ]]; then
    fail "Missing ${file} (copy from ${file}.example)"
    return 1
  fi
  set -a
  # shellcheck disable=SC1090
  source "$file"
  set +a
  ok "Loaded ${file}"
}

check_bot_env() {
  echo "--- Bot (bot/.env) ---"
  local file="${ROOT}/bot/.env"
  if ! load_dotenv "$file"; then
    return
  fi

  require_var DISCORD_BOT_TOKEN "${DISCORD_BOT_TOKEN:-}"
  require_var DISCORD_GUILD_ID "${DISCORD_GUILD_ID:-}"
  require_var DISCORD_APPLICATION_ID "${DISCORD_APPLICATION_ID:-}"
  require_var METAFORGE_WEBHOOK_URL "${METAFORGE_WEBHOOK_URL:-}"
  require_var METAFORGE_WEBHOOK_SECRET "${METAFORGE_WEBHOOK_SECRET:-}"

  if [[ -n "${METAFORGE_WEBHOOK_URL:-}" && "${METAFORGE_WEBHOOK_URL}" == *"/discord"* ]]; then
    warn "METAFORGE_WEBHOOK_URL should be the app origin only (no /discord/... path)"
  fi

  if [[ -n "${DISCORD_GUILD_ID:-}" && ! "${DISCORD_GUILD_ID}" =~ ^[0-9]+$ ]]; then
    fail "DISCORD_GUILD_ID should be a numeric snowflake"
  fi

  if command -v node >/dev/null 2>&1; then
    if [[ -d "${ROOT}/bot/node_modules" ]]; then
      ok "bot/node_modules present"
    else
      warn "Run: cd bot && npm install"
    fi
    if node -e "const v=process.versions.node.split('.').map(Number); process.exit(v[0]>=20?0:1)" 2>/dev/null; then
      ok "Node $(node -v) (>=20)"
    else
      fail "Node 20+ required (found $(node -v 2>/dev/null || echo missing))"
    fi
  else
    warn "Node not in PATH — skipped runtime check"
  fi
}

check_laravel_env() {
  echo "--- Laravel package (laravel/.env or host MetaForge .env) ---"
  local file="${ROOT}/laravel/.env"
  if [[ ! -f "$file" ]]; then
    warn "No ${file} — checking MetaForge app .env is expected in production"
    return
  fi
  load_dotenv "$file"
  require_var DISCORD_BOT_TOKEN "${DISCORD_BOT_TOKEN:-}"
  require_var DISCORD_GUILD_ID "${DISCORD_GUILD_ID:-}"
  require_var DISCORD_BOT_SHARED_SECRET "${DISCORD_BOT_SHARED_SECRET:-}"

  if [[ -n "${DISCORD_BOT_SHARED_SECRET:-}" && ${#DISCORD_BOT_SHARED_SECRET} -lt 32 ]]; then
    warn "DISCORD_BOT_SHARED_SECRET should be at least 32 characters"
  fi
}

echo "MetaForge Discord setup check (mode: ${MODE})"
echo "Repo: ${ROOT}"
echo

case "$MODE" in
  bot) check_bot_env ;;
  laravel) check_laravel_env ;;
  all)
    check_laravel_env
    echo
    check_bot_env
    ;;
  *)
    echo "Usage: $0 [all|bot|laravel]" >&2
    exit 2
    ;;
esac

echo
if [[ "$errors" -gt 0 ]]; then
  echo -e "${RED}${errors} error(s)${NC}, ${warns} warning(s). Fix env before starting services."
  exit 1
fi

echo -e "${GREEN}Env checks passed.${NC} ${warns} warning(s)."
exit 0
