# Deploying SaunaSpeak

Two parts: the Laravel API (`backend/`) and the built Vue SPA (`frontend/dist/`).

## 1. Environment (`backend/.env` on the server)

```env
APP_ENV=production
APP_DEBUG=false                  # NEVER true in production — leaks stack traces
APP_KEY=                         # php artisan key:generate
APP_URL=https://yourdomain.com   # used for Stripe redirect URLs

DB_CONNECTION=mysql
DB_DATABASE=...                  # from your host's MySQL panel
DB_USERNAME=...
DB_PASSWORD=...

# --- AI (Sauna Chat + corrections) — one of these ---
GEMINI_API_KEY=AIzaSy...         # free tier: aistudio.google.com/apikey
# AI_API_KEY=sk-ant-...          # or Anthropic (takes priority if both set)

# --- Chat voice ---
# VPS with Python:  pip install edge-tts   (male Finnish voice, free)
# EDGE_TTS_BIN=/usr/local/bin/edge-tts
# Shared hosting (cPanel): Google Cloud TTS fallback
# GOOGLE_TTS_API_KEY=...         # console.cloud.google.com → enable "Cloud Text-to-Speech API"

# --- Löyly+ billing (leave ALL unset until launch → everything stays free) ---
# STRIPE_SECRET=sk_live_...
# STRIPE_PRICE_ID=price_...      # create a recurring price in the Stripe dashboard
# STRIPE_WEBHOOK_SECRET=whsec_...
```

## 2. Stripe setup (when you're ready to charge)

1. dashboard.stripe.com → Products → add "Löyly+" with a recurring monthly price
   (e.g. €4.99). Copy the `price_...` id → `STRIPE_PRICE_ID`.
2. Developers → API keys → copy the secret key → `STRIPE_SECRET`.
3. Developers → Webhooks → Add endpoint:
   - URL: `https://yourdomain.com/api/billing/webhook`
   - Events: `checkout.session.completed`, `customer.subscription.updated`,
     `customer.subscription.deleted`
   - Copy the signing secret → `STRIPE_WEBHOOK_SECRET`.
4. The moment `STRIPE_SECRET` is set, the paywall activates: Sauna Chat, AI
   correction explanations and weekly insights require an active subscription.
   Unset it and everything is free again.
5. Test first with test-mode keys (`sk_test_...`) and card `4242 4242 4242 4242`.
6. If the price shown in the app changes, update the text in
   `frontend/src/pages/UpgradePage.vue` ("€4.99/month") to match.

## 3. Backend upload (cPanel)

1. Upload `backend/` (with `vendor/` — run `composer install --no-dev` locally
   first, or via SSH on the server).
2. Point the domain's document root at `backend/public`.
3. `php artisan key:generate && php artisan migrate --force && php artisan db:seed --force`
   Then make yourself admin: `php artisan user:promote your@email.com`
4. `php artisan config:cache && php artisan route:cache`
5. Upload the pre-generated media as-is (no Python needed on the server):
   - `backend/public/audio/` (sentence + word + try MP3s, words.json)
   - `backend/public/images/` (OpenMoji SVGs)

## 4. Frontend

1. Locally: `cd frontend && npm run build`
2. Upload the contents of `frontend/dist/` into the same document root
   (`index.html`, `assets/`, icons, Väinö images, `sw.js`, ...). The SPA and
   the API share the domain, so `/api`, `/audio`, `/images` need no config.
3. Laravel must NOT swallow SPA routes: `routes/web.php` should return the
   SPA's `index.html` for any non-API path (or use an `.htaccess` fallback).

## 5. After every content addition

```bash
php artisan db:seed --class=<NewLessonSeeder> && php artisan audio:generate && php artisan images:fetch
```
then re-upload `public/audio` + `public/images`.

## 6. Smoke test after deploy

- Register a fresh account, do one session end-to-end, check audio plays.
- `POST /api/chat` replies with `source: "ai"` (needs GEMINI_API_KEY).
- Chat reply plays audio (edge-tts or Google TTS configured).
- With Stripe test keys: upgrade → pay with 4242… → chat unlocks; webhook
  deliveries show 200 in the Stripe dashboard.
