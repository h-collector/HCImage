<?php

include dirname(__DIR__) . '/HCImage.class.php';

use HC\Image;

try {
    $img  = Image::load('PNG_transparency_demonstration_1.png')->trim();
    $img2 = clone $img;
    $img->getCanvas()
            ->useFilter()
            ->grayScale()
            ->negate()
            ->colorize(2, 118, 219)
            ->negate()
            ->brightness(30)
            ->contrast(-30);

    $img2->crop(0, 0, $img->getWidth() * 2, $img->getHeight())
            ->merge($img, $img->getWidth(), 0)
            ->save('out/filtered', 'png', 9);
} catch (Exception $exc) {
    echo $exc->getMessage(), PHP_EOL;
}
