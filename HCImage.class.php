<?php

require_once __DIR__ . '/HC/Image.php';
require_once __DIR__ . '/HC/Canvas.php';
require_once __DIR__ . '/HC/Color.php';
include_once __DIR__ . '/HC/PixelOps.php';

//aliasing
class_alias('\HC\Image', 'HCImage');
class_alias('\HC\Canvas', 'HCCanvas');
class_alias('\HC\Color', 'HCColor');
class_alias('\HC\PixelOps', 'HCPixelOps');
