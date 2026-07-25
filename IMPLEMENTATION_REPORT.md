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

## Product Phase 4 — customer and reservation redesign (2026-07-22)

### Delivered architecture

Phase 4 preserves the procedural PHP/PDO architecture and existing URLs while introducing `app/customer_service.php`, `app/reservation_service.php`, and `app/protected_file.php` as transaction, authorization, pricing, lifecycle, agency-scope, and protected-delivery boundaries. Existing domain entry points delegate to the reservation service for compatibility. Controllers remain server-rendered and use the shared Phase 1–3 layout and components.

`ROADMAP.md` is now the authoritative six-phase product roadmap. The earlier Phase 0–19 list in this report remains historical internal implementation coverage and does not redefine product-phase numbering.

Migration `005_customer_reservation_workspace.sql` is additive and retry-oriented for MariaDB 10.4. It upgrades customer/reservation optimistic timestamps, adds nullable explicit `tax_rate`, creates the composite customer/agency key, creates and validates `customer_status_history`, uses a PERSISTENT generated baseline slot with a unique index, installs non-cascading foreign keys and checks, adds listing/planning indexes, performs deterministic legacy tax backfill only when provable, inserts missing migration baselines with `WHERE NOT EXISTS`, and leaves version recording to `bin/migrate.php` after the complete SQL file succeeds.

The customer module now has scoped filtered pagination, a dedicated complete create/edit form, optimistic updates, duplicate and driver-eligibility checks, lifecycle-sensitive manager actions, server-derived immutable history, additional drivers, document archive/restore, separate protected downloads, metrics, and permission-aware reservations, finance, incidents, requests, and history tabs. Rental agents retain ordinary create/edit work and are denied block, unblock, and archive in both UI and backend.

The reservation module now has scoped filtered pagination, dedicated creation, a tabbed workspace, contextual allocation editing, status-sensitive actions, authorized cross-agency return checks, deterministic extension/replacement, manager-only commercial override, manager-only legacy-tax resolution, related-module summaries, finance visibility through existing helpers, and bounded server-rendered day/week planning. Planning uses batched queries, desktop/tablet timelines, mobile chronological cards, explicit maintenance/unavailability blocks, accessible text, logical CSS, and optional progressive JavaScript only.

Extension and replacement reuse integer cents/basis points and the existing rental-day rule. Agreed daily rate, discount percentage, explicit tax rate, currency, options, fees, and fixed rule adjustment are preserved without querying current pricing rules. Same-date replacement preserves the exact stored total unless an authorized explicit commercial override is supplied. Overrides require owner/manager permission, a positive daily rate, a reason, a versioned snapshot, and complete before/after commercial audit data. Legacy tax resolution accepts only 0.00–100.00 with at most two decimals, locks and stale-checks the scoped reservation, updates the snapshot and audit, and leaves all historical totals, tax amount, payments, and balance unchanged.

Staff and customer document routes perform their own authorization before passing metadata to the session-agnostic protected-file service. Delivery realpaths both upload root and file, rejects traversal and symlink/junction escape, requires an ordinary file, verifies detected MIME against stored MIME and the allowlist, synthesizes the download name, sends private/no-store/nosniff headers, and returns the same generic 404 for inaccessible records and files. Raw storage paths are not rendered.

All Phase 4 UI keys are present in EN, FR, and AR with identical order and catalogue parity. Language switching preserves only allowlisted IDs, filters, dates, views, and tabs; it removes CSRF and sensitive values. EN/FR remain LTR and AR remains RTL.

### Verification classification

**AUTOMATED — EXECUTED**

- `php bin/php_syntax_check.php`: 156 PHP files, 0 failures before the final documentation-only update; rerun in the final gate below.
- `php tests/business_rules.php`: passed, including roles, translations, safe language switching, deterministic preserved-term pricing, legacy NULL-tax guard, and strict percentage boundaries.
- `php tests/vehicle_phase3.php`: passed; Phase 3 schema, gallery, financing, ordering, audit, and stale-write behavior remain intact.
- `php tests/customer_reservation_phase4.php`: passed; schema audit, agency/RBAC, lifecycle, deterministic pricing, legacy-tax behavior, protected files, true concurrency, and cleanup passed.
- True concurrency used two independent PHP processes and a barrier. Both passed initial validation before GO; exactly one committed, exactly one received a safe conflict, and exactly one overlapping reservation remained before exact fixture cleanup. The test exposed a repeatable-read race in the former non-locking overlap query; production conflict checks now use current `FOR UPDATE` reads inside transactions, and the concurrency suite passed three consecutive isolated reruns plus the final gate run.
- `php tests/phase4_cleanup_audit.php`: `P4_TEST fixture audit: users=0, agencies=0`.
- `node --check backoffice/assets/app.js`: passed.
- `git diff --check`: passed.

**HTTP/SECURITY — EXECUTED**

- `php tests/phase4_http_smoke.php`: passed for role routes, prohibited routes, login sessions, customer/staff document IDOR, generic archived 404, private download headers, rental-agent sensitive-action 403s, manager legacy-tax remediation, out-of-range tax rejection, cross-agency denial, EN/FR LTR, AR RTL, runtime error scanning, and cleanup.
- Protected containment was exercised with a real NTFS junction from inside the upload root to a file outside it; the protected route returned the generic 404 and did not serve the file.
- Runtime logs contained no warning, fatal error, SQLSTATE, PDO exception, unhandled exception, password-hash marker, session identifier, or database-password marker.

**MANUAL — EXECUTED WITH EVIDENCE**

- Source/diff review confirmed Phase 4 is confined to customer/reservation services and routes, planning, protected customer documents, translations, tests, and documentation; no Phase 5 module redesign was introduced.
- Rendered HTTP responses were inspected programmatically for expected route ownership, status, direction, protected headers, uniform not-found bodies, absent raw storage paths, and absent sensitive manager controls for rental agents.
- Migration 005 was executed against the existing Phase 3 database, then rerun. The current final verification returned `SKIP` for 001–005 and `Migrations complete.` with exit code 0. No credential value was printed or stored.

**MANUAL — PENDING USER ACCEPTANCE**

