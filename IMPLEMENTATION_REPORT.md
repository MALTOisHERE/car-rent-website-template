# Implementation report

## Executive summary

The original three-table procedural prototype now has an additive professional management layer while preserving PHP/PDO and the multilingual public website. The implementation introduces centralized security, an authoritative domain schema, explicit lifecycles, agency-scoped RBAC, transaction-safe allocation, and connected back-office/customer workflows.

No Git history was rewritten and no package, framework, commit, or push was introduced.

## Implemented phase coverage

- **Phase 0:** Environment PDO, safe errors/logging, secure sessions, timeouts, security headers, central auth/RBAC, CSRF, POST mutations, password policy, account lockout, hashed expiring reset tokens, session invalidation, safe image/document uploads, protected internal files, and legacy-handler retirement.
- **Phase 1:** Ordered additive migrations for the authoritative pluralized schema and an idempotent legacy import path.
- **Phase 2:** OWNER, AGENCY_MANAGER, RENTAL_AGENT, ACCOUNTANT, FLEET_AGENT, and CUSTOMER roles; activation/deactivation, agency assignment, scoping, and audit logs.
- **Phase 3:** Customers, company fields, normalized contacts, statuses, duplicate checks, age/licence checks, documents, additional drivers, history, search, pagination, and archival.
- **Phase 4:** Professional fleet records, categories, status history, secure images/documents, maintenance/document alerts, archival, and vehicle profitability.
- **Phase 5:** Date/time availability, maintenance exclusion, transactional creation, vehicle row locking, overlap checks at create/confirm/edit/extension/replacement, conflict logs, and pending expiry CLI.
- **Phase 6:** Quotes, reservations, explicit transitions, source tracking, cancellation/no-show/expiry states, pricing snapshots, allocation editing, and portal requests.
- **Phase 7:** Server-side base/duration/seasonal/partner/fee adjustment framework, manual discount authorization threshold, taxes, deposits, and stored breakdowns.
- **Phase 8:** Contract generation, numbering, multilingual snapshots, printable/PDF-ready output, signature status, immutable versioning, and amendments.
- **Phase 9:** Checkout/return inspections, six required photos in the UI, fuel/mileage/accessories/cleanliness/signatures, comparison, damage records, and vehicle status updates.
- **Phase 10:** Partial payments, balances, payment history, deposits, invoice items, printable invoices, and daily cash reconciliation.
- **Phase 11:** Categorized expenses, evidence upload, approval, vehicle/agency association, and estimated profitability.
- **Phase 12:** Maintenance lifecycle, vehicle unavailability, mileage/date scheduling, costs, next-service data, and document alerts.
- **Phase 13:** Fines, accidents, claims, costs, reports, replacement references, and vehicle damage status.
- **Phase 14:** Internal/email/WhatsApp-ready notifications, multilingual templates, history, status, and safe `wa.me` links.
- **Phase 15:** Owner/manager metrics, upcoming operations, alerts, revenue/expense/profitability/utilization reports, filters, printable views, and spreadsheet-safe CSV.
- **Phase 16:** Customer signup/login, profile, documents, availability, booking requests, history, status, modification/cancellation requests, and authorized contract access.
- **Phase 17:** Responsive shared professional back office with RTL-aware layout, status badges, forms, tables, filters, empty states, and removal of legacy admin handlers.
- **Phase 18:** Syntax-check utility, database-independent business-rule test, smoke checklist, security checklist, and migration instructions.
- **Phase 19:** Environment-gated fictional demo seeder, five staff roles, two agencies, customers, fleet states, active/late rentals, maintenance, contract, payment, deposit, invoice, expense, inspection, and damage scenario.

## Important technical decisions

- Procedural PHP remains the delivery model; reusable functions live under `app/`.
- Legacy `user`, `car`, and `reservation` tables are not dropped. New plural tables are authoritative after migration.
- Money uses `DECIMAL`; authoritative calculations occur on the server and are stored as snapshots.
- Reservation concurrency is handled by transactions, vehicle row locks, overlap queries, and rechecks at every allocation-changing transition.
- Historical business records use lifecycle statuses and archival rather than destructive deletion.
- Uploaded files receive random names, MIME/content validation, size limits, restrictive permissions, and private storage.
- Printable HTML is used for PDF-ready documents because no PDF library is installed.

## Migrations

1. `001_authoritative_schema.sql` — full additive domain schema.
2. `002_import_legacy_data.sql` — maps usable legacy users, cars, and reservations without dropping originals.
3. `003_operational_extensions.sql` — portal requests, cash registers, and multilingual notification templates.

Back up first, configure the environment, then run `php bin/migrate.php`. Review protected server logs on failure and restore the backup before retrying.

## Required environment variables

