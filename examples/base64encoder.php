#!/usr/bin/php
<?php
if ($argc < 2)
    exit('To few arguments: '
            . basename(__FILE__)
            . ' image [type <png,jpg,gif> [quality <jpg: 1-100; png: 0-9> [pallete <number> [dithering]]]]');

require dirname(__DIR__) . '/HC/Image.php';
require dirname(__DIR__) . '/HC/Color.php'; //for arbitrary file loading

$image   = $argv[1];
$type    = isset($argv[2]) ? $argv[2] : false;
$quality = isset($argv[3]) ? intval($argv[3]) : false;
$palette = isset($argv[4]) ? intval($argv[4]) : false;
$dither  = isset($argv[5]);

try {
    $image = \HC\Image::load($image, $palette);
    if ($palette) {
        require dirname(__DIR__) . '/HC/Canvas.php';
//        $image->getCanvas()->saveAlpha(false);
        $image->getCanvas()->truecolorToPalette($dither, $palette);
        if ($palette > 2 && ($trans = $image->getTransparentColor($dither, true)) !== -1) {
            $image->setTransparentColor($trans);
        }
        //$image->setTransparentColor(0x7f000000);// sometimes can compress png more
    }
    echo $image->encode($type, $quality), PHP_EOL;
} catch (Exception $exc) {
    echo $exc->getMessage(), PHP_EOL;
}
