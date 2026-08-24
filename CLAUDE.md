# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

A car rental website ("REIM(S) CARS") that started as plain procedural PHP with no framework,
no package manager, and no build step, and is being incrementally migrated to a small OOP core
(Composer + PSR-4, under `src/`) behind the existing pages. The public site is still fully
triplicated across three language directories, plus a separate admin back-office built on the
AdminBSB Material Design template — see "Refactor status" below for what has and hasn't moved
onto the new architecture yet.

## Running the site

Needs a webserver with PHP 8.1+, Composer, and a MySQL/MariaDB database:

1. Run `composer install` (or `composer dump-autoload` — there are no third-party packages yet,
   just the PSR-4 autoloader) to generate `vendor/autoload.php`. **Required**: `assets/connectDB.php`
   now bootstraps through `bootstrap.php`, which requires the Composer autoloader, so nothing
   in `en/`/`fr/`/`ar/`/`admin/` that touches the database will load without this step.
2. Import `rental_car.sql` into a `rental_car` database.
3. Set the environment variables listed in `.env.example` (`DB_HOST`, `DB_PORT`, `DB_NAME`,
   `DB_USER`, `DB_PASSWORD`, `DB_CHARSET`) in the PHP process/webserver environment — the app
   does **not** read `.env` files itself, so these must be exported another way (e.g. Apache
   `SetEnv`, php-fpm pool config, or the shell before starting PHP's built-in server).
4. Serve the repo root, e.g. `php -S localhost:8000` from the project root.
5. Visit `/` — it redirects to `/en/`, `/fr/`, or `/ar/` based on `$_SESSION['lang']` (English
   by default).

There are no lint, test, or build commands in this repo.

## Refactor status

An OOP core is being introduced incrementally (see conversation history / commit log for the
phase plan) without breaking the existing procedural pages:

- **Done**: `src/` (PSR-4, namespace `App\`) with `Infrastructure\Database` (PDO singleton),
  `Domain\{Car,User,UserRole,Reservation,Transmission}` (typed entities — `UserRole` is the one
  place that normalizes the inconsistently-stored `user.role` column), `Repository\{Car,User,Reservation}Repository`
  (centralizes SQL that used to be copy-pasted per page), `Service\{AuthService,LoginThrottle,BookingService,RegistrationService,PasswordResetService}`
  (business logic extracted from `login.php`'s inline anti-bruteforce/captcha code and the
  divergent booking/registration logic in `selection.php`/`process_booking.php`/`confirm_reservation.php`/`signup.php`),
  `Support\{Session,Flash,Csrf,Validator}`, and `I18n\Translator` + `lang/{en,fr,ar}.php` (seed
  translation keys, not yet wired into any page). `assets/connectDB.php` is now a shim that
  bootstraps Composer/session via `bootstrap.php` and delegates to `Infrastructure\Database`,
  so all existing pages keep working unchanged against the same `$mysqlconnection`/
  `reportDatabaseError()` interface.
- **Not yet migrated**: every actual page (`en/`/`fr/`/`ar/*.php`, `admin/*.php`) still contains
  its own inline SQL/session logic and has not been rewired to use the classes above. The plan
  is to consolidate `en/`/`fr`/`ar/` into one page set backed by `I18n\Translator` (starting
  with the booking flow: `cars.php` → `selection.php` → `process_booking.php` → `reserve.php`/`confirm_reservation.php`),
  then auth (`login.php`/`signup.php`/`send_reset_link.php`/`reset_password.php`), then the
  admin panel last.
- **Known pre-existing bugs to fix when migrating those pages** (found during the Phase 1
  review, not yet fixed since the pages themselves are untouched): `send_reset_link.php` and
  `reset_password.php` reference an undefined `$conn` variable (should be `$mysqlconnection`) —
  password reset is currently fatally broken; `admin/index.php`/`admin/cars.php` never call
  `session_start()` before `admin_header.php`'s role check runs, so that guard is likely dead;
  the admin edit-reservation modal echoes DB values unescaped into HTML attributes; no page has
  CSRF protection on state-changing forms; `admin/add_car.php`'s file upload has no
  extension/MIME whitelist; `ar/reserve.php` has diverged in logic (not just translation) from
  `en/reserve.php`.

## Architecture

### Language triplication, not i18n

The site is **not** internationalized via a shared template + translation files. `en/`, `fr/`,
and `ar/` are near-complete copies of the same set of PHP pages (`cars.php`, `login.php`,
`reserve.php`, `selection.php`, `confirm_reservation.php`, etc.), each with hardcoded strings
in its language. **When fixing a bug or changing behavior in one language directory, check
whether the same fix is needed in the other two** — this is the single most important thing to
remember when editing this codebase. `index.php` and `logout.php` at the repo root are the only
shared entry points; they redirect into the per-language folder based on
`$_SESSION['lang']` (set by `header_p.php` when `?lang=xx` is passed).

### Database bootstrap: `assets/connectDB.php` → `App\Infrastructure\Database`

`assets/connectDB.php` is included via `include("../assets/connectDB.php")` from `en/` and
`admin/` pages, but it is now a thin shim (see "Refactor status"): it requires the root
`bootstrap.php` (Composer autoload + `session_start()`), then delegates to
`App\Infrastructure\Database::connection()` for the actual PDO setup and assigns the result to
the legacy global `$mysqlconnection` so existing procedural code keeps working unchanged.

- `Database::connection()` (`src/Infrastructure/Database.php`) reads
  `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASSWORD`/`DB_CHARSET` from the process
  environment (`getenv()`), validates them, and hard-fails with a generic 500 message if
  anything is missing/invalid — never exposes config details to the browser. It's a lazy
  singleton (one PDO instance per request).
- `reportDatabaseError($exception, $context)` (defined in the `connectDB.php` shim) delegates to
  `App\Infrastructure\DatabaseErrorReporter::report()`, which logs the real exception
  server-side and returns a generic, safe-for-display string. **Always catch `PDOException`
  and route through `reportDatabaseError()` instead of leaking `$e->getMessage()` to the
  page** — this replaced an earlier pattern that did leak DB errors (see
  `phase0-database-review.txt` for the history of that fix). New OOP code should call
  `DatabaseErrorReporter::report()` directly instead of the procedural wrapper function.

`ar/connectDB.php` and `fr/connectDB.php` are **not** independent copies — they are thin
backward-compat shims that just `require_once` `assets/connectDB.php`. Don't edit DB bootstrap
logic in those files, or re-inline connection logic into `assets/connectDB.php` — edit
`src/Infrastructure/Database.php`.

### Data model (`rental_car.sql`)

Three tables:
- `car` (`idcar`, `name`, `door`, `bag`, `seat`, `price`, `type` [0 = manual, 1 = auto], `image`
  — filename under `img/`)
- `user` (`iduser`, `fullname`, `email`, `password` [bcrypt via `password_verify`],
  `reset_token`/`reset_token_expiry` for password reset, `role` — inconsistently stored as
  either the string `'admin'` or a numeric string `'0'`/`'1'`; `role == 0` means non-admin,
  anything else is treated as admin-capable by `admin/*` auth checks)
- `reservation` (`idres`, `depart`, `arrive`, `heureDebut`/`heureFin`, `Date_debut`/`Date_fin`,
  `idcar` FK, `iduser` FK, `confirm` — `NULL`/`0` pending, `1` confirmed). A DB `EVENT`
  (`update_confirm_status`) auto-resets `confirm` to `0` once a reservation's end date/time has
  passed.

Availability checks (in `selection.php`, `process_booking.php`) query for overlapping
`reservation` rows with `confirm = 1` using `STR_TO_DATE(CONCAT(Date_debut,' ',heureDebut), ...)`
range comparisons — replicate that same overlap logic if you touch booking code anywhere.

### Booking flow

`cars.php` (search) → `selection.php` (shows cars, checks live availability per car) →
`process_booking.php` (re-validates availability, inserts a `reservation` row if
`$_SESSION['user_id']` is set, otherwise redirects guests to `reserve.php` with the booking
params preserved in the query string) → confirmation.

### Auth & anti-bruteforce

Plain session-based auth (`session_start()` + `$_SESSION['user_id']`/`role`), no framework.
`login.php` (per language dir) implements manual anti-bruteforce: tracks
`$_SESSION['login_attempts']`/`last_failed_time`, locks out after `MAX_ATTEMPTS` (3) for
`LOCKOUT_TIME` (30s), and requires a session-stored arithmetic captcha
(`$_SESSION['captcha_sum']`) after `CAPTCHA_REQUIRE_ATTEMPTS` (2) failed attempts. This logic is
duplicated per language directory, not shared — apply the same care as with any other
cross-language change.

Admin pages (`admin/admin_header.php`, `admin/index.php`) gate access by checking
`$_SESSION['role']` at the top of the file and redirecting to `../index.php` if unset or `0`.
There is no `admin/connectDB.php`; admin pages also include `../assets/connectDB.php` directly.

### Admin panel

`admin/` is the AdminBSB Material Design admin template (Bootstrap 3-based, jQuery, Materialize
CSS) wired up to the same database for car and reservation management
(`add_car.php`, `cars.php`, `delete_car.php`, `confirm_res.php`, `cancel_res.php`,
`update_reservation.php`). Its `bower.json`/`package.json`/`compilerconfig.json` describe the
original upstream template's asset tooling — this project does not run any of that tooling;
treat `admin/plugins`, `admin/css`, `admin/js` as vendored, static template assets.

### Front-end assets

Each language directory (`en/`, `fr/`, `ar/`) has its own `css/`, `js/`, `lib/`, `scss/` — all
static, vendored (Bootstrap, OwlCarousel, WOW.js, etc.), copy-pasted per language rather than
shared. `img/` at the repo root is shared across all language directories and referenced as
`../img/<filename>` from car records in the `car` table.
