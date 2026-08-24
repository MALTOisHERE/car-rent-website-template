# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

A procedural PHP/PDO car-rental agency management platform ("Rental Agency Manager"): a
multilingual (EN/FR/AR, RTL-aware) public marketing site, a customer self-service portal, and a
role-scoped professional back office covering fleet, reservations, contracts, checkout/check-in
inspections, finance (payments/invoices/expenses/cash registers), maintenance, and reporting.
No framework and no Composer — reusable logic lives in plain functions under `app/`, loaded via
`require_once`. This codebase is the result of merging a long-running feature branch
(`feature/professional-car-rental-platform`, ~15,000 lines across 19 build phases) into `main`;
see "History" at the bottom before assuming a from-scratch OOP refactor exists here.

**`IMPLEMENTATION_REPORT.md` is the authoritative, extremely detailed build log** — phase by
phase architecture, migrations, verification evidence, and known limitations. `ROADMAP.md` is
the authoritative *product*-phase roadmap (distinct numbering from the implementation report's
internal Phase 0–19 history — don't confuse the two). Read the relevant section of
`IMPLEMENTATION_REPORT.md` before touching a module you don't already understand; don't try to
hold the whole 650+ line file in context for unrelated work.

## Running the site

Requirements: PHP 8.2+ with `pdo_mysql`/`fileinfo`/sessions, MariaDB 10.4+ (or compatible
MySQL), no Composer/npm needed.

1. Set the environment variables in `.env.example` in the actual process environment — this
   project does **not** parse `.env` files. Required: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`,
   `DB_PASSWORD`, `DB_CHARSET=utf8mb4`. Also commonly set: `APP_ENV`, `APP_BASE_URL`,
   `APP_TIMEZONE`, `APP_CURRENCY`, session/login-throttle tuning vars (see the file for the
   full list).
2. Create/back up the target database, then run `php bin/migrate.php` — applies
   `database/migrations/*.sql` in order, idempotently (already-applied versions print `SKIP`).
   Legacy `user`/`car`/`reservation` tables are **not dropped**; migration `002` imports them
   into the new authoritative schema when present.
3. Serve with `php -S 127.0.0.1:8000 dev_router.php` (not plain `php -S ...` — the router in
   `dev_router.php` blocks direct access to `/storage`, including private inspection-photo
   files; using the bare built-in server without it exposes protected uploads).
4. Entry points: `/` (public site, language-redirects to `/en|fr|ar/`), `/account/login.php`
   (staff + customer login), `/backoffice/` (role-scoped SaaS admin), `/portal/` (customer
   self-service).
5. Optional demo data: set `APP_ENV=development` and a strong `DEMO_PASSWORD`, then
   `php bin/seed_demo.php` (refuses to run with `APP_ENV=production`).

### Tests and checks

No test framework — plain PHP scripts, run directly:

```powershell
php bin/php_syntax_check.php          # lint every tracked PHP file
php tests/business_rules.php          # DB-independent domain/permission/i18n rules
php tests/customer_reservation_phase4.php   # DB-backed integration (needs a real migrated DB)
php tests/finance_phase5a.php
php tests/finance_phase5a_concurrency.php   # real concurrent-process race tests
# ...and the other tests/*.php files, one per feature slice — check IMPLEMENTATION_REPORT.md
# for which ones are DB-backed vs. pure-logic before running them against a real database.
```

Then `docs/SMOKE_TEST.md` and `docs/SECURITY_CHECKLIST.md` for manual/HTTP verification.
**Don't claim a feature works from reading the code alone** — this project's own convention
(see `IMPLEMENTATION_REPORT.md` throughout) is to actually run the syntax check, business-rule
tests, and relevant HTTP smoke checks before calling something verified.

## Architecture

### `app/` — the shared function library

Everything reusable lives here as plain functions (no classes, no autoloader), loaded through
`app/bootstrap.php` (config → HTTP helpers → session → CSRF → auth, then sets the exception
handler, security headers, secure session start, and app config) or the slightly higher-level
`app/application.php` that most entry points actually `require_once`. Key files: `database.php`
(PDO singleton, `tableExists()` etc.), `auth.php`/`auth_service.php` (`isAuthenticated()`,
`currentUserRole()`, `requirePermission()`, `requireAgencyAccess()`, RBAC), `session.php`
(`startSecureSession()`, idle/absolute timeouts), `csrf.php` (`verifyCsrfToken()`), `i18n.php`
(`t()`, catalogue loading, date/money formatting, safe language-switch URLs — catalogues are
`app/translations/{en,fr,ar}.php`, kept in exact key-order parity across all three), `validation.php`,
`upload.php`/`protected_file.php` (safe uploads + authorized private-file delivery, containment
checks against path traversal/symlink escape), and one `*_service.php` per domain module
(`reservation_service.php`, `contract_service.php`, `finance_service.php`,
`vehicle_service.php`, `customer_service.php`, `rental_checkout_service.php`,
`rental_checkin_service.php`, `inspection_photo_service.php`, `vehicle_damage_service.php`).
Each service is the single authoritative mutation boundary for its module — controllers should
delegate to a service, not inline SQL/business rules directly (this is the one architectural
rule this codebase is strict about; see `IMPLEMENTATION_REPORT.md`'s "Important technical
decisions").

### Route directories

- **`en/`, `fr/`, `ar/`** — the original public marketing site (car browsing/booking, static
  pages). Still template-heavy procedural PHP on the legacy `header_p.php`/`footer_p.php`
  per-language include pattern; deliberately not redesigned ("static public marketing pages
  remain template-heavy; core agency workflows were prioritized" — see report). Each language
  folder still has its own `css/`/`js`/`lib/` copies.
- **`account/`** — consolidated login/signup/logout/password-reset for both staff and
  customers, `require_once`-ing `app/application.php` directly (not the legacy per-language
  connect files).
- **`backoffice/`** — the role-scoped professional SaaS admin. `_layout.php` (shell:
  sidebar/topbar/flash/dialogs), `_navigation.php` (role-aware nav + icons), `_components.php`
  (escaped UI helpers: `pageHeader()`, `statusBadge()`, `pagination()`, `flashMessages()`,
  etc.), `assets/app.css`/`app.js` (design tokens, RTL-aware, mobile nav, confirm dialogs). One
  file per module (`vehicles.php`, `reservations.php`, `contracts.php`, `finance.php`,
  `customers.php`, `maintenance.php`, `expenses.php`, `invoices.php`, `reports.php`, `users.php`,
  `agencies.php`, etc.) — every route calls `requirePermission()` and is agency-scoped.
- **`portal/`** — customer-facing self-service (contract view, document access).
- **`admin/`** — the **old** AdminBSB-template admin panel from before this platform existed.
  Now just a redirect shim (`admin/index.php` → `require app/bootstrap.php; safeRedirect('../backoffice/');`)
  kept only for old bookmarks/links. Don't add features here — it's retired.

### Database

`database/migrations/001`–`007`, applied in order by `bin/migrate.php`, additive only (no
destructive `ALTER`/`DROP` of existing data), each idempotent and safe to rerun. The legacy
3-table schema (`user`/`car`/`reservation`) is preserved permanently for backward compatibility
and migration `002` imports it into the new pluralized/agency-scoped schema — don't drop the
legacy tables. Money is `DECIMAL`, computed server-side, stored as snapshots (not recomputed
live from mutable pricing rules). Concurrency-sensitive operations (reservation allocation,
finance ledger writes) use transactions + row locks (`FOR UPDATE`) + overlap rechecks at every
allocation-changing transition, not just at creation — follow that pattern for any new
concurrent-write code; see the Phase 5A concurrency remediation in `IMPLEMENTATION_REPORT.md`
for a worked example of why (a real repeatable-read race was found and fixed this way).

### i18n / RTL

`app/i18n.php` + `app/translations/{en,fr,ar}.php`. Translation strings are stored unescaped —
HTML-escape at the render boundary, not in the catalogue. Missing keys fall back to English,
then to a readable label derived from the key itself (never a hard error). Arabic renders
`lang="ar" dir="rtl"`; layout CSS uses logical properties (`margin-inline-start` etc.), not
hardcoded left/right. The language switcher only ever changes an allowlisted set of URL
parameters (`lang`, and route-local filters like `page`/`status`/`search`) — it strips CSRF
tokens, passwords, and anything not explicitly allowlisted before building the switch link.

### Security conventions to follow

- CSRF: every state-changing POST calls `verifyCsrfToken()` (see `app/csrf.php`).
- File delivery: protected files (inspection photos, customer documents, finance evidence) go
  through a dedicated controller that re-authorizes, checks agency scope, resolves the realpath
  against both the upload root and the target file (rejecting traversal/symlink escape),
  verifies detected MIME against an allowlist, and returns a **generic 404** for every failure
  case (missing, archived, wrong agency, bad file) — never a distinguishable error. Follow this
  pattern for any new protected-file route; see `app/protected_file.php`.
- Errors: `display_errors` is off; exceptions are logged server-side and shown a generic
  message (`app/bootstrap.php`'s exception handler). Never leak `$e->getMessage()` to the
  browser.
- Passwords/tokens: hashed (never plaintext), reset tokens are hashed+expiring. Session IDs
  regenerate on privilege change; sessions have both idle and absolute timeouts (env-tunable).

## History

This repo's `main` branch used to contain a from-scratch OOP refactor (Composer + PSR-4 `src/`,
`pages/` controllers) built in an earlier part of this same session, on top of the *original*
3-table prototype. That work was superseded when `feature/professional-car-rental-platform` —
an independently-developed, much larger rebuild of the same prototype — was merged into `main`
as the new authoritative codebase (merge commit, both branches preserved as parents). The
superseded OOP refactor is preserved for reference on the `backup/oop-refactor-before-merge`
branch but is **not** part of `main` and should not be assumed to exist when reading files here.
