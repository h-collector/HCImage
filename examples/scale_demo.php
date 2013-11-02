<?php

include dirname(__DIR__) . '/HCImage.class.php';

use HC\Image;
use HC\Color;

try {
    //Load Images
    $red  = Color::index('red');
    $img  = new Image('img/test_original.gif');
    $img2 = clone $img;
    $img3 = clone $img;

    $img ->scale(200, true) ->getCanvas()->string(5, 30, 110, 'Scale 2x resampling', $red);
    $img2->scale(200, false)->getCanvas()->string(5, 30, 110, 'Scale 2x resizing', $red);
    $img3->scale2x()        ->getCanvas()->string(5, 30, 110, 'Scale 2x', $red);

    $img->crop(0, 0, $img->getWidth() * 3)
        ->merge($img2, $img2->getWidth(), 0)
        ->merge($img3, $img3->getWidth() * 2, 0)
        ->save('out/scale','png',9);
        
    $img->scale(200, true)->getCanvas()->getConvolution()->dilate();
    $img->scale(50,  true)->getCanvas()->getConvolution()->sharpenNice(); 
    $img->save('out/scale2','png',9);
} catch (Exception $exc) {
    echo $exc->getMessage(), PHP_EOL;
}
