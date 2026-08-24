# Rental Agency Manager

A procedural PHP/PDO rental-agency management application with a multilingual public site, customer portal, and role-scoped professional back office.

## Requirements

- PHP 8.2+ with `pdo_mysql`, `fileinfo`, and sessions
- MariaDB 10.4+ or a compatible MySQL release
- Apache/IIS rules in this repository, or equivalent Nginx protections
- A protected, writable PHP error log

## Quick start

1. Configure the environment variables documented in `.env.example`. The project does not load `.env` files automatically.
2. Create an empty database and import the legacy `rental_car.sql` only when upgrading the original prototype.
3. Back up the database.
4. Run `php bin/migrate.php`.
5. Start locally with `php -S 127.0.0.1:8000 dev_router.php`; the router denies direct storage requests, including private inspection-photo staging/final files.
6. Open `/account/login.php`, `/backoffice/`, or `/portal/`.

For a fictional demonstration, set `APP_ENV=development` and a strong `DEMO_PASSWORD`, then run `php bin/seed_demo.php`.

See `IMPLEMENTATION_REPORT.md` and `docs/` for migration, testing, security, and demonstration instructions.

## Rental lifecycle (Phase 5B)

The approved rental lifecycle is implemented through Phase 5B.1 (contract lifecycle), 5B.2 (authoritative acknowledgements and signing), 5B.5 (protected inspection-photo bundles), 5B.3 (checkout and handover), and 5B.4 (check-in and return). Phase 5B.6 consolidation and release hardening are complete, and the complete Phase 5B rental lifecycle is approved and release-ready. Phase 5C.1 is the current implementation slice; later Phase 5C work remains future work.

The authoritative sequence is:

`reservation ready → contract signed → checkout → active rental → return inspection → check-in → completed rental`

Checkout and return each require exactly six protected photos: front, rear, left, right, interior, and dashboard. Checkout and check-in are transactional and idempotent, enforce role permissions and agency isolation, and support English, French, and Arabic interfaces with RTL rendering for Arabic.

Phase 5C.1 adds an explicit, idempotent vehicle-damage case workflow for completed damaged return inspections. It reuses the inspection and its protected photos, records an audited `open` or `resolved` case, and does not automatically change vehicle state or create finance, repair, maintenance, accident, fine, claim, or replacement workflows. Phase 5C.2 and later fleet/incident work remain future phases.

### Inspection-photo orphan cleanup

Run a deterministic, non-mutating review with `php bin/cleanup_inspection_photo_orphans.php --dry-run --limit=500`.
After reviewing only the controlled relative paths printed by that command, remove those stale unreferenced files with `php bin/cleanup_inspection_photo_orphans.php --execute --limit=500`.
