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
- **Done (Phase 2)**: the booking flow — `cars.php`, `selection.php`, `process_booking.php`,
  `reserve.php`, `confirm_reservation.php` — is consolidated into single implementations under
  `pages/` (plus `pages/templates/header.php`/`footer.php`), used by all three languages via
  thin shims at `en/`/`fr/`/`ar/<page>.php` (each just sets `$_SESSION['lang']` and
  `require`s the shared page — this keeps every existing URL/bookmark working). A single shared
  front-end asset copy lives at `assets/{css,js,lib}/` (verified byte-identical to the old
  per-language copies before consolidating); `pages/templates/header.php` also sets
  `dir="rtl"` for Arabic via `Translator::isRtl()`. See "Language triplication" and "Booking
  flow" below for what this fixed. `en/`, `fr/`, `ar/` still each keep their own `css/`/`js/`/`lib/`
  copies **on disk, untouched** — only pages migrated so far read from `assets/` instead; deleting
  the old per-language copies has to wait until every page in that language is migrated.
- **Not yet migrated**: every other page (`about.php`, `login.php`, `signup.php`, `contact.php`,
  `send_reset_link.php`, `reset_password.php`, etc. in `en/`/`fr/`/`ar/`, and all of `admin/*.php`)
  still contains its own inline SQL/session logic on the old per-language `header_p.php`/`footer_p.php`
  and has not been rewired onto the classes in `src/`. Next up: auth (`login.php`/`signup.php`/
  `send_reset_link.php`/`reset_password.php`), then remaining static pages, then the admin panel.
- **Known pre-existing bugs still open** (not touched by Phase 2, since it only covered the
  booking flow): `send_reset_link.php` and `reset_password.php` reference an undefined `$conn`
  variable (should be `$mysqlconnection`) — password reset is currently fatally broken;
  `admin/index.php`/`admin/cars.php` never call `session_start()` before `admin_header.php`'s
  role check runs, so that guard is likely dead; the admin edit-reservation modal echoes DB
  values unescaped into HTML attributes; no admin page has CSRF protection on state-changing
  forms; `admin/add_car.php`'s file upload has no extension/MIME whitelist.

## Architecture

### Language triplication, not i18n

Most of the site is **still not** internationalized via a shared template + translation files:
`en/`, `fr/`, `ar/` are near-complete copies of the same PHP pages (`login.php`, `about.php`,
`contact.php`, etc.), each with hardcoded strings in its language. **When fixing a bug or
changing behavior in one of these still-duplicated files, check whether the same fix is needed
in the other two** — this is still the most important thing to remember for anything outside
the booking flow. `index.php` and `logout.php` at the repo root are the only shared entry
points; they redirect into the per-language folder based on `$_SESSION['lang']`.

The booking flow (`cars.php`/`selection.php`/`process_booking.php`/`reserve.php`/`confirm_reservation.php`)
is the **exception**: it's been consolidated (see "Refactor status") into one real
implementation per page under `pages/`, with `en/`/`fr`/`ar/<page>.php` reduced to a 6-line
shim (`session_start()` guard, `$_SESSION['lang'] = 'xx'`, `require __DIR__.'/../pages/<page>.php'`).
**Never add logic to those five shim files** — edit the shared file under `pages/` instead, or
it changes for all three languages at once anyway (that's the point). Language selection inside
`pages/` (and its `templates/header.php`) always reads `$_SESSION['lang']`, which the shim sets
on every request to match the folder actually requested — more reliable than the old
`?lang=` toggle alone, which only updated the session on an explicit click.

Consolidating this flow surfaced real bugs in `fr`/`ar` that the shared implementation now
fixes for all three languages at once: `fr`/`ar` `selection.php` used the wrong prepared
statement variable and never checked the requested date range at all (any past confirmed
booking blocked a car regardless of the dates you searched); their `confirm_reservation.php`
stored the guest's password in **plaintext** and inserted into a `name` column that doesn't
exist in the `user` table (fatal error on every guest booking); their `header_p.php` was
missing the logged-in-user nav swap entirely (always showed "Login", even to a logged-in user);
and the guest-registration path (`confirm_reservation.php`) never re-checked availability
before inserting a reservation, in `en` either — a race condition where two guests could double
book the same car. All of that is what `pages/confirm_reservation.php` +
`App\Service\BookingService`/`RegistrationService` fix by construction now.

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

Availability checks query for overlapping `reservation` rows with `confirm = 1` using
`STR_TO_DATE(CONCAT(Date_debut,' ',heureDebut), ...)` range comparisons — this now lives in
exactly one place, `App\Repository\ReservationRepository::hasOverlap()` (used by
`BookingService`); `admin/*.php` still has its own uses of `reservation`/`confirm` but doesn't
duplicate this particular overlap query.

### Booking flow

`pages/cars.php` (browse/marketing page — its own "Book Now" just links to `index.php`, this
is pre-existing, not a bug I introduced) and `pages/selection.php` (search results — shows
cars, checks live availability per car via `BookingService::isAvailable()`) →
`pages/process_booking.php` (re-validates availability via `BookingService::book()`, inserts a
`reservation` row if `$_SESSION['user_id']` is set, otherwise redirects guests to
`pages/reserve.php` with the booking params preserved in the query string) →
`pages/confirm_reservation.php` (guest path only: registers the account via
`RegistrationService`, re-checks availability *before* creating the account so a sold-out car
doesn't leave an orphaned user row, then books via `BookingService`). All reachable from any
language through the `en/`/`fr/`/`ar/` shims described above. `App\Repository\ReservationRepository::hasOverlap()`
is the one place the `STR_TO_DATE(...)` overlap query lives now — touch it there, not in a page.

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

Each language directory (`en/`, `fr/`, `ar/`) still has its own `css/`, `js/`, `lib/`, `scss/`
— all static, vendored (Bootstrap, OwlCarousel, WOW.js, etc.). These were verified
byte-identical across all three languages before Phase 2 copied one shared set to
`assets/{css,js,lib}/`, which only the consolidated `pages/` templates use so far (via
`../assets/...` relative paths — valid because `en/`/`fr/`/`ar/` are always one directory below
repo root, same depth as `pages/`'s callers). The old per-language copies are still on disk and
still used by every not-yet-migrated page — **don't delete `en/css`/`fr/css`/etc. until every
page in that language has been migrated to `pages/`**, or you'll break whatever's left. `img/`
at the repo root has always been shared across all language directories and is referenced as
`../img/<filename>` from car records in the `car` table.
