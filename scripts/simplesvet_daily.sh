#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
NODE_BIN="${NODE_BIN:-$(command -v node || true)}"

if [[ -z "$NODE_BIN" ]]; then
  echo "Node.js nao encontrado no PATH" >&2
  exit 1
fi

cd "$ROOT_DIR/rpa"
"$NODE_BIN" simplesvet-reconcile.js
"$NODE_BIN" simplesvet-worker.js
