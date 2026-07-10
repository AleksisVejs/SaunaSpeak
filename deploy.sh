#!/bin/bash
# SaunaSpeak deployment script for cPanel Terminal.
#
# One-time setup is in DEPLOY.md. After that, every deploy is:
#   local:  cd frontend && npm run build && cd .. && git add -A && git commit -m "..." && git push
#   server: cd ~/saunaspeak && ./deploy.sh
#
# The frontend build (frontend/dist) is committed to git because cPanel has
# no Node — the pull brings backend code, built SPA, audio and images.

# ---- Configuration: adjust once for your cPanel account ----
REPO_PATH="$HOME/saunaspeak"
PUBLIC_PATH="$HOME/public_html"
PHP_BIN="${PHP_BIN:-php}"           # override e.g. PHP_BIN=/usr/local/bin/ea-php83 ./deploy.sh
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

BACKEND="$REPO_PATH/backend"

GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

fail() { echo -e "${RED}$1${NC}"; exit 1; }

echo -e "${GREEN}Starting SaunaSpeak deployment...${NC}"

cd "$REPO_PATH" || fail "Repo not found at $REPO_PATH — clone it first (see DEPLOY.md)"

# Sanity: never deploy over a missing .env (would boot with no config).
[ -f "$BACKEND/.env" ] || fail "No $BACKEND/.env — create it from scripts/env.production.example first"

echo "Resetting and pulling latest from git..."
git reset --hard HEAD || fail "git reset failed"
git clean -fd --exclude=backend/.env --exclude=backend/storage --exclude=backend/public/audio/tts
git pull || fail "git pull failed"

echo "Installing backend dependencies..."
cd "$BACKEND" || fail "backend/ missing"
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction || fail "composer install failed"

echo "Baking SPA into backend/public..."
[ -f "$REPO_PATH/frontend/dist/index.html" ] || fail "frontend/dist missing — run 'npm run build' locally and push"
cp -r "$REPO_PATH/frontend/dist/." "$BACKEND/public/"

echo "Running migrations..."
$PHP_BIN artisan migrate --force || fail "migrations failed"

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

# public_html -> backend/public symlink (idempotent: only touches it when wrong)
if [ "$(readlink -f "$PUBLIC_PATH")" != "$(readlink -f "$BACKEND/public")" ]; then
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
