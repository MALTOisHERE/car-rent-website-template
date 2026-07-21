<?php
require_once __DIR__ . '/../app/application.php';
requireAuthentication('../account/login.php');
requirePermission('vehicles.view');

try {
    $mediaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!$mediaId) throw new InvalidArgumentException('not found');
    $media = dbFetchOne('SELECT vm.*,v.archived_at AS vehicle_archived_at FROM vehicle_media vm JOIN vehicles v ON v.id=vm.vehicle_id AND v.agency_id=vm.agency_id WHERE vm.id=:id AND vm.archived_at IS NULL', ['id'=>$mediaId]);
    if (!$media || $media['vehicle_archived_at'] !== null) throw new InvalidArgumentException('not found');
    requireAgencyAccess((int) $media['agency_id']);
    $path = storedUploadAbsolutePath($media['storage_path']);
    if ($path === null) throw new InvalidArgumentException('not found');
    $allowed = ['image/jpeg','image/png','image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($path);
    if (!in_array($mime, $allowed, true) || $mime !== $media['mime_type']) throw new InvalidArgumentException('not found');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: inline; filename="vehicle-image.' . ($mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg')) . '"');
    header('Cache-Control: private, max-age=300, no-transform');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
} catch (InvalidArgumentException $exception) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found.';
}
