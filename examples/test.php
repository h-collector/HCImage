<?php

include dirname(__DIR__) . '/HCImage.class.php';

use HC\Image;

try {
    //Load Images
    $img3 = clone $img2 = clone $img = new Image('img/test_original.gif') ;
    
    $time = microtime(true);
    for($i=0;$i<3;++$i)
        $img->scale2x();
    echo microtime(true) - $time, "s\n";
    $img->save('/tmp/scale2x', 'png');
    unset($img);
    //echo memory_get_peak_usage(true), "s\n";
    
//    $time = microtime(true);
//    for($i=0;$i<3;++$i)
//        $img3->scale2xopti();
//    echo microtime(true) - $time, "s\n";
//    $img3->save('/tmp/scale2xopti', 'png');
//    unset($img3);
    //echo memory_get_peak_usage(true), "s\n";
    
    $time = microtime(true);
    for($i=0;$i<2;++$i)
        $img2->scale3x();
    echo microtime(true) - $time, "s\n";
    $img2->save('/tmp/scale3x', 'png');
    unset($img2);
    //echo memory_get_peak_usage(true), "s\n";
} catch (Exception $exc) {
    echo $exc->getMessage(), PHP_EOL;
}