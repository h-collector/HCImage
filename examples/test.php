<?php

include dirname(__DIR__) . '/HCImage.class.php';

use HC\Image;
use HC\Canvas;
use HC\Color;

//creating 20x20 image
$image1 = Image::create(30, 30);
$image1->getCanvas()->pixelOperation(function($unused, $x, $y) {
            return Color::fromHSV($x * $y, 1, 1)->toInt();
        });
$image1->save('./tmp/lol.png');
unset($image1);

//loading using class alias
/* @var $image2 \HC\Image */
$image2 = new HCImage('./tmp/lol.png');
$canvas = $image2->resize(40, 40)->getCanvas();
$canvas->filledRectangle(0, 0, 30, 30, 0x30606000);
$canvas->line(2, 2, 30, 40, 0xff);

//merging images
$image3 = Image::create(20, 20, 'red');
$image2->merge($image3, Image::AUTO, Image::AUTO);
echo $image2->encode(IMAGETYPE_PNG, 9);
$image2->save('./tmp/lol', 'jpg');
$image2->scale2x()
        ->save('./tmp/lol2', 'png', 9);

//standalone canvas
$img   = new Canvas(imagecreate(20, 20));
$green = $img->colorAllocate(0, 255, 0);
$black = $img->colorAllocate(0, 0, 0);
$img->filledRectangle(0, 0, 20, 20, $black);
$img->filledArc(10, 10, 10, 10, 10, 150, $green, IMG_ARC_PIE);
$img->png('./tmp/canvas.png', 9);

$img->updateHandle($img->getHandle(), false);
echo Image::load($img->getHandle())->encode();