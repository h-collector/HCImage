<?php

include dirname(__DIR__) . '/HCImage.class.php';

use HC\Image;
use HC\Color;

try {
    //Load Images
    $time   = microtime(true);
    $img    = Image::create(260, 480);
    $canvas = $img->getCanvas();

    echo "Base colors RGB/CMYK\n";
    echo "red     ", $red     = Color::fromString('red'), PHP_EOL;
    echo "lime    ", $lime    = Color::fromString('lime'), PHP_EOL;
    echo "blue    ", $blue    = Color::fromString('blue'), PHP_EOL;
    echo "yellow  ", $yellow  = $red->sumWithColor($lime), PHP_EOL;
    echo "magenta ", $magenta = $red->sumWithColor($blue), PHP_EOL;
    echo "cyan    ", $cyan    = $blue->sumWithColor($lime), PHP_EOL;
    echo "white   ", $white   = Color::get('white'), PHP_EOL;
    echo "black   ", $black   = Color::get('black'), PHP_EOL;
    echo "clear   ", $clear   = Color::clear(), PHP_EOL;

    for ($i = 0, $w = $img->getWidth(); $i < $w; $i+=10) {
        $canvas->filledRectangle($i, 0, $i + 10, 20, $red($i / 2));
        $canvas->filledRectangle($i, 20, $i + 10, 40, $lime($i / 2));
        $canvas->filledRectangle($i, 40, $i + 10, 60, $blue($i / 2));
        $canvas->filledRectangle($i, 60, $i + 10, 80, $yellow($i / 2));
        $canvas->filledRectangle($i, 80, $i + 10, 100, $magenta($i / 2));
        $canvas->filledRectangle($i, 100, $i + 10, 120, $cyan($i / 2));
        $canvas->filledRectangle($i, 120, $i + 10, 140, $white($i / 2));
        $canvas->filledRectangle($i, 140, $i + 10, 160, $black($i / 2));
    }
    $regionW = $img->copyRegion(0, 0, 260, 160, $white);
    $regionB = $img->copyRegion(0, 0, 260, 160, $black);

    $img->merge($regionB, 0, 160)->merge($regionW, 0, 320);
    unset($regionW, $regionB);

    echo "HSV:    ", $hsv = Color::fromHSV(200, 0.5, 1.0), PHP_EOL;
    print_r($hsv->getAsHSV());

    echo "Conversions:\n";
    echo "Base:       ", $c = Color::fromArray(array(200, 100, 10)), PHP_EOL;
    echo "BRG:        ", $c->brg(), PHP_EOL;
    echo "Dissolve:   ", $c->dissolve(0x40), PHP_EOL;
    echo "Brighness:  ", $c->adjustBrightness(0x20), PHP_EOL;
    echo "Gray:       ", $c->gray(), PHP_EOL;
    echo "Mixed:      ", $c->mixWithColor(Color::get('gold')), PHP_EOL;
    echo "Sumed:      ", $c->sumWithColor(Color::get('gold')), PHP_EOL;
    echo "Subtracted: ", $c->subtractColor(Color::get('gold')), PHP_EOL;
    echo "Index:      ", $c->toInt(), PHP_EOL;
    echo "Array:      ", print_r($c->toArray(), true), PHP_EOL;

//    echo "Histogram\n";
//    print_r($img->histogram());
    echo "Number of colors in image: ", count($img->histogram()), PHP_EOL;
    
    echo "Time: ", microtime(true) - $time, "s\n";
    echo "Max mem ", memory_get_peak_usage(true), "B\n";
    echo "Img as html: ", $img->save('out/colors', 'png')->html();
} catch (Exception $exc) {
    echo $exc->getMessage(), PHP_EOL;
}