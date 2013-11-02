<?php

include dirname(__DIR__) . '/HCImage.class.php';

use HC\Color;
use HC\Image;
//use HC\GDResource;

try {
    //GDResource::$debug = true;
    $width   = 256;
    $height  = 256;
    $white   = Color::index('white');
    $mask1   = Image::load('img/mask1.png');
    $mask2   = Image::load('img/mask2.png');
    $rainbow = Image::load('out/hsv.png')->crop(0, Image::MAX, $width, $height);

    $draw = function($rows, $rainbow) use ($white, $height, $width) {
        $t      = microtime(true);
        $masked = Image::create($width * 4, $height * 4);
        $canvas = $masked->getCanvas();
        foreach ($rows as $j => $row) {
            list($mask, $text, $overlay) = $row;

            for ($i = 0; $i < 4; ++$i) {
                $inv     = ($i & 0x1) ? 1 : 0;
                $alpha   = ($i & 0x2) ? 1 : 0;
                //$rainbow->overlay|mask($mask, 0|1, 0|1, 'auto','auto', true)
                $toMerge = $rainbow->{$overlay ? 'overlay' : 'mask'}($mask, $inv, $alpha, Image::AUTO, Image::AUTO, true);
                $masked->merge($toMerge, $i * $width, $j * $height, null, false);
                $canvas->string(5, $i * $width + 16, ($j + 1) * $height - 16, "$text: (inv:$inv, alpha:$alpha)", $white);
            }
        }
        echo "time", microtime(true) - $t, "\n";
        return $masked;
    };
    //normal//////////////////////////////////////////////
    $draw(array(
        array($mask1, 'mask 1', false),//mask half translucent
        array($mask2, 'mask 2', false), //mask inverted no alpfa
        array($mask1, 'overlay 1', true),//overlay
        array($mask2, 'overlay 2', true),//overlay
    ), $rainbow)->save('out/masked1.png');
    
    //translucent image////////////////////////////////////////////// 
    $transRainbow = $rainbow->copy()->setTransparency(40);
    $draw(array(
        array($mask1, 'mask 1', false),//mask half translucent
        array($mask2, 'mask 2', false), //mask inverted no alpfa
        array($mask1, 'overlay 1', true),//overlay
        array($mask2, 'overlay 2', true),//overlay
    ), $transRainbow)->save('out/masked2.png');
    
    //translucent mask//////////////////////////////////////////////
    $mask1->setTransparency(40);
    $mask2->setTransparency(40);
    $draw(array(
        array($mask1, 'mask 1', false),//mask half translucent
        array($mask2, 'mask 2', false), //mask inverted no alpfa
        array($mask1, 'overlay 1', true),//overlay
        array($mask2, 'overlay 2', true),//overlay
    ), $rainbow)->save('out/masked3.png');
    
} catch (ErrorException $exc) {
    echo $exc->getMessage(), PHP_EOL;
}