- Two-browser human concurrency behavior and conflict messaging.
- Real desktop/tablet/mobile visual review, including planning scrolling/cards and pixel-level regressions.
- Arabic visual typography and mixed-direction content review in a real browser.
- Keyboard-only and screen-reader walkthrough; no WCAG conformance claim is made.

### Migration evidence and reserves

The existing Phase 3 upgrade executed migration 005 successfully, and its immediate rerun completed successfully. The final positive verification output was:

```text
SKIP 001_authoritative_schema (already applied)
SKIP 002_import_legacy_data (already applied)
SKIP 003_operational_extensions (already applied)
SKIP 004_vehicle_detail_media (already applied)
SKIP 005_customer_reservation_workspace (already applied)
Migrations complete.
```

`tests/migration_phase4_recovery.php` was implemented for fresh 001–005, immediate rerun, partial-DDL continuation, post-baseline retry, definition audit, baseline uniqueness, and exact disposable-database cleanup. In the configured environment it exited 2 before creating any database because `rental_app` lacks `CREATE DATABASE`. Fresh-database and destructive partial-schema simulations therefore remain pending; they were not misrepresented as passed and the business database was not altered to manufacture evidence.

### Phase 4 senior-review remediation (2026-07-22)

The review identified five narrow correctness gaps. Migration 005 could add an unindexed `AUTO_INCREMENT` identifier before its primary key; foreign-key checks compared only relationship columns; CHECK validation accepted any same-named constraint; reservation replacement locked only the requested vehicle; and operational lifecycle transitions did not validate the locked vehicle state or assert conditional state-update success.

Migration identifier recovery now classifies the live `id` and primary-key state before repairing anything. A missing identifier and primary key are added in one MariaDB-compatible `ALTER`; a compatible existing identifier is upgraded together with its missing primary key where required; an exact complete definition is skipped; and incompatible identifier types, nullability, or primary keys fail closed before the remaining partial-table repair. No recovery branch drops a structure or deletes or rewrites a history row.

Each Phase 4 history foreign key now derives a complete descriptor from `TABLE_CONSTRAINTS`, `KEY_COLUMN_USAGE`, and `REFERENTIAL_CONSTRAINTS`: local table, ordered local columns, referenced schema/table, ordered referenced columns, and normalized non-cascading update/delete rules. Exact matches skip, absent names are created, and same-named incompatible definitions—including cascade rules, different schemas, or different order—signal a schema mismatch without dropping the production constraint. Each CHECK now uses `CHECK_CONSTRAINTS.CHECK_CLAUSE`; normalization removes only identifier quoting and whitespace, preserving literals and operators. The four exact approved expressions are compared conservatively, so permissive, unrelated, or unverifiable same-named checks fail closed.

Reservation replacement continues to lock the scoped reservation first, then locks the unique current/requested vehicle IDs with one ascending `ORDER BY id ASC FOR UPDATE` query. Both locked rows must belong to the origin agency. A changed target must be unarchived and exactly `available`; invalid operational states are rejected; overlap and maintenance checks run after the locks; and reserved/rented state transfers use conditional updates whose affected row must equal one. A bounded retry handles transient MariaDB serialization/deadlock victims without exposing database details; exhaustion becomes the translated safe conflict error. Any failed check or mutation rolls back the reservation and both vehicle states.

Transitions to `confirmed`, `deposit_paid`, `ready`, and `active` now lock and load the assigned vehicle, validate origin agency, existence, archival state, operational status, and the expected `available` or `reserved` state, then recheck reservation and maintenance conflicts under the transaction locks. Confirmation and activation perform their vehicle mutation before the reservation status update and require exactly one affected row. Existing cancellation, no-show, expiry, and completion behavior remains unchanged.

`tests/customer_reservation_phase4.php` now proves confirmation success; rejection for maintenance, damaged, blocked, sold, retired, archived, missing, and cross-agency vehicles; invalid activation rollback; maintenance-conflict recheck; failed replacement state preservation; cross-agency and invalid replacement rejection; successful active replacement state transfer; and two simultaneous opposite-direction replacements through independent workers. The ordered lock contract and affected-row guard are also asserted structurally. The suite passed with exact fixture cleanup.

`tests/migration_phase4_recovery.php` now runs database-independent assertions before requesting database privileges and defines nine isolated disposable scenarios: fresh installation; missing identifier; identifier without auto-increment; identifier without a primary key; auto-increment identifier without a primary key; incompatible identifier type; incompatible primary key; incompatible same-named cascading foreign key; and incompatible same-named permissive CHECK. Successful scenarios audit exact identifier, primary key, foreign keys, rules, CHECK clauses, survivor rows, baseline uniqueness, retry behavior, and immediate rerun. Failure scenarios require exit code 1, no recorded 005 version, retained conflicting definitions, and retained history data. The configured account still lacks `CREATE DATABASE`, so only the structural assertions executed and passed; the privileged DDL scenarios remain pending and are not claimed as passed.

Final remediation verification:

```text
PHP syntax: 156 files checked, 0 failures
Business rules: passed
Phase 3 vehicle regression: passed
Phase 4 integration: passed in three consecutive final runs, including strict transitions, replacement rollback, and opposite replacement concurrency
Phase 4 HTTP/security: passed, including runtime scan and cleanup
Phase 4 cleanup audit: users=0, agencies=0
Migration recovery: structural assertions passed; privileged disposable-database scenarios pending (CREATE DATABASE unavailable)
Current database migration rerun: 001–005 SKIP, Migrations complete, exit 0
JavaScript syntax: passed
Git diff check: passed
```

No password, hash, cookie, session identifier, token, or database credential was written to remediation output or tracked files. Temporary Phase 4 sessions, workers, runtime markers, uploads, and HTTP logs were removed. No permission was broadened, no Phase 5 work was started, and no commit or push was performed.

**Senior-review remediation verdict: REMEDIATION COMPLETE WITH RESERVES.** The only remediation reserve is execution of the already implemented disposable-schema matrix under a suitably privileged test-only database account.

### Remaining limitations and verdict

The implementation is complete within Phase 4’s application scope. Reserves are limited to disposable fresh/partial migration execution under a suitably privileged test-only account and genuine human browser/device/accessibility acceptance. Finance, contracts, inspections, incidents, notifications, the customer portal appearance, the Phase 3 gallery, and all Phase 5 redesign work remain authoritative and unchanged apart from permission-aware links or the minimal protected customer-document link.

