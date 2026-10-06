#!/usr/bin/env bash
#
# One-shot, idempotent provisioning of the Amino Heaven WordPress + WooCommerce
# development store.
#
#   cp .env.example .env
#   scripts/setup.sh
#
# Safe to run repeatedly: every step checks the current state first. Pass
# --skip-seed to provision the environment without importing catalog data.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

SKIP_SEED=0
for arg in "$@"; do
  case "$arg" in
    --skip-seed) SKIP_SEED=1 ;;
    -h|--help) sed -n '2,12p' "$0"; exit 0 ;;
    *) echo "unknown option: $arg" >&2; exit 2 ;;
  esac
done

log()  { printf '\n\033[1;34m==>\033[0m \033[1m%s\033[0m\n' "$*"; }
warn() { printf '\033[1;33mwarning:\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31merror:\033[0m %s\n' "$*" >&2; exit 1; }

command -v docker >/dev/null || die "docker is not installed"
docker info >/dev/null 2>&1 || die "the Docker daemon is not running"

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
if [[ ! -f .env ]]; then
  cp .env.example .env
  warn "created .env from .env.example — review it, then re-run this script."
  exit 1
fi
set -a; # shellcheck disable=SC1091
source .env; set +a

: "${WP_URL:?WP_URL must be set in .env}"
WP_URL="${WP_URL%/}"
ADMIN_USER="${WP_ADMIN_USER:-admin}"
ADMIN_PASS="${WP_ADMIN_PASSWORD:?WP_ADMIN_PASSWORD must be set in .env}"
ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"
STORE_EMAIL="${STORE_EMAIL:-hello@aminoheaven.com}"

wp() { ./scripts/wp.sh "$@"; }

# ---------------------------------------------------------------------------
# 1. Database
# ---------------------------------------------------------------------------
log "Starting MariaDB"
docker compose up -d db

log "Waiting for MariaDB to report healthy"
db_status=""
for _ in $(seq 1 90); do
  db_status="$(docker inspect -f '{{.State.Health.Status}}' amino-heaven-db 2>/dev/null || echo unknown)"
  [[ "$db_status" == "healthy" ]] && break
  sleep 2
done
[[ "$db_status" == "healthy" ]] || die "MariaDB did not become healthy (last status: ${db_status})"
echo "    database ready"

# ---------------------------------------------------------------------------
# 2. WordPress core + wp-config.php (bootstrapped by the official image)
# ---------------------------------------------------------------------------
log "Starting WordPress"
docker compose up -d wordpress

log "Waiting for WordPress core files to be unpacked"
ready=0
for _ in $(seq 1 90); do
  if [[ -f wordpress/wp-includes/version.php && -f wordpress/wp-config.php ]]; then
    ready=1; break
  fi
  sleep 2
done
[[ "$ready" == "1" ]] || die "WordPress core was not unpacked into ./wordpress"

log "Waiting for the web server"
served=0
for _ in $(seq 1 60); do
  if curl -fsS -o /dev/null "${WP_URL}/wp-login.php"; then served=1; break; fi
  sleep 2
done
if [[ "$served" == "1" ]]; then
  echo "    ${WP_URL} is responding"
else
  warn "${WP_URL} did not respond yet; continuing (check \`docker compose logs wordpress\`)"
fi

# ---------------------------------------------------------------------------
# 3. WordPress installation
# ---------------------------------------------------------------------------
if wp core is-installed >/dev/null 2>&1; then
  echo "    WordPress is already installed"
else
  log "Installing WordPress"
  wp core install \
    --url="$WP_URL" \
    --title="Amino Heaven" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASS" \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email
fi

log "Applying locale and timezone"
wp language core install en_US --activate >/dev/null 2>&1 || warn "could not install en_US (offline?)"
wp option update timezone_string "America/Los_Angeles" >/dev/null
wp option update blogdescription "Analytical-grade research compounds verified by independent U.S. laboratories" >/dev/null

# ---------------------------------------------------------------------------
# 4. Theme + WooCommerce
# ---------------------------------------------------------------------------
log "Activating the Amino Heaven theme"
wp theme activate amino-heaven

log "Installing WooCommerce"
if wp plugin is-installed woocommerce >/dev/null 2>&1; then
  wp plugin activate woocommerce
else
  wp plugin install woocommerce --activate
fi

log "Configuring permalinks"
wp rewrite structure '/%postname%/' >/dev/null
wp rewrite flush >/dev/null

# Pretty permalinks need a .htaccess rewrite block (Apache runs this stack with
# AllowOverride All). WordPress's own save_mod_rewrite_rules() refuses to act when
# it cannot detect Apache, and WP-CLI never sets $_SERVER['SERVER_SOFTWARE'], so
# present ourselves as Apache first. A bash fallback writes the same canonical
# block if WordPress still declines, so a fresh clone always gets working URLs.
htaccess_ok=0

if wp eval '
	$_SERVER["SERVER_SOFTWARE"] = "Apache/2.4";
	require_once ABSPATH . "wp-admin/includes/misc.php";
	require_once ABSPATH . "wp-admin/includes/file.php";
	exit( save_mod_rewrite_rules() ? 0 : 1 );
' >/dev/null 2>&1; then
  htaccess_ok=1
fi

# Only ever write the fallback when there is no WordPress block yet, so a
# hand-edited .htaccess (or one WordPress wrote itself) is never clobbered.
if grep -q "BEGIN WordPress" wordpress/.htaccess 2>/dev/null; then
  echo "    .htaccess rewrite rules already in place"
elif [[ "$htaccess_ok" != "1" ]]; then
  # Derive RewriteBase from WP_URL so subdirectory installs work too.
  base_path="$(printf '%s' "$WP_URL" | sed -E 's#^https?://[^/]+##')"
  [[ -z "$base_path" ]] && base_path="/"
  [[ "$base_path" != */ ]] && base_path="${base_path}/"

  cat > wordpress/.htaccess <<HTACCESS
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase ${base_path}
RewriteRule ^index\\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . ${base_path}index.php [L]
</IfModule>
# END WordPress
HTACCESS
  echo "    wrote .htaccess rewrite rules"
fi

if ! grep -q "BEGIN WordPress" wordpress/.htaccess 2>/dev/null; then
  warn "could not write wordpress/.htaccess — pretty permalinks will 404 until it exists"
fi

# ---------------------------------------------------------------------------
# 5. Store + content provisioning (theme WP-CLI commands)
# ---------------------------------------------------------------------------
log "Configuring the store (US / USD / en_US, BACS + COD, flat rate)"
wp amino-heaven setup-store

log "Creating pages, menus and editorial content"
wp amino-heaven setup-content

if [[ "$SKIP_SEED" == "0" ]]; then
  log "Importing the Amino Heaven catalog as native WooCommerce products"
  wp amino-heaven import-products --update
else
  warn "skipping catalog import (--skip-seed)"
fi

# ---------------------------------------------------------------------------
# 6. Summary
# ---------------------------------------------------------------------------
log "Done"
cat <<EOF
    Storefront : ${WP_URL}
    WP admin   : ${WP_URL}/wp-admin  (${ADMIN_USER})
    Shop       : ${WP_URL}/shop
    Cart       : ${WP_URL}/cart
    Checkout   : ${WP_URL}/checkout
    WP-CLI     : scripts/wp.sh <args>

    Storefront email is set to ${STORE_EMAIL}.
EOF
