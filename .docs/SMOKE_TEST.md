# Manual smoke and business workflow tests

Run `php bin/php_syntax_check.php` and `php tests/business_rules.php` first.

## Phase 5A automated finance verification

Run with the configured application database environment. The suites use only random
`P5A_TEST_<id>` fixtures and must finish with a successful cleanup audit.

```powershell
php bin/php_syntax_check.php
php tests/business_rules.php
php tests/vehicle_phase3.php
php tests/customer_reservation_phase4.php
php tests/finance_phase5a.php
php tests/finance_phase5a_concurrency.php
php tests/migration_phase5a_recovery.php
php tests/phase5a_http_smoke.php
php tests/phase5a_cleanup_audit.php
php bin/migrate.php
php bin/migrate.php
node --check backoffice/assets/app.js
git diff --check
```

`tests/finance_phase5a_concurrency.php` uses independent PHP processes and a
synchronization barrier for duplicate payment, competing-balance payments,
refund/refund, refund/close, payment/close, duplicate invoice target, invoice number,
expense decision, and deposit terminal-event races. Sequential simulation is not used.

`tests/migration_phase5a_recovery.php` always performs database-independent structural
assertions. Its destructive recovery scenarios create only random disposable databases.
Exit code 2 means the configured account lacks `CREATE DATABASE`; fresh and partial-DDL
recovery remain privileged migration checks and must not be reported as passed.

The Phase 5A senior-review remediation also requires the migration recovery suite to
exercise wrong column type, nullability/default, generated expression, unique-index,
same-named non-unique-index, cascading-FK, reordered-FK, permissive-CHECK, and
wrong-definition/correct-column-count fixtures. The suite must report the static
assertions separately and exit 2 when disposable-database authority is unavailable.
The final database-integrity remediation additionally requires exact validation of
the five composite agency FKs: invoice/customer, invoice/reservation,
payment/reservation, payment/invoice, and expense/vehicle. Recovery fixtures cover
all five cross-agency mismatches plus same-named cascading, local-order, and
referenced-order conflicts. The authoritative parent UNIQUE keys must exist exactly;
nullable child relationships remain valid NULLs.
`tests/finance_phase5a.php` and `tests/finance_phase5a_concurrency.php` must prove
that completed replays return the original entity, create no second business row,
leave no reserved allocation (or explicitly void an unused replay allocation), and
that invoice-scoped payments cannot exceed either reservation or invoice net
remaining. They must also cover concurrent `createAndIssueInvoiceFromReservation()`
calls and verify one issued invoice and one authoritative idempotency result.

After Phase 5A ledger rows exist, never re-enable an old mutable finance controller.
The finance write cutover requires migration 006 and the new ledger tables. A code
rollback that cannot satisfy that guard must be treated as finance read-only until a
compatible forward deployment is restored.

## Phase 5A manual acceptance

1. Use separate browsers for OWNER, AGENCY_MANAGER, RENTAL_AGENT, ACCOUNTANT, and FLEET_AGENT; verify the documented finance visibility and direct-route denials.
2. Confirm a rental agent can submit only an in-balance payment and cannot view its history or evidence afterward.
3. Repeat the finance pages in EN, FR, and AR; visually verify LTR/RTL layout, mixed-direction references, money, tables, and print output.
4. Complete keyboard-only and screen-reader checks for forms, errors, status messages, evidence links, and printable invoices.
5. At desktop, tablet, and mobile widths, verify payment, deposit, invoice, expense, and cash-register detail pages.
6. On a disposable privileged database host, execute every fresh and partial-DDL recovery scenario before production rollout.

## Security and access

1. Confirm unauthenticated access to `/backoffice/` redirects to the central login.
2. Sign in with each role and confirm direct URL access is denied outside its permissions.
3. Submit a POST form without `_csrf`; confirm HTTP 419.
4. Leave a session idle beyond `SESSION_IDLE_TIMEOUT`; confirm reauthentication is required.
5. Fail login repeatedly; confirm the account is temporarily locked without disclosing whether another email exists.
6. Request a password reset for an existing and nonexistent address; confirm identical browser responses.
7. Reset a password; confirm the token cannot be reused and existing sessions are invalidated.