**Phase 4 verdict: IMPLEMENTATION COMPLETE WITH RESERVES.**

## Back-office SaaS foundation — Phase 1 (2026-07-12)

### Objective and starting state

Phase 1 established a professional reusable shell without changing the procedural PHP architecture or deeply redesigning business modules. The confirmed starting UI had a flat 21-link sidebar, minimal top bar, a single compact CSS file, no shared component layer, no mobile navigation drawer, no dropdown/dialog/drawer behavior, embedded creation forms, raw role/status labels, limited RTL rules, and several permission/visibility mismatches.

### Files and shell architecture

The shared foundation now consists of:

- `backoffice/_layout.php`: authenticated application shell, skip link, sidebar, top bar, flash slot, profile/language menus, notification entry, global confirmation dialog, and drawer host.
- `backoffice/_navigation.php`: grouped, role-aware navigation and reusable SVG icon rendering.
- `backoffice/_components.php`: escaped procedural UI helpers for page headers, breadcrumbs, actions, badges, empty states, pagination, action menus, flash messages, responsive tables, and money display.
- `backoffice/assets/app.css`: design tokens and responsive/RTL-aware component styles.
- `backoffice/assets/app.js`: mobile navigation, dropdowns, confirmation dialog, drawer focus management, Escape/click-outside closing, alert dismissal, table accessibility enhancement, allocation-form hydration, and double-submit prevention.

The shell uses the groups Overview, Rentals, Customers, Fleet, Finance, Commercial, Analytics, and Administration. Groups with no permitted children are omitted. Allocation editing and customer files remain backward-compatible direct routes but are no longer permanent top-level utilities; they are contextual navigation/actions. The top bar derives user identity from the authenticated session and exposes the current agency context without hardcoded user data.

### Components and design system

New helpers: `pageHeader()`, `breadcrumb()`, `primaryAction()`, `secondaryAction()`, `statusBadge()`, `roleBadge()`, `emptyState()`, `pagination()`, `actionMenu()`, `flashMessages()`, and `responsiveTableWrapper()`.

CSS tokens now cover spacing, typography, radii, shadows, surfaces, text, borders, semantic states, focus, sidebar size, content width, and control height. Shared styles cover primary/secondary/quiet/danger/icon buttons, cards, metrics, page headers, toolbars, filters, form controls, responsive tables, semantic badges, role badges, alerts, breadcrumbs, pagination, dropdowns, dialogs, drawers, tabs, detail headers, thumbnails, loading/disabled states, and mobile foundations. Embedded create cards remain functional but are visually demoted and linked from top-right page actions.

All 21 active shell routes now call both `backofficeHeader()`/`backofficeFooter()` and `pageHeader()`. Existing business handlers, form actions, redirects, and route names were preserved. The allocation editor's inline JavaScript was moved into the shared asset.

### Permission changes

| Concern | Old behavior | New behavior | Roles/routes | Reason |
|---|---|---|---|---|
| Pricing | Used `reservations.manage`; rental agents could manage rules | Added `pricing.manage` to agency managers; owner wildcard remains | Rental agent denied `/backoffice/pricing.php`; owner/manager allowed | Least privilege for commercially sensitive pricing |
| Dashboard finance | Every `dashboard.view` role received revenue, balance, and deposit queries/cards | Finance cards and queries require `payments.manage` or `reports.financial` | Hidden from rental/fleet roles; visible to owner/manager/accountant | Prevent financial KPI leakage |
| Finance history | `payments.create` exposed payments, deposits, and invoices | Payment creation remains available; history/deposits require `payments.manage`; invoices require `invoices.manage` | Rental agent gets payment-entry-only view | Separate creation duty from ledger visibility |
| Commercial documents | Contract access also exposed invoice totals | Invoice query/section requires `invoices.manage` | Rental agents retain quotes/contracts without invoice ledger | Protect finance information |
| Agency creation | Manager saw a form that POST rejected | Form/action render only for owners; direct POST owner check remains | Manager may view agencies but cannot see creation UI | Align visible UI and backend authorization |

Business-rule tests now cover the owner wildcard across every protected permission used by the back office; manager pricing and finance; rental-agent payment creation without finance history or pricing; accountant finance and reports without pricing; and fleet isolation from finance and pricing. The same tests exercise production visibility helpers for owner-only agency creation, financial-dashboard metrics, payment history/deposits, and invoice sections.

### Responsive, accessibility, and RTL foundations

- Desktop: sticky full sidebar, sticky top bar, bounded content container, full page actions.
- Tablet (`max-width:1050px`): off-canvas logical-side sidebar, hamburger, backdrop, body scroll lock, close control, and focus containment.
- Mobile (`max-width:760px`, plus compact rules at 480px): stacked page headers/actions, full-width primary actions, single-column grids, vertical filters, compact top bar, and overflow constrained to table wrappers.
- RTL: `dir="rtl"` remains authoritative; logical inset/margin/padding/border/text alignment is used; sidebar/drawer transforms reverse; menu alignment and breadcrumb direction are handled.
- Accessibility: skip link, `aria-current`, labeled menu controls, `aria-expanded`/`aria-controls`, visible focus styles, keyboard Escape and focus return, modal semantics, minimum control sizing, semantic badge text plus color, labeled scroll regions, and table header scopes at runtime.

These are foundation checks, not a WCAG compliance claim. Desktop/tablet/mobile breakpoints and interaction hooks were verified in delivered CSS/JS and through rendered HTML; automated pixel-level browser screenshots remain a manual visual QA item.

### Verification results

The senior-review remediation corrected two shared interaction defects. Confirmation-enabled forms retain the accessible `HTMLDialogElement.showModal()` path, use `window.confirm(message)` only when that API is unavailable, submit only after confirmation, preserve the clicked submitter, and use a one-shot `WeakSet` bypass to prevent recursive handling without disabling later confirmations. Loading buttons still hide their text, but the pseudo-element spinner now uses a dedicated visible color token and contrasting track; the existing reduced-motion rule remains active.

