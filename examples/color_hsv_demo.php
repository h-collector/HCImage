<?php

include dirname(__DIR__) . '/HCImage.class.php';

use HC\Image;
use HC\Color;

try {
    $time   = microtime(true);
    $image  = Image::create(256, 128, -1);
    $canvas = $image->getCanvas();
//    $handle = $canvas->getHandle();
//    for ($y = 0; $y < 60; ++$y)
//        for ($x = 0; $x < 256; ++$x) {
//            $color = Color::fromHSV($x | 7, 255, (($y+4) * 4) | 7, true);
//            //$canvas->setPixel($x, $y, $color->toInt());
//            imagesetpixel($handle, $x, $y, $color->toInt());
//        }
//    for ($y = 60; $y < 120; ++$y)
//        for ($x = 0; $x < 256; ++$x) {
//            $color = Color::fromHSV($x | 7, ((127 - ($y+4)) * 4) | 7, 255, true);
//            //$canvas->setPixel($x, $y, $color->toInt());
//            imagesetpixel($handle, $x, $y, $color->toInt());
//        }
//    for ($y = 120; $y < 128; ++$y)
//        for ($x = 0; $x < 256; ++$x) {
//            $color = (($x | 7) << 16) | (($x | 7) << 8) | ($x | 7);
//            //$canvas->setPixel($x, $y, $color);
//            imagesetpixel($handle, $x, $y, $color);
//        }

    $canvas->pixelOperation(function($unused, $x, $y) {
                        return Color::fromHSV($x | 7, 255, (($y + 4) * 4) | 7, true, false)->toInt();
                    }, array('endY' => 60), 0)
            ->pixelOperation(function($unused, $x, $y) {
                        return Color::fromHSV($x | 7, ((127 - ($y + 4)) * 4) | 7, 255, true, false)->toInt();
                    }, array('beginY' => 60, 'endY'   => 120), 0)
            ->pixelOperation(function($unused, $x, $y) {
                        return (($x | 7) << 16) | (($x | 7) << 8) | ($x | 7);
                    }, array('beginY' => 120, 'endY'   => 128), 0);

    $image2 = Image::create(720, 128, -1);
    $canvas = $image2->getCanvas();
//    $handle = $canvas->getHandle();
//    for ($y = 0; $y < 60; ++$y)
//        for ($x = 0; $x < 720; ++$x) {
//            $color = Color::fromHSV($x % 360, 1.0, ($y+4) / 64);
//            //$canvas->setPixel($x, $y, $color->toInt());
//            imagesetpixel($handle, $x, $y, $color->toInt());
//        }
//    for ($y = 60; $y < 120; ++$y)
//        for ($x = 0; $x < 720; ++$x) {
//            $color = Color::fromHSV($x % 360, (127 - ($y+4)) / 64, 1.0);
//            //$canvas->setPixel($x, $y, $color->toInt());
//            imagesetpixel($handle, $x, $y, $color->toInt());
//        }
//    for ($y = 120; $y < 128; ++$y)
//        for ($x = 0; $x < 720; ++$x) {
//            $color = Color::fromHSV(0.0, 0.0, $x % 360 / 360);
//            //$canvas->setPixel($x, $y, $color->toInt());
//            imagesetpixel($handle, $x, $y, $color->toInt());
//        }

    $canvas->pixelOperation(function($unused, $x, $y) {
                        return Color::fromHSV($x % 360, 1.0, ($y + 4) / 64, false, false)->toInt();
                    }, array('endY' => 60), 0)
            ->pixelOperation(function($unused, $x, $y) {
                        return Color::fromHSV($x % 360, (127 - ($y + 4)) / 64, 1.0, false, false)->toInt();
                    }, array('beginY' => 60, 'endY'   => 120), 0)
            ->pixelOperation(function($unused, $x, $y) {
                        return Color::fromHSV(0.0, 0.0, $x % 360 / 360, false, false)->toInt();
                    }, array('beginY' => 120, 'endY'   => 128), 0);

// $image->resizeToWidth(360);

    $height    = max($image->getHeight(), $image2->getHeight());
    $maxWidth  = max($image->getWidth(), $image2->getWidth());
    $sumHeight = $image->getHeight() + $image2->getHeight();
    $image->crop(0, 0, $maxWidth, $sumHeight)
            ->merge($image, 256) //will tripple image XD
            ->merge($image2, 0, $height)
            ->scale(200, true);
    //$image->output();
    echo microtime(true) - $time, "s\n";
    $image->getCanvas()->png('out/hsv.png');
} catch (Exception $exc) {
    echo $exc->getMessage(), PHP_EOL;
}
