<?php

include dirname(__DIR__) . '/HCImage.class.php';

use HC\Image;
use HC\Color;
use HC\Canvas;
use HC\Helper\Filter;
use HC\Helper\Cache;
use HC\Helper\Effect;

try {
$cacheGet = function($hash) {
            return @file_get_contents('/tmp/serial.' . $hash);
        };
$cacheSet = function($hash, $data) {
            return @file_put_contents('/tmp/serial.' . $hash, $data);
        };

////////////////
$cache = new Cache($cacheGet, $cacheSet);

$cache->run(function() {
    
        });

///////////////
$cache = new Cache(function($hash) {
            return @file_get_contents('/tmp/serial.' . $hash);
        }, function($hash, $data) {
            return @file_put_contents('/tmp/serial.' . $hash, $data);
        });

$cache->run(function() {
            
        });

$cache->run(function() {
            
        });

$cache->rerun();

////////////////
$t = microtime(true);
//dont user effect if once applied
Cache::cache(function() {
            return Effect::reflection('img/mask2.png', 400);
        }, $cacheGet, $cacheSet)->save('/tmp/reflection3.png');

//dont save if exist
Cache::cache(function() {
            Effect::reflection('img/mask2.png', 400)->save('/tmp/reflection1.png');
            Effect::reflection('img/mask2.png', 400, 127, 45, Color::index('red'))->save('/tmp/reflection2.png');
        }, function($hash) {
            return file_exists('/tmp/reflection2.png');
        }, function($hash, $data) {
            
        });

$img = new Image('/tmp/reflection2.png');
$img->useCache(function(Image $image) {
            return $image->useCanvas(function(Canvas $canvas) {
                $canvas->getFilter()->negate()->colorize(40, 100, -20);
             });
        }, $cacheGet, $cacheSet)
        ->save('/tmp/reflection4.png');;
echo "time", microtime(true) - $t, "\n";
} catch (ErrorException $exc) {
    echo $exc->getMessage(), PHP_EOL;
}