Final gate output, rerun against the configured User-scope `rental_app` environment on 2026-07-17:

```text
php bin/php_syntax_check.php
Checked 139 PHP files; failures: 0

php tests/business_rules.php
Business rule tests passed: domain rules, role permissions, and visibility helpers.

php bin/migrate.php
SKIP 001_authoritative_schema (already applied)
SKIP 002_import_legacy_data (already applied)
SKIP 003_operational_extensions (already applied)
Migrations complete.

invalid-credential subprocess
The service is temporarily unavailable.
Exit code: 1

node --check backoffice/assets/app.js
JavaScript syntax check passed.

git diff --check
Git diff check passed.
```

The final verification loaded the current User-scope variables into isolated subprocesses without printing their values. The positive migration run used `rental_app`, returned all three already-applied `SKIP` lines, and exited 0. A separate negative run shadowed `DB_PASSWORD` with a one-time invalid value only inside that subprocess; it emitted only the generic service-unavailable message and exited 1. The real User-scope environment was never modified. No password, credential hash, cookie, CSRF token, or session identifier was written to the final evidence.

The full HTTP matrix was rerun on 2026-07-17 and recorded 155/155 passing checks: both shared assets returned 200; all 105 direct role/route checks matched their expected 200 or 403; every permitted route contained the shared shell and page heading; login/logout and UI-isolation checks passed for all five roles; EN and FR rendered LTR and AR rendered RTL for every role; and the server/runtime-content scan found zero PHP warnings, fatal errors, SQL errors, PDO exceptions, unhandled exceptions, or raw exception pages. `phase1-smoke-tests.txt` retains the earlier detailed sanitized matrix, while the current rerun result and exit code are included in `phase1-tests.txt`.

Role smoke matrix:

| Role | Direct routes | Login/shell/logout | Financial isolation | Pricing/agency checks |
|---|---:|---|---|---|
| Owner | 21 permitted at 200, 0 prohibited | Pass | Dashboard metrics and finance history visible | Pricing and owner-only agency form visible |
| Agency manager | 21 permitted at 200, 0 prohibited | Pass | Dashboard metrics and finance history visible | Pricing visible; agency-create form absent |
| Rental agent | 12 permitted at 200, 9 prohibited at 403 | Pass | Metrics/history/deposits/invoices absent; payment entry retained | Pricing and agency creation absent |
| Accountant | 6 permitted at 200, 15 prohibited at 403 | Pass | Dashboard metrics and finance history visible | Pricing and agency creation absent |
| Fleet agent | 5 permitted at 200, 16 prohibited at 403 | Pass | Dashboard metrics and finance history absent | Pricing and agency creation absent |

The five demo password hashes and login-state fields were temporarily replaced only for deterministic local authentication, restored from in-memory values in `finally`, and never written to the evidence or report. Temporary authentication audit rows, server logs, and session storage were also cleaned in `finally`. No password, cookie, token, session identifier, or credential hash is present in the smoke evidence or tracked files.

### Phase 1 limitations carried forward

Phase 1 intentionally leaves deep module redesign for later. `actionMenu()` supports links only; destructive or state-changing POST/CSRF actions need a separate component API in a later approved phase. Embedded forms are still present, although visually demoted and linked from page actions. Existing large tables still primarily use responsive overflow; module-specific mobile record cards are prepared in the design system but not yet populated. Full translation coverage, translated validation/status/catalogue labels, advanced visual regression testing, and all real browser/screen-reader combinations remain outstanding. The dialog fallback and spinner are code- and syntax-verified, but their browser-native confirmation prompt and pixel-level animation remain manual cross-browser visual checks.

The translation and RTL correction work identified here was implemented in the Phase 2 section below. Deep module redesign, the vehicle-detail/gallery project, and native-browser visual QA remain outside this phase.

## Back-office internationalization foundation — Phase 2 (2026-07-18)

### Objective and architecture

Phase 2 provides a maintainable English, French, and Arabic internationalization layer for the authenticated back office while preserving the procedural PHP architecture, route names, POST actions, database schema, business workflows, and Phase 1 permission boundaries.

`app/i18n.php` now owns language validation, catalogue loading/caching, current-language and explicit-language translation, English and readable-key fallback, safe scalar placeholder interpolation, translated roles/statuses/booleans/validation messages, deterministic date/date-time/money formatting, and allowlisted language-switch URLs. Translation strings remain unescaped in the catalogues; HTML escaping occurs at rendering boundaries. The EN, FR, and AR catalogues each contain 663 string keys with identical key coverage and namespaced groups including `common.*`, `shell.*`, `nav.*`, `role.*`, `status.*`, `field.*`, `action.*`, `page.*`, `section.*`, `validation.*`, `message.*`, `confirm.*`, `empty.*`, `option.*`, and `auth.*`. Legacy Phase 1 aliases remain for backward compatibility.

Missing keys resolve from the selected language to English, then to a readable label derived from the key. Explicit-language translation is used for notification fallback content so a message selected as French or Arabic does not inherit the operator's UI language. Placeholder interpolation accepts scalar values only; output is still escaped where rendered.

### Shared shell, routes, and formatting

The shared layout, navigation, components, login/logout pages, and all 21 active back-office routes now use catalogue-backed page headings, descriptions, breadcrumbs, card titles, labels, buttons, filters, table headings, empty states, role/status labels, validation messages, flash messages, and confirmations. Database-owned customer/agency names, notes, emails, phones, registrations, reservation/contract/invoice references, identifiers, and currency codes remain untranslated.

Dates and times are formatted only at rendering time. English uses forms such as `17 Jul 2026, 10:45`; French uses `17 juil. 2026 à 10:45`; Arabic uses Arabic month names with an unambiguous time. Money remains server-calculated and deterministic: English `MAD 1,250.00`, French `1 250,00 MAD`, and Arabic `1,250.00 MAD`. Dynamic codes, references, email/phone values, dates, registrations, and money use `<bdi>` or the shared bidi-isolation classes so mixed-direction values remain readable.

The language switcher replaces only `lang`, preserves validated route-local filters (`page`, `status`, `search`, `q`, supported dates and scoped identifiers), and drops CSRF values, passwords, redirects, tokens, and unsupported parameters. URLs remain escaped at output.

