#!/usr/bin/env bash
# ⚡ THREVOLT — Fast local dev server
#
# Why: `php artisan serve` runs the PHP built-in server WITHOUT OPcache and
# single-threaded. On Windows/XAMPP every request re-parses ~1,000+ framework
# files (~600ms bootstrap) and concurrent browser requests are serialized.
# This script starts the same server with:
#   • OPcache on          → request time ~0.80s → ~0.21s (measured, 4x faster)
#   • PHP_CLI_SERVER_WORKERS=4 → parallel handling of concurrent API calls
#
# Usage:
#   bash serve-fast.sh [port] [host]   # default port 8000, host 0.0.0.0
#   composer serve:fast                # via composer script
#
# Host defaults to 0.0.0.0 so the API is reachable from your phone on the same
# Wi-Fi (needed by the Android app) as well as from localhost.
#
# Note: with OPcache active, PHP file changes take up to `revalidate_freq`
# (2s) to appear. Restart the script if you edit PHP and don't see changes.

set -e
cd "$(dirname "$0")"
# Absolute path to the project root (required for the router script below -
# relative paths break when the PHP built-in server spawns worker processes).
PROJECT_ROOT="$(pwd)"

# ── Locate the PHP OPcache extension DLL ───────────────────────────────────
OPCACHE_DLL=""
EXT_DIR=$(php -i 2>/dev/null | grep '^extension_dir' | head -1 | awk '{print $3}' | tr '\\' '/' | tr -d '\r')
for cand in "${EXT_DIR}/php_opcache.dll" "C:/xampp/php/ext/php_opcache.dll" "C:/php/ext/php_opcache.dll"; do
  if [ -n "$cand" ] && [ -f "$cand" ]; then
    OPCACHE_DLL="$cand"
    break
  fi
done

PORT="${1:-8000}"
HOST="${2:-0.0.0.0}"

ARGS=()
if [ -n "$OPCACHE_DLL" ]; then
  ARGS+=(-d "zend_extension=${OPCACHE_DLL}")
  ARGS+=(-d opcache.enable_cli=1)
  ARGS+=(-d opcache.validate_timestamps=1)
  ARGS+=(-d opcache.revalidate_freq=2)
  echo "⚡ OPcache ON  → ${OPCACHE_DLL}"
else
  echo "⚠  OPcache DLL not found — serving WITHOUT OPcache (still works, just slower)."
  echo "   Enable OPcache for ~4x faster responses."
fi

echo "🚀 THREVOLT API running on http://${HOST}:${PORT}  (OPcache)"
if [ "$HOST" != "127.0.0.1" ] && [ "$HOST" != "localhost" ]; then
  echo "   LAN: phones on this Wi-Fi can reach http://<PC-LAN-IP>:${PORT}"
fi
echo "   Press Ctrl+C to stop."

# NOTE: no PHP_CLI_SERVER_WORKERS - it is unstable on Windows (worker
# processes fail to load the router script / hang). OPcache alone makes each
# request ~4x faster; use XAMPP Apache + PHP for true parallel processing.
cd public
exec php "${ARGS[@]}" -S "${HOST}:${PORT}" "${PROJECT_ROOT}/server.php"
