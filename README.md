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
5. Start locally with `php -S 127.0.0.1:8000`.
6. Open `/account/login.php`, `/backoffice/`, or `/portal/`.

For a fictional demonstration, set `APP_ENV=development` and a strong `DEMO_PASSWORD`, then run `php bin/seed_demo.php`.

See `IMPLEMENTATION_REPORT.md` and `docs/` for migration, testing, security, and demonstration instructions.
