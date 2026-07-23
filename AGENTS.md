# SaunaSpeak Codex Adapter

This file is the Codex-facing adapter for the repository. `CLAUDE.md` is the
authoritative project guide and contains the non-obvious product, frontend,
audio, deployment, and workflow rules. Read it before making changes. Keep this
adapter concise and update the shared guidance in `CLAUDE.md` instead of copying
long sections here.

## Project map

- `backend/`: Laravel API and application code.
  - `app/`: controllers, models, services, support code, notifications, and
    Artisan commands.
  - `routes/`: API, web, and console routes.
  - `database/`: migrations, seeders, and JSON content for lessons, listening
    scenes, pronunciation pairs, transforms, and reference data.
  - `tests/`: PHPUnit feature and unit tests.
  - `public/audio/`: generated and recorded audio tiers; read `CLAUDE.md` before
    changing audio paths or regeneration behavior.
- `frontend/`: Vue 3, Vite, Pinia, and Vue Router PWA.
  - `src/pages/`: routed pages.
  - `src/components/`: shared UI and learning components.
  - `src/composables/`, `src/stores/`, and `src/utils/`: reusable behavior,
    state, and domain utilities.
  - `public/`: static assets and source artwork.
  - `dist/`: committed production build served in deployment.
- `scripts/`, `deploy.sh`, and `DEPLOY.md`: release and deployment tooling.
- `.github/workflows/ci.yml`: backend tests and frontend build verification.
- `.claude/skills/`: Claude skill sources. `.agents/skills/` contains the Codex
  copies; keep each `SKILL.md` with its supporting files.
- `.codex/config.toml`: minimal project-scoped Codex configuration.
- `.codex/agents/`: project-scoped Codex agent profiles, when corresponding
  Claude agents exist.

## Common commands

Run commands from the relevant subproject directory.

```powershell
# Backend
cd backend
composer install
php artisan test

# Frontend
cd frontend
npm ci
npm test
npm run build
```

Use a targeted backend test with `php artisan test --filter=<TestName>` when
appropriate. Do not start a dev server or preview solely for verification unless
the user explicitly asks.

## Non-negotiable rules

- Preserve the puhekieli-first product principle; kirjakieli is reference text.
- After any change under `frontend/src/`, run the frontend build and include the
  resulting `frontend/dist/` changes.
- Vue page components need one root element and must not use top-level `await`.
- Audio identity rules differ by content type. Read the audio section of
  `CLAUDE.md` before changing audio code, content, filenames, or regeneration.
- Keep secrets in ignored environment files or user-level configuration. Never
  add credentials to `.codex/config.toml` or committed documentation.

## Completion checks

- Run the smallest relevant tests first, then the broader affected suite.
- For frontend source changes, run `npm test` and `npm run build`; confirm
  `frontend/dist/` is synchronized.
- For backend changes, run the relevant PHPUnit tests; use the full
  `php artisan test` suite when the change has broad impact.
- Review the final diff for generated files, secrets, and accidental changes to
  server-owned audio or recordings.
