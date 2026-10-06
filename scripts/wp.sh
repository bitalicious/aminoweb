#!/usr/bin/env bash
#
# Thin wrapper around the WP-CLI sidecar so the whole project can be driven
# without remembering Docker Compose incantations.
#
#   scripts/wp.sh plugin list
#   scripts/wp.sh amino-heaven import-products
#   scripts/wp.sh --info
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "error: .env not found. Run: cp .env.example .env" >&2
  exit 1
fi

# `docker compose run` brings up the db/wordpress dependencies first.
exec docker compose run --rm cli "$@"
