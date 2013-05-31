<?php

include dirname(__DIR__) . '/HCImage.class.php';

use HC\Image;
use HC\Color;

try {
    $red      = Color::get('red');
    $green    = Color::get('green');
    $orange   = $red->mixWithColor($green);
    $img      = new Image('Grayscale_8bits_palette_sample_image.png');
    $canvas   = $img->getCanvas();
    $canvas->filter(IMG_FILTER_COLORIZE, $orange->red, $orange->green, $orange->blue);
    $canvas->convolution(array(
        array(-1.2, -1, -1.2),
        array(-1, 20, -1),
        array(-1.2, -1, -1.2)
    ));
    $img->scale(200)
        ->save('out/colorized','png',9);
} catch (Exception $exc) {
    echo $exc->getMessage(), PHP_EOL;
}
