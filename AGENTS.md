# AGENTS.md

## Setup & Dev Server

```bash
composer setup                          # install + .env + key + migrate + npm build
composer dev                            # serve + queue:listen + vite (concurrently)
npm run dev                             # vite only
npm run build                           # required after frontend changes
```

App is DELTA SGA (leave/vacation approvals). Default `APP_URL=http://localhost:8092`, DB is **PostgreSQL** (`pgsql` 5432 `delta_sga`); SQLite only in tests. No Herd `.test` domain in use despite Boost defaults.

## Verification (order matters)

`composer test` runs `config:clear -> pint --test -> phpstan -> pest`. Replicate manually for focused checks:

```bash
php artisan config:clear
vendor/bin/pint --parallel --test        # lint:check — use --dirty --format agent to fix
phpstan analyse                         # levels 7, paths: app/ bootstrap/app.php config/ database/ routes/
php artisan test --compact              # all tests
php artisan test --compact --filter=testName
php artisan test --compact tests/Feature/SolicitudesPageTest.php
composer ci:check                       # same as composer test — what CI runs
```

CI (`.github/workflows/tests.yml`): `setup-php 8.3 + node 22 -> composer setup -> composer ci:check`.

## Architecture

- **Stack:** Laravel 13 / PHP 8.4 / Livewire 4 + Flux 2 + Blaze 1 + Fortify 1.37 / Tailwind 4 / Vite 8.
- **Routing:** SFC pages in `resources/views/pages/**/⚡*.blade.php` mounted via `Route::livewire('path', 'pages::...')` in `routes/web.php`. Admin routes in `routes/admin.php`, settings in `routes/settings.php`. Two non-Livewire routes: `solicitudes/{solicitud}/pdf` and passkey approval endpoints.
- **Vite inputs:** `resources/css/app.css`, `resources/js/app.js`, `resources/js/passkeys.js`.
- **Domain:** `app/Models` (User, LeaveRequest, LeaveApproval, VacationBalance, Area, Holiday…), `app/Enums` (LeaveRequestStatus/Type, JornadaTipo…), `app/Services`, `app/Actions/Fortify`.
- **Auth:** Fortify with `resetPasswords` + `passkeys` (confirmPassword false). No registration/email verification/2FA. Guard `web`.

## Gotchas — Read `.ai/rules` First

Before planning/editing, open `.ai/rules/index.md` and read matching rule files:

- `**` -> `.ai/rules/general.md` — **config cache breaks tests**: cached config bakes `APP_ENV=local` so tests run with 419 CSRF failures. Always `php artisan config:clear` before `php artisan test`; never `config:cache` in dev.
- `resources/views/pages/**` -> `.ai/rules/pages.md`:
  - `#[Computed]` props must be `$this->prop` in Blade, never bare `$prop`.
  - No MySQL-only SQL (`YEAR()`, `IFNULL`, `DATE_FORMAT`, `selectRaw` with MySQL funcs) — breaks on Postgres/SQLite tests. Use `whereYear`/`whereMonth`/`collect()` instead.
  - No `blade()` helper in SFC `:class` bindings — use ternary.

## Conventions

- **Scaffolding:** `php artisan make:* --no-interaction` (check `--help`); generic classes via `make:class`.
- **Formatting:** Pint preset `laravel`, `.editorconfig` 4 spaces / LF. Run `vendor/bin/pint --dirty --format agent` after touching PHP.
- **Static analysis:** Larastan level 7.
- **Tests:** Pest 5. Feature tests use `RefreshDatabase` (see `tests/Pest.php`); Unit suite is bare. Use `php artisan make:test --pest Name` (no `Feature/` prefix). Faker via `fake()` or `$this->faker`.
- **Docs lookup:** Use Boost `search-docs` before changing framework code (version-aware).
- **Boost MCP:** configured in `opencode.json` (`php artisan boost:mcp`); prefer `database-query`/`database-schema`/`get-absolute-url` over raw shell.
- **Skills:** `fortify-development`, `livewire-development`, `fluxui-development`, `pest-testing`, `tailwindcss-development`, `laravel-best-practices` (see `boost.json`).
