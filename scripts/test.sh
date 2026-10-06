#!/usr/bin/env bash
#
# Test runner for the Amino Heaven WordPress + WooCommerce storefront.
#
#   scripts/test.sh              run every suite
#   scripts/test.sh php          PHP syntax check across the theme
#   scripts/test.sh catalog      WooCommerce catalog verification (prices, SKUs, media)
#   scripts/test.sh store        WordPress/WooCommerce configuration verification
#   scripts/test.sh e2e          Playwright browser flows
#   scripts/test.sh e2e --grep checkout
#   scripts/test.sh http         HTTP smoke test of every route
#
# The stack must be running (`docker compose up -d`, or scripts/setup.sh).
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

THEME_DIR="$ROOT/wordpress/wp-content/themes/amino-heaven"
WP_IMAGE="wordpress:cli-2.12.0-php8.3"

PASS=0
FAIL=0
declare -a FAILED_SUITES=()

log()  { printf '\n\033[1;34m==>\033[0m \033[1m%s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m  PASS\033[0m %s\n' "$*"; PASS=$((PASS + 1)); }
bad()  { printf '\033[1;31m  FAIL\033[0m %s\n' "$*"; FAIL=$((FAIL + 1)); FAILED_SUITES+=("$1"); }

require_env() {
  if [[ ! -f .env ]]; then
    echo "error: .env missing. Run: cp .env.example .env && scripts/setup.sh" >&2
    exit 1
  fi
}

# ---------------------------------------------------------------------------
# PHP syntax
# ---------------------------------------------------------------------------
suite_php() {
  log "PHP syntax check (theme)"
  local failures=0 total=0

  while IFS= read -r file; do
    total=$((total + 1))
    if ! docker run --rm --entrypoint php -v "$THEME_DIR:/app:ro" "$WP_IMAGE" -l "/app/${file#./}" >/dev/null 2>&1; then
      bad "php-lint"
      echo "    syntax error: $file"
      failures=$((failures + 1))
    fi
  done < <(cd "$THEME_DIR" && find . -name '*.php' | sort)

  if [[ "$failures" -eq 0 ]]; then
    ok "php-lint ($total files, 0 errors)"
  fi
}

# ---------------------------------------------------------------------------
# WooCommerce catalog verification
# ---------------------------------------------------------------------------
suite_catalog() {
  log "WooCommerce catalog verification"
  if ./scripts/wp.sh eval-file /tests/wp/verify-catalog.php 2>&1 | grep -v -E "Container|Healthy|Waiting|Running|^$"; then
    ok "catalog"
  else
    bad "catalog"
  fi
}

# ---------------------------------------------------------------------------
# Store configuration verification
# ---------------------------------------------------------------------------
suite_store() {
  log "WordPress / WooCommerce configuration verification"
  if ./scripts/wp.sh eval-file /tests/wp/verify-store.php 2>&1 | grep -v -E "Container|Healthy|Waiting|Running|^$"; then
    ok "store"
  else
    bad "store"
  fi
}

# ---------------------------------------------------------------------------
# HTTP smoke test
# ---------------------------------------------------------------------------
suite_http() {
  log "HTTP smoke test"
  require_env
  # shellcheck disable=SC1091
  set -a; source .env; set +a
  local base="${WP_URL:-http://localhost:8080}"
  base="${base%/}"

  local -a routes=(
    "/" "/shop/" "/shop/page/2/" "/product/bpc-157/" "/product/glp3-rt/"
    "/product-category/research-peptides/" "/product-category/peptide-blends/"
    "/product-category/glp-research/" "/quality-coas/" "/about/" "/contact/"
    "/research-use-terms/" "/track-order/" "/cart/" "/my-account/" "/?s=glp"
    "/wp-sitemap.xml"
  )

  local ua="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36"
  local failures=0

  for route in "${routes[@]}"; do
    local code
    code="$(curl -sS -o /tmp/ah-smoke.html -w '%{http_code}' -A "$ua" "${base}${route}")"
    local errs
    errs="$(grep -c -i -E "Fatal error|<b>Warning</b>|<b>Notice</b>|<b>Deprecated</b>" /tmp/ah-smoke.html || true)"

    if [[ "$code" != "200" || "$errs" != "0" ]]; then
      bad "http"
      echo "    ${route} -> HTTP ${code}, php errors: ${errs}"
      failures=$((failures + 1))
    fi
  done

  # Checkout legitimately redirects when the cart is empty.
  local checkout_code
  checkout_code="$(curl -sS -o /dev/null -w '%{http_code}' -A "$ua" "${base}/checkout/")"
  if [[ "$checkout_code" != "302" && "$checkout_code" != "200" ]]; then
    bad "http"
    echo "    /checkout/ -> HTTP ${checkout_code} (expected 200 or 302)"
    failures=$((failures + 1))
  fi

  # A 404 must really be a 404.
  local notfound
  notfound="$(curl -sS -o /dev/null -w '%{http_code}' -A "$ua" "${base}/no-such-page-xyz/")"
  if [[ "$notfound" != "404" ]]; then
    bad "http"
    echo "    /no-such-page-xyz/ -> HTTP ${notfound} (expected 404)"
    failures=$((failures + 1))
  fi

  if [[ "$failures" -eq 0 ]]; then
    ok "http ($(( ${#routes[@]} + 2 )) routes)"
  fi
}

# ---------------------------------------------------------------------------
# Playwright
# ---------------------------------------------------------------------------
suite_e2e() {
  log "Playwright browser flows"
  # Start from a clean output directory so nothing from an earlier run (or an
  # interrupted one) can interfere.
  rm -rf tests/e2e/test-results 2>/dev/null || true

  if docker compose run --rm e2e "$@"; then
    ok "e2e"
  else
    bad "e2e"
  fi
}

# ---------------------------------------------------------------------------
# Dispatch
# ---------------------------------------------------------------------------
TARGET="${1:-all}"
shift || true

case "$TARGET" in
  php)     suite_php ;;
  catalog) require_env; suite_catalog ;;
  store)   require_env; suite_store ;;
  http)    suite_http ;;
  e2e)     require_env; suite_e2e "$@" ;;
  all)
    require_env
    suite_php
    suite_catalog
    suite_store
    suite_http
    suite_e2e "$@"
    ;;
  -h|--help)
    sed -n '2,16p' "$0"
    exit 0
    ;;
  *)
    echo "unknown suite: $TARGET" >&2
    echo "use: php | catalog | store | http | e2e | all" >&2
    exit 2
    ;;
esac

log "Result"
echo "  passed: ${PASS}"
echo "  failed: ${FAIL}"

if [[ "$FAIL" -gt 0 ]]; then
  printf '  failed suites: %s\n' "${FAILED_SUITES[*]}"
  exit 1
fi

echo "  all selected suites passed"
