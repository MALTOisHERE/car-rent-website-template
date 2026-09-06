<?php

/**
 * Per-agency landing-page branding and content overrides (.docs/AGENCY_SUBDOMAINS_PLAN.md
 * Phase 2 follow-on: white-label storefronts). Content is one JSON blob per
 * (agency, page, language) in agency_page_content -- see migration 010 for why
 * a JSON blob rather than one column/table per field.
 *
 * Every public page follows the same pattern: resolve the tenant agency, load
 * its content for (page, current language) via agencyPageContent(), then read
 * each field through pc($content, 'key', 'original hardcoded copy') so a page
 * with no agency (or an agency that never touched that field) renders
 * byte-for-byte the same original copy that was always there.
 */

function agencyContentPages()
{
    return ['home', 'about', 'service', 'contact', 'team', 'testimonial', 'blog'];
}

/** Fetches the stored content fields for one (agency, page, language), or [] if nothing has been saved yet. */
function agencyPageContent($agencyId, $page, $languageCode)
{
    if (!$agencyId) {
        return [];
    }

    $row = dbFetchOne(
        'SELECT content_json FROM agency_page_content WHERE agency_id=:agency AND page=:page AND language_code=:lang',
        ['agency' => $agencyId, 'page' => $page, 'lang' => $languageCode]
    );
    if (!$row) {
        return [];
    }

    $decoded = json_decode((string) $row['content_json'], true);
    return is_array($decoded) ? $decoded : [];
}

/** Reads one field out of a content array, falling back to the page's original hardcoded copy when unset or blank. */
function pc(array $content, $key, $default)
{
    $value = $content[$key] ?? '';
    $value = is_string($value) ? trim($value) : '';
    return $value !== '' ? $value : $default;
}

/** Upserts one (agency, page, language)'s full content array as a single row. */
function saveAgencyPageContent($agencyId, $page, $languageCode, array $content, $userId)
{
    dbExecute(
        'INSERT INTO agency_page_content (agency_id, page, language_code, content_json, updated_by)
         VALUES (:agency, :page, :lang, :content, :user)
         ON DUPLICATE KEY UPDATE content_json = VALUES(content_json), updated_by = VALUES(updated_by)',
        ['agency' => $agencyId, 'page' => $page, 'lang' => $languageCode, 'content' => json_encode($content, JSON_UNESCAPED_UNICODE), 'user' => $userId]
    );
}

/**
 * The brand colors to render a public page with: the resolved agency's own
 * palette where set, falling back field-by-field to Aurevo's current navy
 * brand so an agency that only set one color doesn't end up with blank CSS
 * for the other two.
 */
function agencyColorPalette($agency)
{
    $defaults = ['primary' => '#011468', 'secondary' => '#011468', 'dark' => '#00104f'];
    if (!$agency) {
        return $defaults;
    }

    return [
        'primary' => isValidHexColor($agency['primary_color'] ?? null) ? $agency['primary_color'] : $defaults['primary'],
        'secondary' => isValidHexColor($agency['secondary_color'] ?? null) ? $agency['secondary_color'] : $defaults['secondary'],
        'dark' => isValidHexColor($agency['accent_dark_color'] ?? null) ? $agency['accent_dark_color'] : $defaults['dark'],
    ];
}

function isValidHexColor($value)
{
    return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
}

/** "#rrggbb" -> "r, g, b", for building rgba() gradients from a brand color. */
function hexToRgbTriplet($hex)
{
    $hex = ltrim($hex, '#');
    return implode(', ', [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ]);
}

/**
 * A <style> block overriding every named Bootstrap utility class these public
 * templates use for brand color (.btn-primary/.btn-secondary/.text-primary/
 * .text-secondary/.bg-primary/.bg-secondary and the --bs-primary variable),
 * with !important so it wins over the compiled Bootstrap bundle. This only
 * reaches class-based color usage -- the many scattered inline
 * style="color:#011468" attributes throughout the public templates are
 * parameterized directly at each call site via agencyColorPalette() instead
 * (an attribute-selector trick to catch those generically was tried and
 * dropped: [style*="color:#011468"] also matches "background-color:#011468"
 * as a substring, which would incorrectly recolor text on elements that only
 * set a background). Safe to include on every public page unconditionally;
 * it renders the exact same default navy when no agency (or no custom
 * colors) is resolved.
 */
function agencyColorStyleBlock($agency)
{
    $c = agencyColorPalette($agency);
    $p = e($c['primary']);
    $s = e($c['secondary']);
    $d = e($c['dark']);
    $pRgb = hexToRgbTriplet($c['primary']);
    $dRgb = hexToRgbTriplet($c['dark']);
    return <<<HTML
<style>
:root{--bs-primary:{$p};--bs-primary-rgb:{$p};--bs-secondary:{$s};--bs-dark:{$d};}
.btn-primary{background-color:{$p}!important;border-color:{$p}!important;}
.btn-primary:hover{background-color:{$d}!important;border-color:{$d}!important;}
.btn-secondary{background-color:{$p}!important;}
.btn-secondary:hover{background-color:#fff!important;color:{$p}!important;border-color:{$p}!important;}
.text-primary{color:{$p}!important;}
.text-secondary{color:{$s}!important;}
.bg-primary{background-color:{$p}!important;}
.bg-secondary{background-color:{$s}!important;}
.bg-custom-secondary{background-color:{$s}!important;}
.btn.btn-primary{background-color:{$p}!important;border-color:{$p}!important;}
.btn.btn-primary:hover{color:{$p}!important;border-color:{$p}!important;}
.navbar-light .navbar-nav .nav-link:hover,.navbar-light .navbar-nav .nav-link.active{color:{$p}!important;text-decoration-color:{$p}!important;}
.feature .feature-item .feature-icon span{background:{$p}!important;}
.about .about-item .about-item-inner .about-icon{background:{$p}!important;}
.counter .counter-item .counter-item-icon{background:{$p}!important;}
.service .service-item::after{background:{$p}!important;}
.service .service-item .service-icon{background:{$p}!important;}
.service .service-item:hover .service-icon{background:{$p}!important;}
.categories .categories-item{border-color:{$p}!important;}
.categories .categories-item:hover{border-color:{$p}!important;}
.categories .categories-item .categories-item-inner:hover{box-shadow:0 0 50px {$p}!important;}
.categories-carousel .owl-nav .owl-prev,.categories-carousel .owl-nav .owl-next{background:{$p}!important;border-color:{$p}!important;}
.categories-carousel .owl-nav .owl-prev:hover,.categories-carousel .owl-nav .owl-next:hover{color:{$p}!important;border-color:{$p}!important;}
.steps .steps-item{background:{$p}!important;}
.steps .steps-item .setps-number{background:{$p}!important;}
.blog .blog-item .blog-content .blog-date{background:{$p}!important;}
.team .team-item::after{background:{$p}!important;}
.bg-breadcrumb{background:linear-gradient(rgba({$pRgb},1),rgba({$dRgb},0.8)),url(img/fact-bg.jpg)!important;background-position:center top!important;background-repeat:no-repeat!important;background-size:cover!important;}
.counter{background:linear-gradient(rgba({$dRgb},0.9),rgba({$pRgb},0.9)),url(img/fact-bg.jpg)!important;background-position:center center!important;background-repeat:no-repeat!important;background-size:cover!important;}
.steps{background:linear-gradient(rgba({$dRgb},0.85),rgba({$dRgb},0.85)),url(img/bg-1.jpg)!important;background-position:center center!important;background-repeat:no-repeat!important;background-size:cover!important;}
</style>
HTML;
}
