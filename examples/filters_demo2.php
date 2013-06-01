<?php

include dirname(__DIR__) . '/HCImage.class.php';

use HC\Color;
use HC\Image;

try {
    $img  = Image::load('img/PNG_transparency_demonstration_1.png');
    $time = microtime(true);

    $img2 = clone $img->trim();
    $img2->crop(0, 0, $img->getWidth() * 3.5, $img->getHeight());

    $tColor  = Color::index('blue');
    $filter  = $img->scale(50)->getCanvas()->useFilter();
    $xOffset = $img->getWidth();
    $yOffset = $img->getHeight();

    $filter->grayScale();
    $img2->merge($img, $xOffset * 2)
            ->getCanvas()
            ->string(5, $xOffset * 2, $yOffset - 20, "(1) gray scale", $tColor);

    $filter->negate();
    $img2->merge($img, $xOffset * 3)
            ->getCanvas()
            ->string(5, $xOffset * 3, $yOffset - 20, "(2)+negate", $tColor);

    $filter->colorize(2, 118, 219);
    $img2->merge($img, $xOffset * 4)
            ->getCanvas()
            ->string(5, $xOffset * 4, $yOffset - 20, "(3)+colorize(2, 118, 219)", $tColor);

    $filter->negate();
    $img2->merge($img, $xOffset * 5)
            ->getCanvas()
            ->string(5, $xOffset * 5, $yOffset - 20, "(4)+negate", $tColor);

    $filter->brightness(30);
    $img2->merge($img, $xOffset * 6)
            ->getCanvas()
            ->string(5, $xOffset * 6, $yOffset - 20, "(5)+brightness(30)", $tColor);

    $filter->contrast(-30);
    $img2->merge($img, $xOffset * 2, $yOffset)
            ->getCanvas()
            ->string(5, $xOffset * 2, $yOffset * 2 - 20, "(6)+contrast(-30)", $tColor);

    $filter->pixelate(4, true);
    $img2->merge($img, $xOffset * 3, $yOffset)
            ->getCanvas()
            ->string(5, $xOffset * 3, $yOffset * 2 - 20, "(7)+pixelate(4)", $tColor);

    $filter->edgeDetect();
    $img2->merge($img, $xOffset * 4, $yOffset)
            ->getCanvas()
            ->string(5, $xOffset * 4, $yOffset * 2 - 20, "(8)+edge detect", $tColor);

    $filter->smooth(4);
    $img2->merge($img, $xOffset * 5, $yOffset)
            ->getCanvas()
            ->string(5, $xOffset * 5, $yOffset * 2 - 20, "(9)+smooth(5)", $tColor);

    $filter->emboss();
    $img2->merge($img, $xOffset * 6, $yOffset)
            ->getCanvas()
            ->string(5, $xOffset * 6, $yOffset * 2 - 20, "(10)+emboss", $tColor);

    echo microtime(true) - $time, "s\n";
    $img2->save('out/filtered2', 'png', 9);
} catch (Exception $exc) {
    echo $exc->getMessage(), PHP_EOL;
}