### RTL, accessibility, and JavaScript

Arabic renders with `lang="ar" dir="rtl"`; English and French render LTR. The Phase 1 logical layout was retained and extended with logical text alignment, logical dropdown positioning, RTL drawer/sidebar behavior, direction-aware breadcrumb chevrons, viewport-bounded action menus, Arabic navigation typography, and unicode-bidi isolation for mixed-direction controls and values. No duplicate RTL stylesheet was added.

`backoffice/assets/app.js` has no independent translation catalogue. Server-translated data attributes supply the remaining runtime table label, while the existing translated confirmation dialog supplies confirmation text. Dialog fallback, clicked-submitter preservation, double-submit protection, dropdown behavior, drawer focus management, and Escape handling remain unchanged.

### Automated tests and verification

`tests/business_rules.php` now verifies required namespaced key parity and scalar values across all languages; English/readable missing-key fallback; role and status coverage; interpolation; safe escaping at the rendering boundary; deterministic money/date/date-time output including invalid input; and language-switch preservation/removal rules.

Final database-backed verification results:

```text
Translation keys: EN 663, FR 663, AR 663
PHP syntax: 139 files checked, 0 failures
Business rules: passed (domain, permissions, visibility, translations, localization, safe language switching)
Migration: all three versions SKIP (already applied), exit 0
JavaScript syntax: passed
Git diff check: passed
HTTP smoke: 1,326 passed, 0 failed
Runtime/server error scan: 0
Shared physical assets: app.css and app.js both 200 (2/2)
```

The HTTP matrix covered OWNER, AGENCY_MANAGER, RENTAL_AGENT, ACCOUNTANT, and FLEET_AGENT in EN, FR, and AR. Owner and manager retained 21 permitted routes; rental agent retained 12 permitted and 9 prohibited routes; accountant retained 6 permitted and 15 prohibited routes; fleet agent retained 5 permitted and 16 prohibited routes. Every permitted response retained the shell and page heading, translated navigation/role/status content, and the expected direction. Every prohibited route returned 403. Finance visibility, manager/owner pricing access, and owner-only agency creation remained unchanged. Login pages were also checked in all three languages.

### Files changed and limitations

Phase 2 changes are confined to `app/i18n.php`, the three catalogues, shared authentication/error text, `backoffice/_layout.php`, `_navigation.php`, `_components.php`, both shared assets, all 21 active route files, login/logout, `tests/business_rules.php`, and this report. No schema, migration, package, framework, route, form action, commit, or push was introduced.

No WCAG compliance or pixel-perfect RTL claim is made. Browser screenshot comparison, real-device Arabic typography, screen-reader testing, outbound email rendering, and public/portal/print-template translation remain manual or later-scope work. Only two physical files exist under `backoffice/assets/`, so the asset check is truthfully 2/2 rather than an asserted 3/3.

During verification, an initial temporary-login harness failed while restoring its in-memory password snapshots because its cleanup statement supplied extra PDO bindings. Recovery was then completed explicitly and transactionally for exactly the five isolated `.demo@example.test` staff accounts using the configured User-scope `DEMO_PASSWORD`. No password or hash was displayed or written to tracked evidence. Real credential login subsequently passed for OWNER, AGENCY_MANAGER, RENTAL_AGENT, ACCOUNTANT, and FLEET_AGENT, followed by the complete 1,326-check matrix. Login-state fields were restored, smoke-specific authentication audit rows were removed, and temporary cookies, sessions, server logs, and runners were cleaned.

## Vehicle workspace and protected gallery — Phase 3 (2026-07-20)

### Delivered architecture

Phase 3 adds an additive professional vehicle workspace while retaining `backoffice/vehicles.php` as the authoritative fleet list and lifecycle route. Each accessible fleet row now links to `backoffice/vehicle_detail.php?id=<vehicle_id>`, which provides allowlisted overview, profile, media, reservation, maintenance, inspection, document, incident, finance, and history tabs. Tabs, queries, counts, and deep links are rendered only when the current role already has the corresponding module permission. Existing related modules remain authoritative for their mutations and accept a validated optional `vehicle_id` context filter.

`database/migrations/004_vehicle_detail_media.sql` adds the agency-scoped `vehicle_media` table, a composite vehicle/agency foreign key, user audit references, captions, alternative text, protected storage metadata, dimensions, stable ordering, archival fields, and a generated unique primary slot that enforces at most one active primary image per vehicle. Existing `primary_image_path` values are backfilled and retained as a compatibility mirror. The migration also upgrades `vehicles.updated_at` to microsecond precision for optimistic concurrency and adds the composite vehicle/agency candidate key required by the media foreign key. The migration is retry-tolerant after partial DDL and does not remove legacy data.

`app/vehicle_service.php` centralizes scoped vehicle loading, tab validation, complete profile validation, optimistic stale-write rejection, row locking, category and unique identifier checks, mileage-correction reasoning, profile change auditing, status history, and gallery operations. Profile editing covers specifications, category, commercial rates/deposit, mileage and allowance, acquisition values, financing type, and monthly financing. Registration and VIN are normalized; model year, counts, mileage, dates, and monetary values are range checked; future purchase dates are rejected; submitted financing type is mandatory and restricted to `owned`, `loan`, or `lease`; loan and lease require a strictly positive monthly amount; owned vehicles always clear that amount; and a mileage reduction requires an audit-logged correction reason. Existing database rows with NULL, empty, or otherwise unrecognized financing values render as `owned` in the edit form for backward compatibility, without silently accepting an empty or invalid submitted value. Only changed profile fields are included in the audit entry.

Gallery upload accepts verified JPEG, PNG, and WebP files through the existing private upload root. Image dimensions are now captured. A request is limited to 10 images and a vehicle to 50 active images. Multi-file uploads are transactionally all-or-nothing at the database level and newly written files are removed on failure. The first uploaded image in a new gallery becomes primary; captions and alternative text are editable; keyboard-accessible move-up/move-down controls maintain ordering; explicit primary selection updates both the enforced media flag and compatibility path; and archiving retains the established file and promotes the next active image. Restoration always appends the image with `is_primary=0`, never invokes primary selection, never changes an existing primary, and never populates `vehicles.primary_image_path`. When the active gallery has no primary, the compatibility path remains NULL until an authorized user explicitly selects one. All operations require `vehicles.manage`, CSRF, current-agency access, and audit logging.

