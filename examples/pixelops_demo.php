<?php

include dirname(__DIR__) . '/HCImage.class.php';

use HC\Color;
use HC\Image;

try {
    $img    = Image::load('img/PNG_transparency_demonstration_1.png');
    $time   = microtime(true);
    $img->trim();
    $w      = $img->getWidth();
    $h      = $img->getHeight();
    $canvas = $img->getCanvas();
    $canvas ->usePixelOps(0,           0, $w / 2, $h / 2, 1, 1, false)->saltAndPepper()
            ->usePixelOps(0,      $h / 2, $w / 2,   null, 1, 1, false)->noise()
            ->usePixelOps($w / 2,      0,   null, $h / 2, 1, 1, true )->filterHue(Color::get(20,240,140))
            ->usePixelOps($w / 2, $h / 2,   null,   null, 1, 1, true )->swapColor('gbr')
            ->usePixelOps($w * 2 / 5, $h * 2 / 5, $w * 3 / 5, $h * 3 / 5,1,1,true)->transparency(30);

    echo microtime(true) - $time, "s\n";
    $img->save('out/pixels', 'png', 9);
} catch (Exception $exc) {
    echo $exc->getMessage(), PHP_EOL;
}