## Primary rental workflow

1. Create a customer with valid age/licence data and add an additional driver/document.
2. Create a vehicle and upload a valid image; reject executable, oversized, or MIME-mismatched files.
3. Create a pending reservation and verify its server-generated price snapshot.
4. Attempt an overlapping reservation for the same vehicle; it must be rejected.
5. Confirm the reservation, generate a contract, mark it signed, and create an amendment.
6. Record advance payment and deposit; create and print an invoice.
7. Record checkout with all six photos; confirm vehicle becomes rented.
8. Record return, compare mileage/fuel/late time, and record damage if applicable.
9. Return or partially retain the deposit with a reason.
10. Confirm dashboard revenue and profitability reflect recorded payments/approved expenses.

## Fleet workflow

1. Schedule maintenance overlapping a requested rental; availability must reject the period.
2. Start maintenance; vehicle must become `maintenance`.
3. Complete maintenance; vehicle must return to `available` if still in maintenance state.
4. Upload an expiring insurance/inspection document and confirm dashboard alert visibility.

## Customer portal

1. Register a fictional customer, update the profile, and upload a document.
2. Search availability by date/time and submit a booking request.
3. Submit modification and cancellation requests.
4. Resolve requests in the back office and verify authorized contract access.

## Phase 4 automated and HTTP/security verification

Run with the configured test database environment. The suites create only random `P4_TEST_<run-id>` fixtures and must finish with a successful cleanup audit.

```powershell
php bin/php_syntax_check.php
php tests/business_rules.php
php tests/vehicle_phase3.php
php tests/customer_reservation_phase4.php
php tests/phase4_http_smoke.php
php tests/phase4_cleanup_audit.php
php bin/migrate.php
node --check backoffice/assets/app.js
git diff --check
```

`tests/customer_reservation_phase4.php` covers schema definitions, server-derived customer history, lifecycle RBAC, strict operational vehicle-state transitions, cross-agency and archived vehicle rejection, deterministic extension, replacement rollback, opposite-direction ordered-lock concurrency, legacy-tax remediation, protected-file validation, two-process allocation concurrency, and cleanup. `tests/phase4_http_smoke.php` covers the owner/manager/rental-agent routes, prohibited accountant/fleet/customer routes, crafted sensitive actions, portal/staff document IDOR, archived-document behavior, MIME/private headers, an NTFS junction or POSIX symlink containment escape, EN/FR LTR, AR RTL, runtime logs, and cleanup.

`tests/migration_phase4_recovery.php` always runs its database-independent remediation assertions, then requires permission to create and drop nine random disposable databases for fresh, compatible partial-identifier, incompatible-identifier, incompatible-primary-key, incompatible-foreign-key, and incompatible-CHECK scenarios. It must never be pointed at a business schema as a substitute. Exit code 2 means that the privileged scenarios remain pending because the configured account lacks disposable-database privileges; it is not a partial-DDL pass.

## Phase 4 manual acceptance

1. In two browsers, attempt to extend and replace the same reservation; confirm the stale actor receives a conflict and the committed record remains coherent.
2. Check customer list/form/workspace and reservation list/form/workspace at desktop, tablet, and mobile widths.
3. Check day/week planning at tablet width for horizontal scrolling and on mobile for chronological reservation, maintenance, and unavailability cards.
4. Repeat core pages in Arabic and confirm logical alignment, mixed-direction references, dates, money, and controls remain readable.
5. Complete keyboard-only navigation and a screen-reader walkthrough of tabs, filters, planning alternatives, forms, status messages, and protected-download links.
6. Confirm no raw upload path appears in page source, browser URLs, downloaded filenames, or error responses.

