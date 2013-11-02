<?php

spl_autoload_register(function($className) {
            $classFile = str_replace(array('\\', '_'), DIRECTORY_SEPARATOR, $className);
            include /* __DIR__ . DIRECTORY_SEPARATOR . */$classFile . '.php';
            return class_exists($className, false);
        });

//require_once __DIR__ . '/HC/GDResource.php';
//require_once __DIR__ . '/HC/Canvas.php';
//require_once __DIR__ . '/HC/Color.php';
//require_once __DIR__ . '/HC/Image.php';
////Helpers
//include_once __DIR__ . '/HC/Helper/Convolution.php';
//include_once __DIR__ . '/HC/Helper/PixelOps.php';
//include_once __DIR__ . '/HC/Helper/Filter.php';
//include_once __DIR__ . '/HC/Helper/Cache.php';
//include_once __DIR__ . '/HC/Helper/Font.php';

////aliasing
//class_alias('\HC\GDResource', 'HCGDResource');
//class_alias('\HC\Canvas', 'HCCanvas');
//class_alias('\HC\Color', 'HCColor');
//class_alias('\HC\Image', 'HCImage');
////Helpers
//class_alias('\HC\Helper\Convolution', 'HCHelperConvolution');
//class_alias('\HC\Helper\PixelOps', 'HCHelperPixelOps');
//class_alias('\HC\Helper\Filter', 'HCHelperFilter');
//class_alias('\HC\Helper\Cache', 'HCHelperCache');
//class_alias('\HC\Helper\Font', 'HCHelperFont');