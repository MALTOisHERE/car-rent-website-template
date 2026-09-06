<?php

/**
 * Public, unauthenticated delivery of one agency's logo image. Deliberately
 * separate from app/protected_file.php's pattern (which requires a logged-in
 * staff session): a storefront logo is public-facing marketing material by
 * definition, so there is no session to check here. What IS still enforced,
 * matching backoffice/vehicle_media.php's containment pattern: the stored
 * path must resolve inside storage/uploads (never outside it, never a
 * traversal), the agency must not be archived, and the sniffed MIME type
 * must be in the image allowlist. Any failure returns a generic 404, same as
 * every other protected/served-file route in this codebase.
 */

require_once __DIR__ . '/app/application.php';

function agencyLogoNotFound()
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found.';
    exit;
}

$agencyId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$agencyId) {
    agencyLogoNotFound();
}

$agency = dbFetchOne('SELECT logo_path FROM agencies WHERE id=:id AND archived_at IS NULL', ['id' => $agencyId]);
if (!$agency || !$agency['logo_path']) {
    agencyLogoNotFound();
}

$path = storedUploadAbsolutePath($agency['logo_path']);
if ($path === null) {
    agencyLogoNotFound();
}

$allowed = ['image/jpeg', 'image/png', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($path);
if (!in_array($mime, $allowed, true)) {
    agencyLogoNotFound();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: inline; filename="agency-logo.' . ($mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg')) . '"');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($path);
