<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));$failed=0;$checked=0;
foreach($iterator as $file){$path=$file->getPathname();if($file->getExtension()!=='php'||str_contains($path,DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR)||str_contains($path,DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR))continue;$checked++;$command=escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path);exec($command,$output,$code);if($code!==0){$failed++;fwrite(STDERR,implode(PHP_EOL,$output).PHP_EOL);}$output=[];}
echo "Checked $checked PHP files; failures: $failed\n";exit($failed===0?0:1);

