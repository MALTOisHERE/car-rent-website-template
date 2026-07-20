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
