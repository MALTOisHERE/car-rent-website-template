-- Migration 009: agency subdomains and custom domains -- Phase 1 (schema +
-- resolution layer) of AGENCY_SUBDOMAINS_PLAN.md. Schema only; no existing
-- page's behavior changes yet, the public storefront rewrite is a later phase.

ALTER TABLE agencies
    ADD COLUMN IF NOT EXISTS subdomain VARCHAR(63) NULL AFTER code,
    ADD COLUMN IF NOT EXISTS custom_domain VARCHAR(255) NULL AFTER subdomain,
    ADD COLUMN IF NOT EXISTS custom_domain_verified_at DATETIME NULL AFTER custom_domain;

-- Backfill any pre-existing agency rows (installs upgrading from before this
-- migration) with a deterministic, collision-free slug: lowercase name with
-- non-alphanumerics collapsed to single hyphens, suffixed with the row's own
-- id so uniqueness never depends on replicating PHP's collision-check loop
-- in SQL. Agencies created after this migration get a clean slug (no id
-- suffix) from backoffice/agencies.php via generateUniqueAgencySlug().
UPDATE agencies
SET subdomain = CONCAT(
    COALESCE(NULLIF(TRIM(BOTH '-' FROM REGEXP_REPLACE(LOWER(name), '[^a-z0-9]+', '-')), ''), 'agency'),
    '-', id
)
WHERE subdomain IS NULL;

-- subdomain is deliberately left NULLable rather than NOT NULL: several
-- DB-backed test fixtures under tests/ (and bin/seed_demo.php before this
-- migration's own fix) insert into agencies without a subdomain value, and
-- MySQL/MariaDB unique indexes already allow multiple NULLs without
-- conflicting. resolveTenantAgency()'s lookup only ever matches an exact,
-- non-NULL subdomain, so a NULL row here simply never resolves as a tenant --
-- which is the correct behavior for an agency that was never assigned a
-- public subdomain (test fixtures, or any future non-storefront agency).
ALTER TABLE agencies
    ADD UNIQUE KEY IF NOT EXISTS uq_agencies_subdomain (subdomain),
    ADD UNIQUE KEY IF NOT EXISTS uq_agencies_custom_domain (custom_domain);
