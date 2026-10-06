#!/usr/bin/env bash
#
# Entry point for the Playwright service. Installs the test dependencies on
# first use (they live in the mounted directory and are git-ignored) and then
# runs the suite, forwarding any arguments to `playwright test`.
#
#   docker compose run --rm e2e
#   docker compose run --rm e2e --grep "checkout"
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

if [[ ! -d node_modules/@playwright ]]; then
  echo "Installing Playwright test dependencies…"
  npm install --no-audit --no-fund --loglevel=error
fi

exec npx playwright test "$@"
