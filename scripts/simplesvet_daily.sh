#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
NODE_BIN="${NODE_BIN:-$(command -v node || true)}"

if [[ -z "$NODE_BIN" ]]; then
  echo "Node.js nao encontrado no PATH" >&2
  exit 1
fi

cd "$ROOT_DIR/rpa"
EXIT_CODE=0

if "$NODE_BIN" simplesvet-reconcile.js; then
  "$NODE_BIN" simplesvet-worker.js || EXIT_CODE=$?
else
  EXIT_CODE=$?
fi

"$NODE_BIN" simplesvet-report.js || true
exit "$EXIT_CODE"
