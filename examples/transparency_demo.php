<?php

include dirname(__DIR__) . '/HCImage.class.php';

use HC\Image;

try {
    //Load Images
    $image1 = \HC\Image::load('img/PNG_transparency_demonstration_1.png');
    $image2 = \HC\Image::load('img/PNG_transparency_demonstration_2.png');
    //$image3 = clone $image1;//-> new Image($image1, false, true)
    $image3 = new Image($image1, false, false);

    //merge non transparent image to non transparent
    $image2->merge($image1, 200, 200);
    $image2->save('out/trans1');

    //resize, trim, and paste over transparent image
    $image1->resize(200, 200);
    $image1->save('out/trans2');
    $image1->trim();
    $image1->getCanvas()->filledRectangle(20, 20, 50, 50, 0x50ff0000);
    $image1->save('out/trans3');
    unset($image1);

    //resieze to height transparent image and paste over it 
    //nontransparent with translucency 50, save using filters
    $image2->resizeToHeight(100);
    $image3->merge($image2, Image::AUTO, Image::AUTO, 50);
    $image3->save('out/trans4', false, 9);
} catch (Exception $exc) {
    echo $exc->getMessage(), PHP_EOL;
}
