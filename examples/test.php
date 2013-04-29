<?php

include dirname(__DIR__ ) . '/HCImage.class.php';

use HC\Image;

//creating 20x20 image
$image1 = Image::create(20, 20);
$image1->save('./tmp/lol.png');
unset($image1);

//loading using class alias
/* @var $image2 \HC\Image */ 
$image2 = new HCImage('./tmp/lol.png');
$image2->resize(40, 40);
$image2->getCanvas()->filledRectangle(0, 0, 40, 40, 0x00606000);

//merging images
$image3 = Image::create(20, 20,'red');
$image2->merge($image3, Image::AUTO, Image::AUTO);
$image2->save('./tmp/lol.png');


