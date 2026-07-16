#!/bin/bash
# SaunaSpeak deployment script for cPanel Terminal.
#
# One-time setup is in DEPLOY.md. After that, every deploy is:
#   local:  cd frontend && npm run build && cd .. && git add -A && git commit -m "..." && git push
#   server: cd ~/saunaspeak && ./deploy.sh
#
# The frontend build (frontend/dist) is committed to git because cPanel has
# no Node - the pull brings backend code, built SPA, audio and images.

# ---- Configuration: adjust once for your cPanel account ----
REPO_PATH="$HOME/saunaspeak"
# PUBLIC_PATH: leave EMPTY when the (sub)domain's document root already points
# at backend/public (set it when creating the domain in cPanel - preferred).
# Only set this if you want the script to manage a symlink, e.g. "$HOME/public_html".
# NEVER point it at a public_html that serves another site (RigInspect!).
PUBLIC_PATH="${PUBLIC_PATH:-}"
PHP_BIN="${PHP_BIN:-php}"           # override e.g. PHP_BIN=/usr/local/bin/ea-php83 ./deploy.sh
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

BACKEND="$REPO_PATH/backend"

GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

fail() { echo -e "${RED}$1${NC}"; exit 1; }

echo -e "${GREEN}Starting SaunaSpeak deployment...${NC}"

cd "$REPO_PATH" || fail "Repo not found at $REPO_PATH - clone it first (see DEPLOY.md)"

# Sanity: never deploy over a missing .env (would boot with no config).
[ -f "$BACKEND/.env" ] || fail "No $BACKEND/.env - create it from scripts/env.production.example first"

echo "Resetting and pulling latest from git..."
git reset --hard HEAD || fail "git reset failed"
# audio/human + audio/pending are the native recordings uploaded through the
# studio - they exist ONLY on this server. They're gitignored (which already
# shields them from clean), but keep the explicit excludes as a second lock:
# a clean without them once deleted every approved take.
git clean -fd \
  --exclude=backend/.env \
  --exclude=backend/storage \
  --exclude=backend/public/audio/tts \
  --exclude=backend/public/audio/human \
  --exclude=backend/public/audio/pending
git pull || fail "git pull failed"

echo "Installing backend dependencies..."
cd "$BACKEND" || fail "backend/ missing"
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction || fail "composer install failed"

echo "Baking SPA into backend/public..."
[ -f "$REPO_PATH/frontend/dist/index.html" ] || fail "frontend/dist missing - run 'npm run build' locally and push"
cp -r "$REPO_PATH/frontend/dist/." "$BACKEND/public/"

echo "Running migrations..."
$PHP_BIN artisan migrate --force || fail "migrations failed"

# JsonLessonSeeder is idempotent (skips lessons already present by title), so
# any new database/lessons/*.json shipped in this pull imports automatically -
# no manual db:seed step on the server anymore.
echo "Importing any new lessons..."
$PHP_BIN artisan db:seed --class=JsonLessonSeeder --force || fail "lesson seeding failed"

# git reset reverts the tracked words.json to the committed (all-TTS) version.
# audio:generate rebuilds it from the files actually on disk - human takes win
# where their files exist, dangling human URLs in the DB fall back to TTS.
# Never fatal: on a host without edge-tts it still links everything shipped.
echo "Relinking audio (human takes win, manifest rebuilt)..."
$PHP_BIN artisan audio:generate || echo "audio:generate reported missing files - check output above"

echo "Clearing caches..."
$PHP_BIN artisan config:clear
$PHP_BIN artisan cache:clear
$PHP_BIN artisan route:clear
$PHP_BIN artisan view:clear

echo "Setting permissions..."
chmod -R 755 "$BACKEND"
chmod -R 775 "$BACKEND/storage" "$BACKEND/bootstrap/cache"
mkdir -p "$BACKEND/public/audio/tts" && chmod 775 "$BACKEND/public/audio/tts"

echo "Optimizing Laravel..."
$PHP_BIN artisan config:cache || fail "config:cache failed"
$PHP_BIN artisan route:cache || fail "route:cache failed"
$PHP_BIN artisan view:cache

# Optional docroot symlink - skipped unless PUBLIC_PATH is set explicitly.
if [ -z "$PUBLIC_PATH" ]; then
  echo "PUBLIC_PATH not set - skipping symlink (domain docroot should point at backend/public)."
elif [ "$(readlink -f "$PUBLIC_PATH")" != "$(readlink -f "$BACKEND/public")" ]; then
  if [ -e "$PUBLIC_PATH" ] && [ ! -L "$PUBLIC_PATH" ]; then
    echo "Backing up existing public_html..."
    mv "$PUBLIC_PATH" "${PUBLIC_PATH}_backup_$(date +%Y%m%d_%H%M%S)" || fail "backup failed"
  else
    rm -f "$PUBLIC_PATH"
  fi
  echo "Linking public_html -> backend/public..."
  ln -s "$BACKEND/public" "$PUBLIC_PATH" || fail "symlink failed"
else
  echo "public_html symlink already correct."
fi

echo -e "${GREEN}Deployment completed successfully!${NC}"
echo "Smoke test: open the site, log in, play one sentence's audio, send Väinö a message."
