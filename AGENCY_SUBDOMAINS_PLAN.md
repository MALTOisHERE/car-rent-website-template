# Agency subdomains and custom domains: implementation plan

Status: **planning only, not started**. Written 2026-08-25 as a reference for a future session. Nothing described here has been implemented yet.

## The idea

Right now the public marketing site (`en/`, `fr/`, `ar/`) is one shared, agency-agnostic storefront that queries a disconnected legacy `car` table (see migration 008, added the same day this plan was written, purely to stop that page from crashing on a fresh install). It has no relationship to the real multi-agency fleet data.

The target model: every agency created in the backoffice automatically gets its own landing page at `{slug}.yourplatform.com`, showing only that agency's real fleet (from the authoritative `vehicles`/`agencies` tables), with the agency owner able to later attach a custom domain (`rentals.theiragency.com` or similar) that resolves to the same storefront, the way Shopify/similar platforms let a merchant point their own domain at a hosted store.

## Current state (as of this plan)

- `agencies` table (migration 001) has no `subdomain` or `custom_domain` column today.
- `vehicles` has `agency_id`, this is the real per-agency fleet data the storefront should read from instead of the legacy `car` table.
- The public site has no tenant-resolution concept at all: every visitor sees the same pages regardless of Host header.
- `backoffice/agencies.php` creates agencies but has no slug/domain step.
- No reverse proxy, wildcard DNS, or SSL automation exists in this repo. The local dev workflow is plain `php -S 127.0.0.1:8000 dev_router.php`, which cannot serve real subdomains.

## Proposed architecture

### 1. Schema

Add to `agencies` (new migration, 009+):
- `subdomain VARCHAR(63) UNIQUE NOT NULL`: URL-safe slug, e.g. `agadir-cars`. Generated from the agency name at creation time, with a numeric suffix on collision (`agadir-cars`, `agadir-cars-2`, ...).
- `custom_domain VARCHAR(255) UNIQUE NULL`: the owner's own domain, once linked.
- `custom_domain_verified_at DATETIME NULL`: set once DNS ownership is confirmed (see domain verification below). A domain with no verification timestamp must never be treated as active, to prevent one agency claiming another's live domain before proving control of it.

### 2. Tenant resolution layer

A new `app/tenant.php` (or similar), loaded early in the public site's bootstrap, that:
1. Reads `$_SERVER['HTTP_HOST']`.
2. Strips port if present, lowercases.
3. If it matches `{slug}.{PLATFORM_BASE_DOMAIN}`, look up the agency by `subdomain = slug`.
4. Else, look up the agency by `custom_domain = host` (only if `custom_domain_verified_at` is set).
5. Else (bare platform domain, or unrecognized host), fall back to a generic "choose your agency" / platform marketing page, not any single agency's data.
6. Store the resolved agency (or null) in a request-scoped variable other public pages read from, the same way `currentAgencyIds()` is already the pattern in the backoffice.

Security note: validate the Host header against an expected pattern before using it in any query or output (never trust it blindly, classic Host-header-injection surface). Only exact-match lookups against known `subdomain`/`custom_domain` values, no wildcard string building.

### 3. Storefront rewrite

The actual car-browsing/booking pages (`en/cars.php`, `en/selection.php`, and whatever booking flow replaces the legacy one) need to query `vehicles WHERE agency_id = :resolved_agency_id` (plus `archived_at IS NULL`, matching the backoffice's own convention) instead of `SELECT * FROM car`. Agency name/branding on the page (currently hardcoded "REIMS CARS" in the `<title>`) should come from the resolved `agencies` row.

This is realistically a rewrite of the public browsing/booking pages, not a patch, they were written against the old single-tenant schema and it shows throughout (see the earlier audit: `car.image`/`car.gear` drift, no agency concept anywhere, booking submission already silently redirects to `portal/` instead of writing anywhere).

### 4. Agency creation flow

`backoffice/agencies.php`'s create-agency action generates the slug from the agency name at insert time (slugify, lowercase, collision-check loop). Consider showing the generated subdomain back to the owner immediately after creation ("Your site is live at `agadir-cars.yourplatform.com`") and allowing them to edit it once before any bookings exist, but not after (URLs already handed out to customers should stay stable).

### 5. Custom domain linking (owner-facing)

A new settings page (or a section on an existing agency settings page) where the owner enters their domain, then:
1. Show them the DNS record to add (typically a `CNAME` pointing their domain at the platform, or an `A` record at a static IP, depending on hosting).
2. Verification step: either poll for the DNS record to appear, or issue a one-time TXT-record challenge token they add and we check for, standard domain-ownership-proof pattern. Only set `custom_domain_verified_at` once this passes.
3. Show connection status (pending / verified / failed) on that settings page.

### 6. Infrastructure (outside this codebase's PHP, needs real deployment environment)

This is the part that cannot be built or tested against the local `php -S` dev server:
- Wildcard DNS (`*.yourplatform.com`) pointed at wherever this deploys.
- A wildcard SSL certificate (or per-request cert issuance, e.g. via Let's Encrypt) covering the platform subdomain, plus a certificate per verified custom domain.
- A reverse proxy or webserver in front of PHP (Nginx/Caddy/similar) that terminates TLS and passes the Host header through to PHP, since the built-in dev server has no concept of routing by hostname.

None of this is code we write in this repo, it's hosting configuration that has to exist wherever the app is actually deployed. Local development of the tenant-resolution *logic* can still happen by editing `hosts` file entries or using a tool like `laravel valet`-style local wildcard DNS, but real subdomain hosting is a production concern.

## Suggested phase order

1. **Schema + resolution layer only.** Add the migration, add `app/tenant.php`, wire agency creation to generate a slug. No visible behavior change yet for existing pages, this is groundwork.
2. **Storefront rewrite.** Rebuild the car browsing/booking pages to be agency-scoped and read real fleet data. This is the biggest chunk of work and the first phase with real user-visible payoff, an agency's subdomain now shows their actual cars.
3. **Custom domain settings UI + verification flow.** Comes last since it depends on phases 1-2 already working, and needs the infra piece (wildcard cert/reverse proxy) to exist in whatever environment this eventually deploys to, before it's testable end-to-end.

## Open questions for the next session

- What happens at the bare platform domain with no subdomain, a global marketing/pricing page, a directory of agencies, or something else?
- Is the legacy `en/fr/ar` structure kept per-agency (each subdomain still serves `/en/`, `/fr/`, `/ar/`), or does language become a query param/cookie instead now that the domain itself identifies the agency?
- Should `portal/` (customer self-service) also become subdomain-aware, or does a customer always use the platform's shared portal regardless of which agency they booked with?
- Who hosts this in production, and does that host support wildcard SSL and custom-domain SSL provisioning out of the box (e.g. Cloudflare, Vercel-style platforms often do), or does that need to be built explicitly (e.g. via Let's Encrypt's DNS-01 challenge)? This materially changes how much of phase 3 is "write code" versus "configure a platform feature."
- Should a deleted/archived agency's subdomain become immediately reusable, or reserved permanently to avoid a new agency accidentally inheriting an old one's residual traffic/links?
