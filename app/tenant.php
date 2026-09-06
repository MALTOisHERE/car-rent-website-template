<?php

/**
 * Tenant resolution for the public site: which agency (if any) a request's Host
 * header belongs to. Phase 1 of .docs/AGENCY_SUBDOMAINS_PLAN.md -- defined here and
 * loaded on every request via bootstrap.php, but not yet called from any page;
 * the storefront rewrite (Phase 2) is what actually branches on this.
 *
 * classifyTenantHost() is pure string parsing, kept separate from the
 * database lookup so it can be unit tested without a DB connection (see
 * tests/business_rules.php). The Host header is attacker-controlled input --
 * only exact-match lookups are ever run against it, never wildcard/LIKE
 * queries or string concatenation into SQL.
 */
/** Does this string look like a valid agency subdomain slug (the same format generateUniqueAgencySlug() produces)? */
function isValidAgencySlugFormat($slug)
{
    return is_string($slug) && preg_match('/^[a-z0-9-]{1,63}$/', $slug) === 1;
}

function classifyTenantHost($host, $baseDomain)
{
    $host = strtolower(trim((string) $host));
    $host = preg_replace('/:\d+$/', '', $host);
    $baseDomain = strtolower(trim((string) $baseDomain));

    if ($host === '' || !preg_match('/^[a-z0-9.-]+$/', $host)) {
        return ['type' => 'bare'];
    }

    if ($baseDomain === '') {
        return ['type' => 'bare'];
    }

    if ($host === $baseDomain || $host === 'www.' . $baseDomain) {
        return ['type' => 'bare'];
    }

    if (str_ends_with($host, '.' . $baseDomain)) {
        $slug = substr($host, 0, -1 * (strlen($baseDomain) + 1));
        if ($slug !== '' && $slug !== 'www' && isValidAgencySlugFormat($slug)) {
            return ['type' => 'subdomain', 'slug' => $slug];
        }
        return ['type' => 'bare'];
    }

    return ['type' => 'custom_domain', 'host' => $host];
}

/**
 * Development-only convenience: a request can simulate hitting a given
 * agency's subdomain via ?agency=slug, since local dev has no real DNS to
 * test subdomains against. Returns false when no override applies (not in
 * development, or no ?agency param) so resolveTenantAgency() falls through
 * to normal Host-based resolution; returns the resolved agency row (or null,
 * for an unknown/invalid slug) when an override IS requested, in which case
 * the Host header is never consulted. Ignored entirely outside
 * APP_ENV=development -- only the Host header is ever trusted in production.
 *
 * $environment is an explicit parameter (defaulting to the real appConfig()
 * value) rather than read internally, so tests can exercise both branches
 * without depending on appConfig()'s static memoization of the real env var.
 */
function resolveDevTenantOverride($environment = null)
{
    $environment = $environment ?? appConfig('environment');
    if ($environment !== 'development' || !isset($_GET['agency'])) {
        return false;
    }

    $slug = strtolower(trim((string) $_GET['agency']));
    if (!isValidAgencySlugFormat($slug)) {
        return null;
    }

    return dbFetchOne(
        'SELECT * FROM agencies WHERE subdomain = :slug AND archived_at IS NULL',
        ['slug' => $slug]
    ) ?: null;
}

/**
 * Resolves the current request's Host header to an active agency row, or
 * null for the bare platform domain / an unrecognized host. A custom domain
 * only matches once its ownership has been verified (custom_domain_verified_at
 * set) -- see .docs/AGENCY_SUBDOMAINS_PLAN.md's domain verification step.
 */
function resolveTenantAgency()
{
    $override = resolveDevTenantOverride();
    if ($override !== false) {
        return $override;
    }

    $classified = classifyTenantHost($_SERVER['HTTP_HOST'] ?? '', appConfig('platform_base_domain'));

    if ($classified['type'] === 'subdomain') {
        return dbFetchOne(
            'SELECT * FROM agencies WHERE subdomain = :slug AND archived_at IS NULL',
            ['slug' => $classified['slug']]
        ) ?: null;
    }

    if ($classified['type'] === 'custom_domain') {
        return dbFetchOne(
            'SELECT * FROM agencies WHERE custom_domain = :host AND custom_domain_verified_at IS NOT NULL AND archived_at IS NULL',
            ['host' => $classified['host']]
        ) ?: null;
    }

    return null;
}
