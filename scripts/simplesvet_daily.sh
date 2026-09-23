#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "$ROOT_DIR/rpa"
/usr/bin/node simplesvet-reconcile.js
/usr/bin/node simplesvet-worker.js
