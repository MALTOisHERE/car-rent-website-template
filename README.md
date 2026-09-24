# Aurevo

A procedural PHP/PDO rental-agency management application: a multilingual (EN/FR/AR, RTL-aware)
public marketing site, a per-agency branded storefront, a customer self-service portal, and a
role-scoped professional back office (fleet, reservations, contracts, checkout/check-in
inspections, finance, maintenance, reporting).

No framework, no Composer, no npm. Reusable logic lives in plain functions under `app/`.

## Requirements

- PHP 8.2+ with the `pdo_mysql`, `fileinfo`, and `session` extensions enabled
- MariaDB 10.4+ (or a compatible MySQL release)
- A writable, non-web-accessible location for the PHP error log
- Apache/IIS rules from this repository, or equivalent Nginx protections, for production

No Composer or npm install step is needed to run the application.

## 1. Get the code and configure the environment

```powershell
git clone <this-repository-url>
cd car-rent-website-template
copy .env.example .env
```

Open `.env` and fill in real values. At minimum, set the database connection:

| Variable | Meaning |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Your MySQL/MariaDB connection |
| `DB_CHARSET` | Leave as `utf8mb4` |
| `APP_ENV` | `development` locally; `production` on a real deployment |
| `APP_BASE_URL` | e.g. `http://127.0.0.1:8000` for local dev |
| `APP_TIMEZONE`, `APP_CURRENCY` | Defaults are fine for local dev |
| `DEMO_PASSWORD` | Only required if you plan to seed demo data (step 4) |

The rest of the variables in `.env.example` (session timeouts, login throttling, upload limits,
etc.) have sane defaults and don't need to change for local development.

`.env` is already `.gitignore`'d and must never be committed or deployed. `app/config.php` reads
it on every request when present, and a value there always overrides one already set as a real
process/web-server environment variable, so production should set real environment variables
instead of shipping a `.env` file.

## 2. Create the database

Create an empty database matching the credentials in `.env`, then apply the migrations:

```powershell
php bin/migrate.php
```

This runs every file in `database/migrations/` in order. It's idempotent, safe to re-run, and
purely additive (it never drops or destructively alters existing data), so re-running it after
pulling new migrations is always safe. Already-applied migrations print `SKIP`.

If you're upgrading an existing installation of the *original* prototype, import the legacy
`rental_car.sql` dump first and back up your database before running migrations; migration `002`
imports the legacy `user`/`car`/`reservation` tables into the new schema automatically.

## 3. Run the app locally

```powershell
php -S 127.0.0.1:8000 bin/dev_router.php
```

Use `bin/dev_router.php`, not a bare `php -S 127.0.0.1:8000` with no router. The router blocks
direct access to `/storage`, including private inspection-photo files, and transparently maps
the public site's bare top-level URLs (like `/cars.php` or `/`) to the files under `site/`. Running
without it exposes protected uploads.

Once it's running, open:

| URL | What it is |
|---|---|
| `http://127.0.0.1:8000/` | Public marketing site |
| `http://127.0.0.1:8000/account/login.php` | Staff and customer login |
| `http://127.0.0.1:8000/backoffice/` | Role-scoped SaaS admin (requires a staff login) |
| `http://127.0.0.1:8000/portal/` | Customer self-service portal |

## 4. Optional: seed demo data

To explore the app with realistic fictional data instead of an empty database, set `APP_ENV=development`
and a strong `DEMO_PASSWORD` in `.env` (this command refuses to run when `APP_ENV=production`),
then run:

```powershell
php bin/seed_demo.php
```

Every seeded account uses the `DEMO_PASSWORD` you set. A few of the seeded logins:

| Email | Role |
|---|---|
| `owner.demo@example.test` | Agency owner |
| `manager.demo@example.test` | Agency manager |
| `accountant.demo@example.test` | Finance/accountant |
| `fleet.demo@example.test` | Fleet manager |
| `agent.demo@example.test` | Front-desk agent |

See `.docs/DEMO.md` for the full scenario this data walks through.

## Tests and checks

There's no test framework here, just plain PHP scripts, run directly. Run these after making any
change before considering it verified; don't assume a change works just from reading the code:

```powershell
php bin/php_syntax_check.php       # lint every tracked PHP file
php bin/check_no_em_dash.php       # repo convention: no em dashes anywhere in tracked files
php tests/business_rules.php       # DB-independent domain/permission/i18n rules
```

