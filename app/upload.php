<?php

function storeValidatedImage(array $file, $category = 'images')
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('A valid image upload is required.');
    }
    if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > appConfig('upload_max_bytes')) {
        throw new InvalidArgumentException('The image exceeds the permitted size.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('The uploaded image could not be verified.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new InvalidArgumentException('Only valid JPEG, PNG, or WebP images are permitted.');
    }

    $category = preg_replace('/[^a-z0-9_-]/i', '', (string) $category) ?: 'images';
    $relativeDirectory = 'storage/uploads/' . $category . '/' . date('Y/m');
    $absoluteDirectory = dirname(__DIR__) . '/' . $relativeDirectory;
    if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0750, true) && !is_dir($absoluteDirectory)) {
        throw new RuntimeException('The upload directory is unavailable.');
    }

    $filename = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
    $absolutePath = $absoluteDirectory . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new RuntimeException('The image could not be stored.');
    }
    chmod($absolutePath, 0640);

    return [
        'path' => $relativeDirectory . '/' . $filename,
        'mime_type' => $mime,
        'size' => (int) $file['size'],
        'original_name' => basename((string) ($file['name'] ?? 'image')),
    ];
}

function storeValidatedDocument(array $file, $category = 'documents')
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('A valid document upload is required.');
    }
    if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > appConfig('upload_max_bytes')) {
        throw new InvalidArgumentException('The document exceeds the permitted size.');
    }
    $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file['tmp_name']);if(!isset($allowed[$mime]))throw new InvalidArgumentException('Only PDF, JPEG, PNG, or WebP documents are permitted.');
    if(str_starts_with($mime,'image/')&&@getimagesize($file['tmp_name'])===false)throw new InvalidArgumentException('The uploaded image document is invalid.');
    $category=preg_replace('/[^a-z0-9_-]/i','',(string)$category)?:'documents';$relative='storage/uploads/'.$category.'/'.date('Y/m');$absolute=dirname(__DIR__).'/'.$relative;if(!is_dir($absolute)&&!mkdir($absolute,0750,true)&&!is_dir($absolute))throw new RuntimeException('The upload directory is unavailable.');$filename=bin2hex(random_bytes(24)).'.'.$allowed[$mime];if(!move_uploaded_file($file['tmp_name'],$absolute.'/'.$filename))throw new RuntimeException('The document could not be stored.');chmod($absolute.'/'.$filename,0640);
    return ['path'=>$relative.'/'.$filename,'mime_type'=>$mime,'size'=>(int)$file['size'],'original_name'=>basename((string)($file['name']??'document'))];
}
