#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DAILY_SCRIPT="$ROOT_DIR/scripts/simplesvet_daily.sh"
LOG_DIR="${SIMPLESVET_LOG_DIR:-$ROOT_DIR/logs}"
LOG_FILE="$LOG_DIR/simplesvet-cron.log"
START_MARKER="# BEGIN LISONTECH_SIMPLESVET"
END_MARKER="# END LISONTECH_SIMPLESVET"

if [[ ! -f "$DAILY_SCRIPT" ]]; then
  echo "Arquivo nao encontrado: $DAILY_SCRIPT" >&2
  exit 1
fi

chmod +x "$DAILY_SCRIPT"
mkdir -p "$LOG_DIR"
chmod 700 "$LOG_DIR"
CURRENT="$(crontab -l 2>/dev/null || true)"
CLEAN="$(printf '%s\n' "$CURRENT" | awk -v start="$START_MARKER" -v end="$END_MARKER" '
  $0 == start { skip=1; next }
  $0 == end { skip=0; next }
  !skip { print }
')"

{
  printf '%s\n' "$CLEAN"
  printf '%s\n' "$START_MARKER"
  printf '%s\n' 'CRON_TZ=America/Sao_Paulo'
  printf '0 3 * * * /usr/bin/flock -n /tmp/lisontech-simplesvet.lock %q >> %q 2>&1\n' "$DAILY_SCRIPT" "$LOG_FILE"
  printf '%s\n' "$END_MARKER"
} | crontab -

echo "Cron do SimplesVet instalado para 03:00 (America/Sao_Paulo)."
crontab -l
