<?php

spl_autoload_register(function($className) {
            $classFile = str_replace(array('\\', '_'), DIRECTORY_SEPARATOR, $className);
            include /* __DIR__ . DIRECTORY_SEPARATOR . */$classFile . '.php';
            return class_exists($className, false);
        });
//require_once __DIR__ . '/HC/Image.php';
//require_once __DIR__ . '/HC/Canvas.php';
//require_once __DIR__ . '/HC/Color.php';
////Helpers
//include_once __DIR__ . '/HC/Helper/PixelOps.php';
//include_once __DIR__ . '/HC/Helper/Filter.php';
//include_once __DIR__ . '/HC/Helper/Convolution.php';
//
////aliasing
//class_alias('\HC\Image', 'HCImage');
//class_alias('\HC\Canvas', 'HCCanvas');
//class_alias('\HC\Color', 'HCColor');
////Helpers
//class_alias('\HC\Helper\PixelOps', 'HCHelperPixelOps');
//class_alias('\HC\Helper\Filter', 'HCHelperFilter');
//class_alias('\HC\Helper\Convolution', 'HCHelperConvolution');