The rest of `tests/*.php` are one-per-feature-slice integration tests; most need a real migrated
database, and some spin up their own local HTTP server. Check `.docs/IMPLEMENTATION_REPORT.md`
for which ones are DB-backed versus pure-logic before running one against a real database, since
several of them insert and mutate real rows.

`.docs/SMOKE_TEST.md` and `.docs/SECURITY_CHECKLIST.md` cover manual/HTTP verification beyond
what these scripts check.

## Project layout

- **`app/`** - the shared function library (no classes, no autoloader): config, database, auth/RBAC,
  sessions, CSRF, i18n, validation, uploads, and one `*_service.php` per domain module (reservations,
  contracts, finance, vehicles, customers, checkout/check-in, inspections, damage). Every mutation
  goes through its service, not inline SQL in a route file.
- **`site/`** - the public marketing site. One copy of each page (not one per language); language is
  resolved dynamically via `?lang=`/session state.
- **`account/`** - login/signup/logout/password reset, shared by staff and customers.
- **`backoffice/`** - the role-scoped professional admin. One file per module, every route
  permission-checked and agency-scoped.
- **`portal/`** - customer-facing self-service (contract view, document access).
- **`admin/`** - retired old admin template; kept only as a redirect shim for old bookmarks.
- **`database/migrations/`** - ordered, idempotent, additive-only SQL migrations.
- **`bin/`** - CLI entry points (migrate, seed, syntax check, cleanup jobs, the local dev router).
- **`tests/`** - the plain-PHP test scripts described above.
- **`.docs/`** - the authoritative deep-dive docs. Read the relevant section before touching a
  module you don't already understand; `.docs/IMPLEMENTATION_REPORT.md` alone is 650+ lines, so
  don't try to hold the whole thing in context for unrelated work.

See `CLAUDE.md` for the full architecture rundown, including the reasoning behind these
conventions and the security patterns every new route/service should follow.

## Public site and per-agency branding

Every public marketing page (`site/index.php`, `cars.php`, `selection.php`, `about.php`, and the
rest) lives once under `site/`, not once per language. `bin/dev_router.php` (locally) and
`.htaccess` (in production) transparently map bare top-level requests like `/cars.php` and `/` to
their file under `site/`, so the URLs seen by visitors never change.

Each agency can customize its own public storefront from `/backoffice/agency_branding.php`: a
logo, a three-color brand palette, and the actual wording shown on its public pages, section by
section and per language. The resolved agency for a request comes from the subdomain in the
`Host` header in production, or from a `?agency=your-agency-slug` query parameter while
`APP_ENV=development` (see `app/tenant.php`), so branding can be previewed locally without
configuring real subdomains.

## Rental lifecycle

The approved rental lifecycle covers contract signing, checkout/handover, active rental, return
inspection, and check-in:

`reservation ready → contract signed → checkout → active rental → return inspection → check-in → completed rental`

Checkout and return each require exactly six protected photos (front, rear, left, right,
interior, dashboard). Checkout and check-in are transactional and idempotent, enforce role
permissions and agency isolation, and are fully translated with RTL support for Arabic.

A vehicle-damage case workflow exists for completed damaged return inspections: it reuses the
inspection and its protected photos and records an audited `open`/`resolved` case, without
automatically creating finance, repair, maintenance, accident, fine, claim, or replacement
records.

### Inspection-photo orphan cleanup

Run a deterministic, non-mutating review with:

```powershell
php bin/cleanup_inspection_photo_orphans.php --dry-run --limit=500
```

After reviewing only the controlled relative paths it prints, remove those stale unreferenced
files with:

```powershell
php bin/cleanup_inspection_photo_orphans.php --execute --limit=500
```

## Troubleshooting

- **Uploads/photos 404 or feel "exposed"**: make sure you started the server with
  `bin/dev_router.php`, not a bare `php -S`.
- **Login works but the back office 403s everywhere**: the account's role/permissions weren't
  seeded correctly, or you're testing a customer account against `/backoffice/` (customers only
  get `/portal/`).
- **Migrations report `SKIP` for everything and the app still errors on missing tables**: you're
  probably pointed at a different database than the one migrations were run against; double-check
  `DB_NAME` in `.env`.
- **Changing `.env` has no effect**: confirm you edited the file at the project root (not
  `.env.example`). A value in `.env` always overrides a real environment variable of the same
  name, so if you're deploying under a process manager with opcode caching, a worker restart may
  be needed for the new value to be read.