`backoffice/vehicle_media.php` is the only new delivery path. It authenticates and authorizes `vehicles.view` before lookup, joins the media row to the same-agency vehicle, rejects archived/missing records, resolves only files beneath `storage/uploads`, revalidates the actual MIME type, and emits a fixed safe inline filename, exact length, `nosniff`, and private cache headers. Storage paths and original filenames are not exposed in its URL.

The workspace is fully represented in the EN, FR, and AR catalogues, now at 756 keys each with exact key-order parity. Language switching preserves only an allowlisted `tab`, and inaccessible requested tabs are normalized before switch links render. EN and FR remain LTR; AR remains RTL. Mixed-direction registrations, references, dates, and monetary values use the existing isolation helpers. Responsive vehicle headers, horizontal tabs, metrics, and gallery cards extend the existing logical-property design system; media ordering is not drag-only.

### Permissions and finance isolation

- OWNER and AGENCY_MANAGER receive the full editable workspace, related summaries, and finance tab.
- FLEET_AGENT receives full profile/gallery editing and permitted maintenance, inspection, document, and incident summaries, with no finance query, count, tab, link, or placeholder.
- RENTAL_AGENT receives read-only profile/gallery access and permitted reservation/inspection summaries. Mutation fields and maintenance, document, incident, and finance tabs are absent.
- ACCOUNTANT and CUSTOMER remain denied because they do not have `vehicles.view`; anonymous requests redirect to the account login route.
- Profitability is deliberately labelled as an estimate and uses paid vehicle reservation payments minus approved, non-archived vehicle expenses. No related-module permission was broadened.

### Migration and verification

The configured User-scope database environment applied the migration successfully:

```text
SKIP 001_authoritative_schema (already applied)
SKIP 002_import_legacy_data (already applied)
SKIP 003_operational_extensions (already applied)
APPLY 004_vehicle_detail_media
Migrations complete.
```

The final rerun returned all four migrations as `SKIP (already applied)` and exited 0. Information-schema and transaction-rollback integration checks proved the required media columns, composite agency foreign key, active-primary uniqueness, compatibility-path synchronization, metadata editing, exact-set ordering, archival/restoration, cross-agency insert rejection, and stale profile rejection without retaining test database records.

Final automated gates:

```text
PHP syntax: 143 files checked, 0 failures
Business rules: passed (including vehicle profile validation and safe vehicle tab switching)
Phase 3 database integration: passed
Migration rerun: all four versions SKIP, exit 0
JavaScript syntax: passed
Git diff check: passed
Role/language/security HTTP matrix: 88 passed, 0 failed
Full 21-route role/language regression: 707 passed, 0 failed
Runtime/server error scan: 0
Translation keys: EN 756, FR 756, AR 756
```

The focused HTTP matrix covered all five staff roles in EN, FR, and AR; expected 200/403 behavior; LTR/RTL direction; editable versus read-only profile rendering; finance isolation; all ten owner tabs; all six contextual module filters; invalid-vehicle and missing-media 404 responses; permission-first media denial; anonymous redirection; mutation RBAC; and the existing HTTP 419 CSRF failure contract. The complete pre-existing 21-route matrix was then rerun across the same five roles and three languages: all 707 status, direction, safe-content, and shared-asset checks passed with no runtime errors. A separate real multipart lifecycle verified upload, authenticated 200 image delivery with the correct MIME/private cache headers, caption/alternative-text update, archive followed by delivery 404, restoration followed by delivery 200, and zero runtime errors. Its media row, audit entries, stored file, sessions, runners, and test-only artifacts were removed afterward. No password, credential hash, cookie, session identifier, CSRF value, or token was printed or added to tracked files.

### Senior-review remediation (2026-07-21)

The Phase 3 senior review found two narrow compliance mismatches. First, `restoreVehicleMedia()` appended archived media correctly but called `setPrimaryVehicleMedia()` when no active media existed, which made restoration an implicit primary-selection action and populated the compatibility path. That call was removed. Restoration now only clears archival fields, forces `is_primary=0`, assigns the next active sort position, and writes the restore audit entry. It never modifies `vehicles.primary_image_path`; an empty gallery therefore remains without a primary until an authorized user explicitly selects one. Restoring into a populated gallery preserves its existing primary and compatibility path.

Second, profile validation used an allowlist helper with a NULL fallback, causing submitted empty or invalid financing values to be silently stored as NULL. Financing now uses a single explicit `owned`/`loan`/`lease` allowlist and rejects every missing or invalid submitted value. The edit form is required and offers exactly those three choices. A display-only compatibility helper maps legacy NULL, empty, or unrecognized stored values to `owned`; this fallback is not used for submitted data. `owned` always clears monthly financing, while `loan` and `lease` reject empty, zero, negative, or invalid amounts and require a strictly positive amount.

No schema change or new migration was required. `tests/business_rules.php` covers every financing value/amount combination and legacy display normalization. `tests/vehicle_phase3.php` verifies the same production service behavior against the database and proves restoration with an existing primary, restoration into an empty gallery, preserved NULL compatibility path, append ordering, restore audit logging, and explicit primary selection afterward. All database tests use rollback transactions.

Remediation verification:

```text
PHP syntax: 143 files checked, 0 failures
Business rules: passed
Phase 3 integration: passed
Migration: 001–004 SKIP (already applied), exit 0
JavaScript syntax: passed
Git diff check: passed
Focused remediation HTTP: 68 passed, 0 failed
Runtime/server error scan: 0
Translation keys: EN 756, FR 756, AR 756; exact parity
```

The focused remediation HTTP run reconfirmed editable access for OWNER, AGENCY_MANAGER, and FLEET_AGENT; read-only access for RENTAL_AGENT; denial for ACCOUNTANT; finance isolation; EN/FR LTR; AR RTL; the mandatory financing control with exactly three options; legacy NULL rendering as selected `owned`; and absence of PHP warnings, fatal errors, SQL errors, PDO exceptions, or unhandled errors. Existing RBAC, CSRF, agency scoping, audit logging, upload validation, protected media delivery, and related-module ownership were not broadened or redesigned.

