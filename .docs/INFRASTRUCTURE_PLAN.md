# Docker, VPS, separated database, CDN images: infrastructure plan

Status: **planning only, not started**. Written 2026-08-25 as a reference for a future session. Nothing described here has been implemented yet, beyond the housekeeping in the same commit as this doc (removed the retired admin/ template and unused vendor/, cleaned up .gitignore).

## The direction, as stated

- Hosted on a VPS.
- Docker-based.
- Database separated (its own service/container, not bundled with the app).
- "Everything separated as microservices."
- Uploaded images moved to a CDN (Cloudinary named specifically) instead of local disk.

## Current state (relevant to this plan)

- Procedural PHP, no framework, no Composer, one shared `app/` function library used by every route directory (`backoffice/`, `account/`, `portal/`, `en/fr/ar/`).
- Single MariaDB database, connected via `assets/connectDB.php` using plain `getenv()` calls (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_CHARSET`), no `.env` file parsing. This is already Docker/12-factor-friendly, a real head start.
- `bin/migrate.php` is a clean CLI entrypoint that applies `database/migrations/*.sql` in order, idempotently. Maps naturally onto a Docker "run once per deploy" step.
- File uploads (vehicle photos, inspection photos, customer documents) go to local disk under `storage/`, split into `storage/uploads/` (public-ish) and `storage/inspection-photo-private/` (strictly access-controlled). Delivery of the private ones goes through `app/protected_file.php`: re-authorize on every request (role + agency scope), resolve realpath against both the upload root and the target file (rejects traversal/symlink escape), verify detected MIME against an allowlist, and return a **generic 404** for every failure case, never a distinguishable error.
- Sessions are native PHP file-based sessions (`storage/sess_*`). This works fine for a single PHP process/container but breaks the moment there is more than one app instance behind a load balancer, since session file N only exists on the container that wrote it.
- No Docker files exist yet (no Dockerfile, no docker-compose.yml).

## Piece by piece

### 1. Separated database

The least controversial part, and the connection code already assumes a remote host (`DB_HOST` is already a configurable hostname, not hardcoded `localhost`). In Docker terms this just means: a `db` service (MariaDB image) with its own named volume for `/var/lib/mysql`, and the app service(s) pointed at it via `DB_HOST=db` on the same Docker network. `bin/migrate.php` runs against that same `DB_HOST`. No code changes needed here, this is pure Docker Compose wiring.

### 2. Docker for the app itself

A PHP-FPM or `php:8.2-apache`-based image, with `dev_router.php`'s logic replaced by real webserver routing in production (the router script exists specifically for the *built-in dev server*; a production Nginx/Apache config plays the same "block direct /storage access, resolve directory indexes" role natively and more efficiently). Needs:
- A production webserver config (Nginx + php-fpm, most likely) that mirrors `dev_router.php`'s two security rules: never serve `/storage/*` directly, and block path traversal (`..`) in the URL.
- The `.env.example` variable list becomes real container environment variables (or Docker secrets for `DB_PASSWORD` and similar).
- `bin/migrate.php` and `bin/seed_demo.php` (guarded by `APP_ENV=production` refusal, already in place) become one-off `docker compose run` commands or an init container, not something that runs on every boot.

### 3. "Everything separated as microservices": needs a decision, not a default

This phrase covers a wide range, and picking the wrong end of that range is a lot of wasted rework. Three real options, roughly in order of effort:

**A. One container, multiple route groups (not really microservices, but genuinely "separated" in the way that matters for a VPS deploy).** Single PHP codebase and single container image, but the *reverse proxy* routes different hostnames/paths to it: `app.example.com` → backoffice, `portal.example.com` → portal, marketing subdomains → public site (this is the same reverse-proxy layer the [agency subdomains plan](AGENCY_SUBDOMAINS_PLAN.md) already needs). Zero code changes. Scales by adding more replicas of the *same* container, not by splitting the codebase.

**B. Multiple containers, same codebase, different entrypoints.** Build the same image, but run different containers pointed at different document roots or with different Nginx server blocks: one container/replica group serving only `backoffice/`, another only `portal/`, another only `en/fr/ar/`. Still one shared `app/` library and one shared database, just process-level separation so, say, heavy backoffice report queries can't starve the public booking site of PHP-FPM workers. Meaningfully more ops complexity than A for a real but modest benefit at this traffic scale.

**C. True microservices.** Actually splitting `app/`'s domain services (reservations, finance, vehicles, contracts, inspections, customers) into independently deployable services with their own APIs and possibly their own datastores, with the current route directories becoming thin clients calling those APIs. This is a genuine rewrite: it means introducing HTTP/RPC contracts between services, distributed transactions or eventual consistency for anything that currently relies on a single MySQL transaction (and there is a lot of that, reservation allocation, finance ledger writes, and checkout/check-in all explicitly use row locks and transactions *because* a real race was found and fixed that way, see `IMPLEMENTATION_REPORT.md`'s Phase 5A concurrency remediation), and almost certainly a message queue or event bus for anything that currently just calls a PHP function directly. This is many times the effort of A or B and changes the architecture CLAUDE.md currently documents as a deliberate choice ("No framework and no Composer... reusable logic lives in plain functions").

None of this needs to be settled today, but it changes literally everything downstream (how many Dockerfiles, whether a service mesh or API gateway is needed, whether the shared `app/` library survives or gets carved up), so it's the first open question in this plan, not an afterthought.

### 4. Session storage

Only matters once there is more than one app replica (options A/B/C all eventually hit this once traffic justifies horizontal scaling). Needs a shared session store, Redis is the standard choice and pairs naturally with a Docker `redis` service. `app/session.php` would need its session handler swapped from the PHP default (files) to Redis-backed. Not urgent for a single-VPS, single-container deploy, but worth deciding now so the Docker Compose file has a `redis` service from day one rather than being retrofitted later.

### 5. Images on Cloudinary (or similar CDN)

Two very different cases here, worth not conflating:

- **Public images** (vehicle photos, agency-facing marketing images): straightforward win. Upload to Cloudinary on save, store the returned `public_id`/URL in the existing DB columns (`vehicles.primary_image_path` and similar already exist as string columns, they'd just hold a Cloudinary URL instead of a local path), and let the CDN serve them directly, no PHP involvement per image request at all. Pure upside: faster delivery, no VPS disk/bandwidth cost for images, existing `app/upload.php` validation (MIME allowlist, size limits) still runs before the Cloudinary upload call.
- **Protected images** (inspection photos, customer documents): the current design's entire point is that nothing is served without a fresh, per-request authorization check (agency scope + role + generic 404 on any failure, `app/protected_file.php`). Cloudinary supports "authenticated" delivery with signed, time-limited URLs, which can replicate this, but it's a real design piece, not a drop-in swap: the app would generate a short-lived signed Cloudinary URL *after* running the exact same authorization check it runs today, then redirect or hand back that URL instead of streaming the file itself. Worth explicitly deciding whether protected images move to Cloudinary at all in phase one, or stay on local/VPS disk (mounted as a Docker volume) while only the public-facing images move to the CDN first.

## Suggested phase order

1. **Docker Compose for local/VPS parity first, no architecture change.** One app container (current monolith, unchanged code) + one `db` container + a `redis` container for sessions (added now, wired in later). Proves the deploy story works before touching anything else.
2. **Public images to Cloudinary.** Self-contained, no auth-flow redesign needed, immediate win, and validates the CDN integration pattern before tackling the harder protected-file case.
3. **Decide and implement the A/B/C question above**, informed by actual VPS resource constraints and traffic once phase 1 is live, rather than guessing today.
4. **Protected images to Cloudinary (optional/later)**, once the signed-URL authorization flow is designed, or leave them on a mounted volume indefinitely if the added complexity isn't worth it for this app's document volume.

## Open questions for the next session

- Which of A/B/C (or something in between) is actually wanted for "microservices"? This is the one decision that should happen before any Docker files get written, since it determines how many images/services this plan produces.
- Single VPS or multiple? A single VPS running Docker Compose is a very different (much simpler) target than multiple VPSes needing container orchestration (Swarm/Kubernetes), worth confirming before assuming Compose is the final answer rather than a local-dev stepping stone.
- Does the [agency subdomains plan](AGENCY_SUBDOMAINS_PLAN.md)'s reverse-proxy/wildcard-SSL layer get built as part of this infrastructure work, or separately? They share the same reverse-proxy piece, doing them together avoids configuring it twice.
- Cloudinary specifically, or "a CDN" generally? Cloudinary has transformation/optimization features (auto-format, responsive sizing) that a plain S3+CloudFront setup wouldn't give for free, worth confirming Cloudinary is the actual choice versus just the first example that came to mind.
- Should protected documents (customer IDs, licences) go to a CDN at all, or is keeping sensitive documents on infrastructure fully under this app's own access-control code (rather than a third party's signed-URL system) a deliberate compliance/security preference? Worth a explicit decision given the codebase's otherwise very deliberate stance on protected-file access control.
