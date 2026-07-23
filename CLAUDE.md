# SaunaSpeak — Project Guide

SaunaSpeak is a Finnish-learning web app (Laravel backend + Vue frontend). This file
captures the non-obvious rules and gotchas an agent needs to work here safely. Follow
these exactly — several encode bugs that have already bitten this project.

## Product principle

- **Puhekieli-first.** Spoken/colloquial Finnish is the primary content; kirjakieli
  (standard written Finnish) appears only as a reference, never as the main form.
- No dialect tracks (TTS can't voice them reliably). Learning happens through
  scenario roleplay ("Tilanteet") and personalized chat. Plans are monthly/yearly,
  plus a 3-day Löyly+ trial (card upfront, first-timers only).

## Frontend rules (Vue)

- **`frontend/dist` is tracked in git and IS what production serves.** After ANY edit
  under `frontend/src`, you must rebuild the frontend and commit the updated
  `frontend/dist` — the source change alone does nothing in production.
- **Every page component must have a single root element.** App.vue wraps `router-view`
  in a route transition; a page that renders a fragment root breaks navigation.
- **No top-level `await` in page components.** App.vue does NOT wrap `router-view` in
  `<Suspense>`, so a top-level await in a routed page will break it.

## Audio rules (these are subtle — read before touching audio)

- **Sentence audio is keyed by TEXT, not id.** Clip filenames derive from
  `finnish_text`. Ids drift between local and prod, so never map audio by id.
- **Listening/scene audio is keyed by SLOT, not text.** Editing a scene line leaves the
  OLD audio in place until you regenerate with `--force`. Don't assume an edit refreshes
  the clip.
- After a `migrate:fresh` / reseed, run `php artisan audio:generate` to relink
  `audio_url` — otherwise audio comes back empty.
- The test suite stashes the real ElevenLabs clips during runs. An interrupted
  `php artisan test` leaves them in `public/audio/eleven__live_backup/` — restore from
  there rather than assuming they were destroyed.

## Deploy / ops gotchas

- **`/sitemap.xml` is now a Laravel route**, not a static file. If a static
  `public/sitemap.xml` exists on the server it shadows the dynamic route — delete it.
- The reminder-email cron IS live. The mail has a stale-streak bug that can show lapsed
  users an incorrect streak — verify streak freshness before trusting reminder copy.
- Stripe webhook endpoint must send `checkout.session.async_payment_succeeded`
  (required for Löyly+ subscription linking).

## Environment notes

- Primary shell is PowerShell on Windows. When passing multi-line strings (e.g. commit
  messages) to native tools, do NOT use PowerShell here-strings (`@'...'@`) from a POSIX
  shell context — it silently prefixes the subject with a bare `@`. Use a heredoc.

## Working preferences

- Do not spin up a dev server / preview to verify changes unless explicitly asked.