No commit or push was performed, and Phase 4 was not started.

### Phase 3 limitations

- Images are retained in private local storage without resizing, thumbnails, EXIF processing, CDN/object storage, or derivative generation. Those changes require separate storage and retention decisions.
- Archive is intentionally reversible and never physically deletes established media. Permanent deletion and orphan-file retention cleanup remain a separately approved operational feature.
- Related modules are context-filtered and summarized, not redesigned; their existing routes and permissions remain authoritative.
- Profitability is an operational estimate, not general-ledger accounting, and depends on complete payment and approved-expense capture.
- The legacy `vehicles.primary_image_path` mirror remains intentionally until all public/portal consumers migrate to protected media IDs.
- Automated HTML, direction, RBAC, and interaction contracts passed; pixel-level browser comparison, real-device Arabic typography, keyboard walkthrough, and screen-reader testing remain manual acceptance work.

## Phase 5A — Finance Core (2026-07-22)

Phase 5A introduces a single finance write boundary in `app/finance_service.php`. Payment,
adjustment, excess allocation, deposit, invoice/credit-note, expense, cash-register, cash
movement, idempotency, number-allocation, evidence, audit, agency, and permission rules are
enforced in that service. Compatibility functions in `app/operations.php` delegate only;
the complete PHP-source mutation scan found no finance controller mutation outside the
service, migrations, or test setup/cleanup.

Migration 006 was applied to the configured database and a subsequent verification run
returned versions 001–006 as already applied with exit code 0. Its preflight rejects
multiple open agency registers, duplicate active reservation invoices, cross-agency
finance links, impossible monetary relationships, and incompatible partial table/column
definitions before cutover. Additive DDL and deterministic legacy backfills are used;
ambiguous legacy deposit history is retained unresolved and receives no fabricated event.
Migration version 006 is recorded only after the migration runner completes every
statement successfully.

Every allocated invoice, credit-note, payment, adjustment, deposit-event, and movement
number is retained in `financial_number_allocations` as reserved, consumed, or voided.
Business rejections void safe unused allocations, while ambiguous failures may leave a
reserved gap; no allocation is deleted or reused. Operation-scoped idempotency keys are
stored and locked. Authoritative balance and register rows are locked for mutations, and
deadlock/serialization retries are bounded.

Explicit excess tender is represented by one immutable payment for the received amount,
one exact `excess_reallocation` adjustment, and one dedicated deposit. Reservation and
invoice paid totals subtract that adjustment. Cash tender produces separate `payment_in`
and `deposit_in` movements, preventing the excess from being counted as revenue.
Adjustments and deposit events are append-only. Issued invoices are immutable operational
records; draft cancellation and capped credit notes replace destructive edits. Expenses
require a separate decision actor, except for a reasoned and audited OWNER exception, and
rejected expenses do not create cash movements. Cash close uses a locked timestamp
boundary and records any explained difference as a movement.

The common financial evidence endpoint authorizes permission and agency before lookup,
then rejects missing, archived, cross-agency, missing-file, invalid-MIME, traversal, and
containment-escape cases through the same not-found response. It never exposes raw paths
or original filenames. RENTAL_AGENT can create only a normal within-balance payment with
optional proof; the role cannot read payment history/evidence or access adjustment,
deposit, invoice, expense, or cash workflows. ACCOUNTANT access remains agency-scoped and
finance-specific; FLEET_AGENT and CUSTOMER have no Phase 5A back-office access.

### Phase 5A verification classification

- AUTOMATED — EXECUTED: PHP syntax (169 files, 0 failures), business rules, Phase 3 vehicle integration, Phase 4 customer/reservation integration, Phase 5A finance integration, JavaScript syntax, migration reruns, and Git diff validation.
- AUTOMATED — EXECUTED: nine real independent-process concurrency races passed: duplicate payment, competing balance, refund/refund, refund/close, payment/close, invoice target, numbering, expense decision, and deposit terminal event.
- HTTP/SECURITY — EXECUTED: role access, crafted-action denial, agency IDOR, protected evidence, EN/FR/AR direction and invoice print, runtime-log scan, and test cleanup passed.
- AUTOMATED — EXECUTED: cleanup audit reported zero `P5A_TEST` users, agencies, allocations, and artifacts.
- PRIVILEGED MIGRATION — PENDING: structural recovery assertions passed, but the configured account cannot create disposable databases; fresh and partial-DDL scenarios exited 2 and are not claimed as passed.
- MANUAL — EXECUTED WITH EVIDENCE: none.
- MANUAL — PENDING USER ACCEPTANCE: real-browser responsive/Arabic visual review, keyboard and screen-reader walkthrough, and production-like privileged migration rehearsal.

The finance cutover is deliberately fail-closed. Migration 006 must exist before new
writes. After any Phase 5A ledger activity, old mutable finance controllers must never be
re-enabled; a code rollback without the Phase 5A service must default to finance read-only
operation until a compatible forward deployment is restored.

### Phase 5A remaining limitations

- Privileged fresh-install and partial-DDL recovery scenarios remain pending because this database account lacks disposable-database creation authority.
- Browser-level responsive, Arabic visual, keyboard, and screen-reader acceptance remains manual.
- Financial evidence uses protected local storage; lifecycle retention, malware scanning, object storage, and backup policy remain deployment responsibilities.
- Phase 5A is an operational subledger, not a general ledger, tax filing system, bank reconciliation engine, or payment-gateway integration.
- Legacy finance rows with unknowable opening history remain explicitly unresolved rather than reconstructed.
- CLI tests in this sandbox require a writable `session.save_path`; HTTP tests create an isolated writable session directory.

No commit or push was performed. Phase 5B, 5C, and 5D were not started.

### Phase 5A senior-review remediation (2026-07-23)

The senior review identified four correctness gaps in the original Phase 5A
implementation. Migration 006 previously treated same-named objects and column
counts as sufficient evidence, numbered commands allocated numbers before checking
completed idempotency, invoice-scoped payments capped only the reservation in some
paths, and the compatibility invoice command used two independently idempotent
commands. These behaviors could accept an incompatible partial schema, leave an
unexplained reserved number, bypass an invoice net balance, or create a second
compatibility result after a replay.

