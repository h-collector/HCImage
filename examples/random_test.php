<?php

include dirname(__DIR__) . '/HCImage.class.php';

use HC\Image;
use HC\Canvas;
use HC\Color;

try {
    //creating 20x20 image
    $image1 = Image::create(30, 30);
    $image1->getCanvas()->pixelOperation(function($unused, $x, $y) {
                return Color::fromHSV($x * $y, 1, 1)->toInt();
            });
    $image1->save('./out/lol.png');
    unset($image1);

//loading using class alias
    /* @var $image2 \HC\Image */
    $image2 = new HCImage('./out/lol.png');
    $canvas = $image2->resize(40, 40)->getCanvas();
    $canvas->filledRectangle(0, 0, 30, 30, 0x30606000);
    $canvas->line(2, 2, 30, 40, 0xff);

//merging images
    $image2->merge(Image::create(20, 20, 'red'), Image::AUTO, Image::AUTO);
    echo $image2->encode(IMAGETYPE_PNG, 9);
    $image2->save('./out/lol', 'jpg');
    $image2->scale2x()
            ->save('./out/lol2', 'png', 9);
    unset($image2);

//standalone canvas
    $img   = new Canvas(imagecreate(20, 20));
    $green = $img->colorAllocate(0, 255, 0);
    $black = $img->colorAllocate(0, 0, 0);
    $img->filledRectangle(0, 0, 20, 20, $black);
    $img->filledArc(10, 10, 10, 10, 10, 150, $green, IMG_ARC_PIE);
    $img->png('./out/canvas.png', 9);
    $img->setPixel(2000, 200, $green);

    $img->updateHandle($img->getHandle(), false);
    echo Image::load($img->getHandle())->flip();
    echo PHP_EOL, "Destroyed: ", is_resource($img->getHandle()) ? "no" : "yes";

    $t = microtime(true);
    echo PHP_EOL, Image::load('./out/lol2.png')->scale(1000, false)->flip(true)->getCanvas()->count();
    echo PHP_EOL, microtime(true) - $t, ":", memory_get_peak_usage() / (1024 * 1024), PHP_EOL;
} catch (Exception $exc) {
    echo $exc->getMessage(), PHP_EOL;
}

