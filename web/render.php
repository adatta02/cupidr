<?php

error_reporting(E_ALL);

$template = "ryangosling.jpg";
$text = "Fox trot charlie!!!!";

$font = dirname(__FILE__) . "/../bin/OpenSans-ExtraBold.ttf";
$templateImg = imagecreatefromjpeg( dirname(__FILE__) . "/templates/" . $template );

$im = imagecreatetruecolor(1875, 1275);

$black = imagecolorallocate($im, 0, 0, 0);
$transparentWhite = imagecolorallocatealpha($im, 0, 0, 0, 90);

imagesavealpha($im, true);
imagefill($im, 0, 0, $transparentWhite);

$coords = imagettftext($im, 60, 0, 50, 100, $black, $font, $text);

$height = $coords[3] + 60; 
$width = $coords[2] + 60;

imagecopy($templateImg, $im, 40, 40, 0, 0, $width, $height);

header('Content-Type: image/png');
imagepng($templateImg);
imagedestroy($templateImg);