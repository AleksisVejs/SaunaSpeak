#!/usr/bin/env bash
# Builds a cPanel-ready release: Laravel backend (prod vendor/) with the Vue
# SPA baked into public/, packaged as saunaspeak-release.zip (or .tar.gz).
# Works from Git Bash on Windows and from Linux/macOS:
#   bash scripts/deploy.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RELEASE="$ROOT/release"
cd "$ROOT"

echo "1/5 Building frontend..."
(cd frontend && npm run build)

echo "2/5 Staging backend..."
rm -rf "$RELEASE"
mkdir -p "$RELEASE"

# Everything Laravel needs at runtime; no tests, no .env, no dev tooling.
for d in app bootstrap config database public resources routes; do
  cp -r "backend/$d" "$RELEASE/$d"
done
cp backend/artisan backend/composer.json backend/composer.lock "$RELEASE/"

# Fresh storage skeleton (never ship local logs/caches).
mkdir -p "$RELEASE/storage/app/public" \
         "$RELEASE/storage/framework/cache/data" \
         "$RELEASE/storage/framework/sessions" \
         "$RELEASE/storage/framework/views" \
         "$RELEASE/storage/logs"
# Clear dev caches that came along with bootstrap/.
find "$RELEASE/bootstrap/cache" -type f -not -name '.gitignore' -delete 2>/dev/null || true

echo "3/5 Installing production vendor/ ..."
(cd "$RELEASE" && composer install --no-dev --optimize-autoloader --no-interaction --quiet)

echo "4/5 Baking SPA into public/ ..."
cp -r frontend/dist/. "$RELEASE/public/"

# Env template the server admin fills in as .env.
cp scripts/env.production.example "$RELEASE/.env.example.production"

echo "5/5 Packaging..."
rm -f saunaspeak-release.zip saunaspeak-release.tar.gz
if command -v zip >/dev/null 2>&1; then
  (cd "$RELEASE" && zip -rq "$ROOT/saunaspeak-release.zip" .)
  OUT=saunaspeak-release.zip
elif command -v powershell.exe >/dev/null 2>&1; then
  # Git Bash on Windows usually has no `zip` — borrow PowerShell's.
  powershell.exe -NoProfile -Command "Compress-Archive -Path '$(cygpath -w "$RELEASE")\\*' -DestinationPath '$(cygpath -w "$ROOT")\\saunaspeak-release.zip' -Force"
  OUT=saunaspeak-release.zip
else
  tar -czf saunaspeak-release.tar.gz -C "$RELEASE" .
  OUT=saunaspeak-release.tar.gz   # cPanel File Manager extracts this too
fi

echo "Done: $OUT ($(du -h "$ROOT/$OUT" | cut -f1))"
echo "Next: upload + extract on cPanel — see DEPLOY.md"
