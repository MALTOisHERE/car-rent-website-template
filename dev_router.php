<?php
/* Use with `php -S 127.0.0.1:8000 dev_router.php` for a safe repository-root dev server. */
$request=(string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);$decoded=rawurldecode($request);
if(str_contains($decoded,"\0")||str_contains($decoded,'..')||preg_match('#^/storage(?:/|$)#',$decoded)){http_response_code(404);echo 'Not found.';return;}
$file=__DIR__.str_replace('/',DIRECTORY_SEPARATOR,$decoded);
if(is_dir($file)){$indexFile=rtrim($file,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'index.php';if(is_file($indexFile)){require $indexFile;return;}}
if(is_file($file)&&pathinfo($file,PATHINFO_EXTENSION)!=='php')return false;
if(is_file($file)&&pathinfo($file,PATHINFO_EXTENSION)==='php'){require $file;return;}
http_response_code(404);echo 'Not found.';
