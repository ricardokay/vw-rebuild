#!/usr/bin/env bash
#
# WP-CLI wrapper for the Local-hosted vancouverweekly-local site.
#
# Removes the per-session rebuild tax: Local's mysqld socket lives under a
# run-ID directory that changes whenever Local restarts, and /tmp gets wiped
# between sessions. This re-detects the socket every invocation and caches the
# phar outside /tmp.
#
# Usage:  tools/wp.sh option get blogname
#         tools/wp.sh post list --post_status=publish --format=count
#         tools/wp.sh db query "SELECT COUNT(*) FROM wptg_posts"
#
# Overrides: VW_SITE_PATH, VW_MYSQL_SOCK, VW_WP_CACHE

set -euo pipefail

LOCAL_RUN="$HOME/Library/Application Support/Local/run"
LIGHTNING="$HOME/Library/Application Support/Local/lightning-services"
SITE_PATH="${VW_SITE_PATH:-$HOME/Local Sites/vancouverweekly-local/app/public}"
CACHE_DIR="${VW_WP_CACHE:-$HOME/.cache/vw-wp-cli}"
PHAR="$CACHE_DIR/wp-cli.phar"
PHP_INI="$CACHE_DIR/php.ini"
PHAR_URL="https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar"

die() { printf 'wp.sh: %s\n' "$*" >&2; exit 1; }

# --- site ------------------------------------------------------------------
[ -f "$SITE_PATH/wp-load.php" ] || die "no WordPress at: $SITE_PATH
  Set VW_SITE_PATH to the site's app/public directory."

# --- mysqld socket: re-detected every run ----------------------------------
# Local's run-ID (e.g. HKOO9D7DI) changes on restart, so it is never hardcoded.
if [ -n "${VW_MYSQL_SOCK:-}" ]; then
  SOCK="$VW_MYSQL_SOCK"
  [ -S "$SOCK" ] || die "VW_MYSQL_SOCK is not a socket: $SOCK"
else
  SOCK=""
  SOCK_COUNT=0
  FOUND=""
  while IFS= read -r candidate; do
    [ -S "$candidate" ] || continue
    SOCK_COUNT=$((SOCK_COUNT + 1))
    SOCK="$candidate"
    FOUND="$FOUND  $candidate"$'\n'
  done < <(find "$LOCAL_RUN" -maxdepth 3 -name mysqld.sock 2>/dev/null)

  if [ "$SOCK_COUNT" -eq 0 ]; then
    die "no mysqld.sock found under:
  $LOCAL_RUN
  Is Local running and the site started? Or set VW_MYSQL_SOCK."
  fi
  if [ "$SOCK_COUNT" -gt 1 ]; then
    die "$SOCK_COUNT candidate sockets found — ambiguous, refusing to guess:
$FOUND  Set VW_MYSQL_SOCK to the one you want."
  fi
fi

# --- php + mysql client from Local's bundled services ----------------------
PHP_BIN=""
for candidate in "$LIGHTNING"/php-*/bin/*/bin/php; do
  [ -x "$candidate" ] && PHP_BIN="$candidate"
done
[ -n "$PHP_BIN" ] || die "no PHP binary under $LIGHTNING/php-*/bin/*/bin/php"

# `wp db query` shells out to the mysql client, which needs two separate things:
#
#   1. to be ON PATH at all — otherwise "env: mysql: No such file or directory"
#      (the July handoff's fix), and
#   2. to be pointed at Local's socket. The -d flags below configure PHP's mysqli
#      driver, NOT the client binary, which otherwise tries /tmp/mysql.sock and
#      fails with "Failed to get current SQL modes ... ERROR 2002". MYSQL_UNIX_PORT
#      is the client's own socket override.
#
# PATH alone is not sufficient — verified 2026-09-11.
MYSQL_BIN_DIR=""
for candidate in "$LIGHTNING"/mysql-*/bin/*/bin; do
  [ -x "$candidate/mysql" ] && MYSQL_BIN_DIR="$candidate"
done
if [ -n "$MYSQL_BIN_DIR" ]; then
  PATH="$MYSQL_BIN_DIR:$PATH"
  export PATH
fi
MYSQL_UNIX_PORT="$SOCK"
export MYSQL_UNIX_PORT

# --- cached phar, validated ------------------------------------------------
mkdir -p "$CACHE_DIR"
[ -f "$PHP_INI" ] || printf 'memory_limit = 1024M\ndetect_unicode = Off\n' > "$PHP_INI"

phar_ok() {
  [ -s "$PHAR" ] && "$PHP_BIN" -c "$PHP_INI" "$PHAR" --version >/dev/null 2>&1
}

if ! phar_ok; then
  command -v curl >/dev/null 2>&1 || die "curl not available to fetch wp-cli.phar"
  printf 'wp.sh: fetching wp-cli.phar into %s\n' "$CACHE_DIR" >&2
  curl -sSL --fail -o "$PHAR.tmp" "$PHAR_URL" || die "download failed: $PHAR_URL"
  mv "$PHAR.tmp" "$PHAR"
  phar_ok || die "downloaded file is not a working wp-cli phar: $PHAR"
fi

# --- run -------------------------------------------------------------------
exec "$PHP_BIN" \
  -c "$PHP_INI" \
  -d mysqli.default_socket="$SOCK" \
  -d pdo_mysql.default_socket="$SOCK" \
  "$PHAR" --path="$SITE_PATH" "$@"
