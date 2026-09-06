<?php
/* Use with `php -S 127.0.0.1:8000 bin/dev_router.php` for a safe repository-root dev server. */
$root=dirname(__DIR__);
$request=(string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);$decoded=rawurldecode($request);
if(str_contains($decoded,"\0")||str_contains($decoded,'..')||preg_match('#^/storage(?:/|$)#',$decoded)){http_response_code(404);echo 'Not found.';return;}
if($decoded===''||$decoded==='/'){require $root.'/site/index.php';return;}
$file=$root.str_replace('/',DIRECTORY_SEPARATOR,$decoded);
if(is_dir($file)){$indexFile=rtrim($file,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'index.php';if(is_file($indexFile)){require $indexFile;return;}}
if(is_file($file)&&pathinfo($file,PATHINFO_EXTENSION)!=='php')return false;
if(is_file($file)&&pathinfo($file,PATHINFO_EXTENSION)==='php'){require $file;return;}
if(preg_match('#^/([A-Za-z0-9_-]+\.php)$#',$decoded,$m)){$siteFile=$root.DIRECTORY_SEPARATOR.'site'.DIRECTORY_SEPARATOR.$m[1];if(is_file($siteFile)){require $siteFile;return;}}
http_response_code(404);echo 'Not found.';
