<?php

$src = imagecreatefromjpeg(__DIR__.'/public/brand/tanwiriyyah-logo.jpg');
$width = imagesx($src);
$height = imagesy($src);
$dst = imagecreatetruecolor(32, 32);
imagecopyresampled($dst, $src, 0, 0, 0, 0, 32, 32, $width, $height);
imagepng($dst, __DIR__.'/public/brand/favicon.png');

$png = file_get_contents(__DIR__.'/public/brand/favicon.png');
$header = pack('vvv', 0, 1, 1);
$entry = pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 22);
file_put_contents(__DIR__.'/public/favicon.ico', $header.$entry.$png);

echo 'OK '.strlen($png).PHP_EOL;
