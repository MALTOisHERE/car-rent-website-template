<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);$extensions=['php','md','sql'];$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));$hits=0;$checked=0;
foreach($iterator as $file){$path=$file->getPathname();if(!in_array(strtolower($file->getExtension()),$extensions,true)||str_contains($path,DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR))continue;$checked++;$lines=file($path);if($lines===false)continue;foreach($lines as $number=>$line){if(str_contains($line,"\u{2014}")){$hits++;fwrite(STDERR,$path.':'.($number+1).': '.trim($line).PHP_EOL);}}}
echo "Checked $checked files for em-dashes; hits: $hits\n";exit($hits===0?0:1);