Database: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_CHARSET=utf8mb4`.

Application/runtime options are documented in `.env.example`. `.env` files are not automatically parsed; set variables in the PHP/web-server process.

## Test instructions

```powershell
php bin/php_syntax_check.php
php tests/business_rules.php
```

Then follow `docs/SMOKE_TEST.md` and `docs/SECURITY_CHECKLIST.md`. Runtime success must not be claimed until these execute in an environment with PHP and a migrated test database.

## Local migration and runtime verification (2026-07-12)

The migration blocker was caused by `tableExists()` preparing `SHOW TABLES LIKE :table_name`. MariaDB 10.4 with native PDO prepares does not accept a placeholder in that statement. The helper now validates identifiers and queries `information_schema.tables` for `table_schema = DATABASE()` with a bound table-name value. PDO emulation remains disabled.

The migration runner was also made explicit and fail-safe: files are sorted, `schema_migrations` is created with MariaDB-compatible InnoDB/utf8mb4 DDL, applied versions are skipped, and `002_import_legacy_data` runs only when all three legacy tables (`user`, `car`, and `reservation`) exist. A legitimate no-legacy skip is recorded, successful migrations are recorded idempotently, unreadable files and SQL failures print `FAILED` and exit non-zero, and technical exception details remain in protected logs.

Before correction, `rental_agency` contained the 30 authoritative tables from migration 001 plus `schema_migrations`; `001_authoritative_schema` was recorded, none of the legacy tables existed, and the three migration-003 tables were absent. All existing application tables were InnoDB with `utf8mb4_unicode_ci`. Migration 001 uses `CREATE TABLE IF NOT EXISTS` consistently and has no `ALTER TABLE`, so rerunning was safe and no database reset or destructive cleanup was needed.

All three SQL files were reviewed against MariaDB 10.4.32. They use compatible defaults, indexes, foreign-key ordering, `LONGTEXT` for JSON snapshots, and InnoDB/utf8mb4 DDL; no MySQL 8-only syntax or unsafe repeated `ALTER TABLE` was present, so no SQL migration file required modification.

Verified migration output:

```text
SKIP 001_authoritative_schema (already applied)
SKIP 002_import_legacy_data (legacy tables not present)
APPLY 003_operational_extensions
Migrations complete.
```

The immediate rerun and final quality-gate run returned already-applied `SKIP` lines for 001, 002, and 003 and exited successfully. The final database contains 34 InnoDB tables, 96 foreign keys, all three expected migration rows, and the expected operational-extension tables. The database default and every table use utf8mb4/`utf8mb4_unicode_ci`. No missing foreign-key target was found. `rental_app@localhost` has global `USAGE` and privileges on `rental_agency.*` only.

`php bin/seed_demo.php` completed successfully on repeated runs. A before/after key-count comparison remained `5|2|2|5|3|1|1|1` for demo staff users, agencies, customers, vehicles, reservations, contracts, payments, and invoices, confirming idempotency. Demo maintenance, deposit, expense, and inspection records were also verified. The five required staff accounts are active.

The PHP 8.2.12 built-in server was tested at `127.0.0.1:8000`. `/` resolved to the English public site, the login page returned 200 and rendered a CSRF token, the owner demo account authenticated with a persistent session, and the dashboard returned 200. Customers, vehicles, reservations (including the availability calendar), contracts, inspections, finance, invoices, expenses, maintenance, notifications, reports, users, and agencies each returned their own authenticated 200 response without a raw exception or SQL error. Anonymous `/portal/` access correctly redirected to login; customer-portal authorization remains available through customer signup/login. A Reports-page undefined-key warning found during smoke testing was corrected by defaulting the requested date values before validation.

Final automated results: 137 PHP files checked with 0 syntax failures; business-rule tests passed; `git diff --check` passed. There is one PDO construction point, native prepares remain enabled, no undefined `$conn` reference was found, and no database password was added to tracked files.

Exact local startup sequence (supply local secret values in the process environment):

```powershell
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='3306'
$env:DB_NAME='rental_agency'
$env:DB_USER='rental_app'
$env:DB_PASSWORD='<local rental_app password>'
$env:DB_CHARSET='utf8mb4'
$env:APP_ENV='development'
$env:APP_BASE_URL='http://127.0.0.1:8000'
$env:DEMO_PASSWORD='<strong local demo password>'
php bin\migrate.php
php bin\seed_demo.php
php -S 127.0.0.1:8000
```

## Demo instructions

See `docs/DEMO.md`. The seeder refuses `APP_ENV=production` and requires a strong `DEMO_PASSWORD` environment variable.

## Remaining limitations and pending depth

- Migration, seeding, session authentication, and the primary local HTTP routes are verified. Full browser interaction, concurrent multi-user allocation testing, outbound mail delivery, and production web-server configuration remain manual pilot checks.
- Printable HTML supports browser “Save as PDF”; native binary PDF generation would require an approved PDF library.
- Email uses configured PHP mail transport; production queue/retry and delivery-provider integration remain external work.
- WhatsApp support is click-to-open only, intentionally avoiding a paid API.
- Pricing rules implement a practical adjustment framework; country holiday calendars, stacking policies, and rate-contract precedence need deeper configuration UX.
- Inspection signatures are status records, not cryptographic/e-signature capture.
- Uploaded private files need an authorized download controller before end users can download evidence directly.
- Cash register covers opening, cash receipts, expected/actual balance, and close; dedicated cash-out voucher workflow can be expanded.
- Refunds, credit-note accounting, partner commission settlement, and general-ledger integration need deeper finance workflows.
- Accident photo galleries, insurance claim correspondence, and automated fine-to-contract matching are represented in the schema but have limited UI depth.
- Automated notification scheduling/retry and scheduled maintenance alert delivery require cron/task-scheduler configuration.
- Static public marketing pages remain template-heavy; core agency workflows were prioritized as requested.
- Old SQL dumps remain for migration compatibility and contain previously identified sensitive sample/history data; web access is blocked, but sanitizing archival fixtures remains recommended.

## Remaining risks

- Rotate any credential that was ever committed and consider a separately approved Git-history cleanup.
- Verify Apache/IIS rules or configure equivalent Nginx restrictions before deployment.
- Configure HTTPS, secure PHP error logging, backups, retention, monitoring, and restore tests.
- Complete all manual authorization and concurrency tests against a disposable migrated database before production use.
