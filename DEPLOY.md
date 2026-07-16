# Deploying SaunaSpeak

Two parts: the Laravel API (`backend/`) and the built Vue SPA (`frontend/dist/`).

## 1. Environment (`backend/.env` on the server)

```env
APP_ENV=production
APP_DEBUG=false                  # NEVER true in production - leaks stack traces
APP_KEY=                         # php artisan key:generate
APP_URL=https://saunaspeak.com   # used for Stripe redirect URLs

DB_CONNECTION=mysql
DB_DATABASE=...                  # from your host's MySQL panel
DB_USERNAME=...
DB_PASSWORD=...

# --- AI (Sauna Chat + corrections) - one of these ---
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
   - URL: `https://saunaspeak.com/api/billing/webhook`
   - Events: `checkout.session.completed`, `customer.subscription.updated`,
     `customer.subscription.deleted`
   - Copy the signing secret → `STRIPE_WEBHOOK_SECRET`.
4. The moment `STRIPE_SECRET` is set, the paywall activates: Sauna Chat, AI
   correction explanations and weekly insights require an active subscription.
   Unset it and everything is free again.
5. Test first with test-mode keys (`sk_test_...`) and card `4242 4242 4242 4242`.
6. If the price shown in the app changes, update the text in
   `frontend/src/pages/UpgradePage.vue` ("€4.99/month") to match.

## 3. cPanel deploy via git (recommended)

Everything ships through GitHub - the built SPA (`frontend/dist`), audio and
images are committed, so the server needs no Node and no manual uploads.
`deploy.sh` in the repo root does the whole server side.

### One-time setup (cPanel Terminal)

```bash
# 0. In cPanel UI first:
#    - MySQL Databases: create DB + user, grant ALL
#    - Domains -> Create A New Domain (or Subdomains): add the domain/subdomain
#      SaunaSpeak will live on, and set its Document Root to
#      saunaspeak/backend/public   <- this replaces any public_html symlinking
#    - MultiPHP Manager: set that domain to PHP 8.2+
#    Then in Terminal:

cd ~
git clone https://github.com/AleksisVejs/SaunaTalk.git saunaspeak
cd saunaspeak/backend

cp ../scripts/env.production.example .env
nano .env        # fill APP_URL, DB_*, OPENROUTER_API_KEY (see section 1)

composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan audio:generate      # links the shipped MP3s to sentences (seeder leaves audio_url null)
php artisan user:promote your@email.com

cd ~/saunaspeak
chmod +x deploy.sh
./deploy.sh      # caches, permissions, bakes the SPA; docroot comes from the domain setup
```

If `composer`/`php` aren't found, use your host's binaries, e.g.
`/usr/local/bin/ea-php83` and `php composer.phar`; the script accepts
`PHP_BIN=... COMPOSER_BIN=... ./deploy.sh`.

### Every deploy after that

```bash
# locally:
cd frontend && npm run build && cd ..
git add -A && git commit -m "..." && git push

# on cPanel Terminal:
cd ~/saunaspeak && ./deploy.sh
```

The script: resets + pulls, composer install, copies `frontend/dist` into
`backend/public`, runs migrations, relinks audio, clears + rebuilds caches,
fixes permissions, and keeps the `public_html` symlink pointed at
`backend/public`. Server-local files (`backend/.env`, `storage/`, generated
chat TTS, and the **native recordings** in `public/audio/human/` +
`public/audio/pending/`) survive because they're gitignored.

The `audio:generate` step matters: `git reset` reverts the tracked
`words.json` to the all-TTS committed version, and that command rebuilds it
from the files on disk so approved human takes stay live across deploys.

## 4. After every content addition (locally)

```bash
php artisan db:seed --class=JsonLessonSeeder && php artisan audio:generate && php artisan images:fetch
cd ../frontend && npm run build
git add -A && git commit -m "new lessons" && git push
# then on the server: ./deploy.sh (it now runs JsonLessonSeeder itself - any
# new database/lessons/*.json imports automatically)
```

## 5. Smoke test after deploy

- Register a fresh account, do one session end-to-end, check audio plays.
- `POST /api/chat` replies with `source: "ai"` (needs GEMINI_API_KEY).
- Chat reply plays audio (edge-tts or Google TTS configured).
- With Stripe test keys: upgrade → pay with 4242… → chat unlocks; webhook
  deliveries show 200 in the Stripe dashboard.

## 6. Scheduler (review-reminder emails)

`reminders:send` emails learners with due reviews daily at 17:00. It needs two
things on the server:

1. The Laravel scheduler cron (cPanel → Cron Jobs, every minute):
   ```
   * * * * * cd ~/saunaspeak/backend && php artisan schedule:run >> /dev/null 2>&1
   ```
2. A mail transport in `backend/.env` (`MAIL_MAILER=smtp` + your host's SMTP
   credentials). With the default `log` driver the command runs but only logs.

Preview who would get mail without sending: `php artisan reminders:send --dry-run`

## 7. Adding lessons with the AI pipeline

Locally:

```bash
php artisan lesson:draft "Topic name" --level=A1   # drafts JSON to storage/app/lesson-drafts/
# REVIEW the draft by hand - puhekieli quality is the product - then:
php artisan lesson:import storage/app/lesson-drafts/<file>.json
php artisan audio:generate
cd ../frontend && npm run build
git add -A && git commit -m "lesson: <topic>" && git push
```

`lesson:import` inserts the lesson at the end of its CEFR level block (so the
path shows one divider per level) and copies the reviewed JSON into
`database/lessons/` (tracked) for production.

On the server after `./deploy.sh`:

```bash
php artisan db:seed --class=JsonLessonSeeder   # imports new database/lessons/*.json (idempotent)
php artisan audio:generate                     # links the MP3s that came in via git
```