The remediation is confined to `database/migrations/006_finance_core.sql`,
`app/finance_service.php`, `tests/migration_phase5a_recovery.php`,
`tests/finance_phase5a.php`, `tests/finance_phase5a_concurrency.php`, this report,
and `docs/SMOKE_TEST.md`.

Migration 006 now keeps expected column, index, foreign-key, CHECK, engine, and
collation descriptors and compares existing information-schema metadata before
altering or creating objects. Column comparisons include type, unsignedness,
nullability, default, EXTRA, generated expression, and datetime precision. Index
comparisons include name, ordered columns, uniqueness, and type. Foreign-key
comparisons include local and referenced schema/table/column order and normalized
RESTRICT/NO ACTION rules. CHECK clauses are read from `CHECK_CONSTRAINTS` and
normalized only for harmless quoting/whitespace. Missing definitions remain
creatable; incompatible or unverifiable definitions fail closed; no unexpected
production structure is dropped or rewritten.

Numbered finance operations retain the safe preallocation strategy but now void
every unused allocation on a completed idempotent replay with the explicit reason
`Idempotent replay; allocation unused`. Consumed numbers are immutable and never
reused. The tests cover normal and excess payments, adjustments, deposits and
deposit events, invoice issue and credit notes, manual cash movement, cash close,
and expense decisions, including concurrent duplicate requests.

Invoice-scoped normal and excess payments lock the reservation and selected invoice,
require the invoice to be same-agency, issued, non-draft, non-cancelled, and
eligible, and cap payable tender to the smaller reservation/invoice net remainder.
Credit notes are included in the invoice net balance; explicit excess sends only
the excess to the dedicated deposit workflow, and audit data records both balances,
payable, and excess.

`createAndIssueInvoiceFromReservation()` is now one authoritative command with one
caller idempotency row and one invoice-number allocation. A replay returns the same
issued invoice, creates no draft or issue attempt, and voids any unused replay
allocation. Separate draft and issue APIs retain independent idempotency.

Strengthened tests add exact migration metadata assertions and disposable scenarios
for wrong column type, nullability/default, generated expression, unique index,
same-named non-unique index, cascading or reordered foreign keys, permissive CHECK,
and a wrong definition with the expected column count. Finance tests assert zero
reserved/consumed allocation growth on replay (or an explicit replay void), invoice
net-payment caps, compatibility replay identity, and concurrent duplicate behavior.

Remediation verification currently recorded:

```text
php -d session.save_path=storage tests/finance_phase5a.php: PASS
php -d session.save_path=storage tests/finance_phase5a_concurrency.php: PASS
php -d session.save_path=storage tests/migration_phase5a_recovery.php:
  structural assertions: PASS
  privileged disposable-database scenarios: PENDING (exit 2; CREATE DATABASE authority unavailable)
php -d session.save_path=storage bin/php_syntax_check.php: PASS (170 files, 0 failures)
php -d session.save_path=storage tests/business_rules.php: PASS
php -d session.save_path=storage tests/vehicle_phase3.php: PASS
php -d session.save_path=storage tests/customer_reservation_phase4.php: PASS
php -d session.save_path=storage tests/phase5a_http_smoke.php: PASS
php -d session.save_path=storage tests/phase5a_cleanup_audit.php: PASS (zero fixtures/artifacts)
php -d session.save_path=storage bin/migrate.php (twice): PASS (001-006 SKIP; exit 0)
node --check backoffice/assets/app.js: PASS
git diff --check: PASS
```

All non-privileged final gates listed above have now been rerun successfully. No
privileged migration scenario is claimed as passed when the account cannot create
disposable databases. Browser visual, keyboard, and screen-reader acceptance remain
manual limitations. No commit or push was performed, and Phase 5B, 5C, and 5D were
not started.

### Phase 5A final database-integrity remediation (2026-07-24)

Migration 006 now adds and validates these five composite agency foreign keys:

- `fk_invoices_customer_agency`: `invoices(customer_id, agency_id)` → `customers(id, agency_id)`
- `fk_invoices_reservation_agency`: `invoices(reservation_id, agency_id)` → `reservations(id, agency_id)`
- `fk_payments_reservation_agency`: `payments(reservation_id, agency_id)` → `reservations(id, agency_id)`
- `fk_payments_invoice_agency`: `payments(invoice_id, agency_id)` → `invoices(id, agency_id)`
- `fk_expenses_vehicle_agency`: `expenses(vehicle_id, agency_id)` → `vehicles(id, agency_id)`

Before any FK DDL, the migration requires the exact authoritative composite
UNIQUE keys `uq_customers_id_agency`, `uq_vehicles_id_agency`,
`uq_reservations_id_agency`, and `uq_invoices_id_agency`. Existing invoice,
payment, and expense rows are preflighted for cross-agency mismatches; any
mismatch raises a generic migration failure and no historical row is updated,
deleted, or relinked. Nullable relationship columns remain nullable.

Existing same-named constraints are checked through information_schema for
constraint type, local table and ordered columns, referenced schema/table and
ordered columns, and UPDATE/DELETE rules. Only RESTRICT and NO ACTION are
accepted. Cascading, reordered, wrong-schema, wrong-table, non-FK, or otherwise
incompatible definitions fail closed; missing compatible constraints are added
without dropping unexpected production constraints.

Recovery tests now cover exact presence of all five FKs, five cross-agency
mismatch fixtures, cascading definitions, wrong local order, wrong referenced
order, and prior Phase 5A structural scenarios. Finance integration tests also
assert persisted same-agency relationships across payments, invoices, deposits,
deposit events, adjustments, expenses, and related parent entities.

Final verification on 2026-07-24: PHP syntax (170 files), business rules,
Phase 3, Phase 4, finance, concurrency, HTTP/security smoke, cleanup audit,
JavaScript syntax, both migration reruns, and diff checks passed. Migration
recovery structural assertions passed; its disposable-database scenarios remain
PENDING with exit code 2 because CREATE DATABASE authority is unavailable.
No commit or push was performed, and Phase 5B, 5C, and 5D were not started.

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
