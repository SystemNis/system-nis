#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════════
# start.sh — Container entrypoint for Railway
#
# Runs once per container boot (every deploy, every restart). Order matters:
#   1. Cache config/routes/views for production performance
#   2. Run pending migrations (safe to run every boot — Laravel tracks
#      which migrations already ran via the `migrations` table)
#   3. Boot the HTTP server on Railway's injected $PORT
# ════════════════════════════════════════════════════════════════════════
set -e

echo "── NIS Audit System: booting ──────────────────────────────"

# Railway injects PORT at runtime; default to 8080 for local docker testing
PORT="${PORT:-8080}"

# ── Laravel production caches ────────────────────────────────────────────
# Safe no-ops if already cached from a previous boot in the same image.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── Database migrations ──────────────────────────────────────────────────
# --force is required in production since Laravel normally prompts for
# confirmation; non-interactive containers need this flag to proceed.
php artisan migrate --force

# ── Storage symlink (for any user-uploaded files / public storage) ──────
php artisan storage:link || true

echo "── Starting server on 0.0.0.0:${PORT} ─────────────────────"

# artisan serve is sufficient for an internal audit tool's traffic profile.
# For significantly higher concurrency, swap this for php-fpm + nginx.
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
