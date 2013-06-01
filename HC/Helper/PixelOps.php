<?php

namespace HC\Helper;

use HC\Canvas;
use HC\Color;

use InvalidArgumentException;

/**
 * Some useful methods for Canvas::pixelOperation
 *
 * @package HC\Helper
 * @author  h-collector <githcoll@gmail.com>
 *          
 * @link    http://hcoll.onuse.pl/view/HCImage
 * @license GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 * 
 * @method HC\Canvas transparency($alpha)
 * @method HC\Canvas saltAndPepper($factor = 20, $salt = 0x00ffffff, $pepper = 0x00000000)
 * @method HC\Canvas noise($factor = 100)
 * @method HC\Canvas swapColor($swap = 'rgb')
 * @method HC\Canvas filterHue(Color $color)
 */
class PixelOps {

    private $canvas  = null,
            $forOpts = array(),
            $cache   = false;

    private function __construct(Canvas $canvas, $forOpts, $cache) {
        $this->canvas  = $canvas;
        $this->forOpts = $forOpts;
        $this->cache   = $cache;
    }
    
    /**
     * Apply predefined Canvas::pixelOperation (one time)
     * Note: by default sets imagealphablending to false
     * 
     * @param Canvas $canvas
     * @param int  $beginX
     * @param int  $beginY
     * @param int  $endX   canvas full width if null
     * @param int  $endY   canvas full height if null
     * @param int  $stepX
     * @param int  $stepY
     * @param bool $cache speed up color calculations but use more memory
     * @param bool $alphaBlending
     * @return PixelOps
     */
    public static function on(Canvas $canvas, 
            $beginX = 0, $beginY = 0, $endX = null, $endY = null, $stepX = 1, $stepY = 1, 
            $cache = false, $alphaBlending = false) {
        $forOpts = compact('beginX', 'beginY', 'endX', 'endY', 'stepX', 'stepY');
        $canvas->alphaBlending($alphaBlending);
        return new self($canvas, $forOpts, $cache);
    }
    
    /**
     * Apply custom callback with set range and step parameters
     * 
     * @param callback $operation  Color|int function([Color|int $rgb[, int $x[, int $y[, $handle]]]])
     * @param int      $returnMode declare what callback will return 0-int, 1-Color, 2-Color|int, 3-int|Color
     * @param int      $mode       declare what callback want for argument as 0-Color, 1-index, 2-0, 
     *                             by default automatic (typehint Color->1, $rgb = $unused -> 2)
     * @throws InvalidArgumentException
     * @return Canvas
     */
    public function apply($callback, $returnMode = 3, $mode = 0) {
        $canvas       = $this->canvas;
        $forOpts      = $this->forOpts;
        $cache        = $this->cache;
        $this->canvas = null;//allow reuse?

        if ($forOpts['endX'] === null)
            $forOpts['endX'] = $canvas->sX();
        if ($forOpts['endY'] === null)
            $forOpts['endY'] = $canvas->sY();
        return $canvas->pixelOperation($callback, $forOpts, $returnMode, $mode, $cache);
    }

     public function __call($name, $arguments) {
        $closure = call_user_func_array(__CLASS__ . '::pixel' . ucfirst($name), $arguments);
        switch ($name) {
            case 'transparency':  $returnMode = 1; break;
            case 'saltAndPepper': $returnMode = 0; break;
            case 'noise':         $returnMode = 1; break;
            case 'swapColor':     $returnMode = 0; break;
            case 'filterHue':     $returnMode = 1; break;
            default:              $returnMode = 3;
        }
        return $this->apply($closure, $returnMode);
    }
    
    /**
     * Add alpha to image, use with caching
     * 
     * @see Canvas::pixelOperation(),Image::merge()
     * 
     * @param int $alpha transparency to add
     * @return callable pixel dissolver
     */
    public static function pixelTransparency($alpha) {
        return function(Color $pixel) use ($alpha) {
                    return $pixel->dissolve($alpha, true);
                };
    }

    /**
     * Add salt and pepper to image, dont use with caching
     * 
     * @see Canvas::pixelOperation()
     * @param int $factor how much of salt and pepper to add, where 1 is max
     * @param int $salt   color of salt
     * @param int $pepper color of pepper
     * @return callable salt or pepper maker
     */
    public static function pixelSaltAndPepper($factor = 20, $salt = 0x00ffffff, $pepper = 0x00000000) {
        $white = (int) ($factor / 2 - 1);
        $black = (int) ($factor / 2 + 1);
        return function() use ($factor, $white, $black, $salt, $pepper) {
                    $random = mt_rand(0, $factor);
                    if ($random === $white) return $salt;
                    if ($random === $black) return $pepper;
                };
    }

    /**
     * Add rgb noise to image, dont use with caching
     * 
     * @see Canvas::pixelOperation()
     * @param int $factor how strong noise to add, 255 is max
     * @return callable noise maker
     */
    public static function pixelNoise($factor = 100) {
        if ($factor < 0)
            $factor = -$factor;
        return function(Color $pixel) use ($factor) {
                    return $pixel->adjustBrightness(mt_rand(-$factor, $factor), true);
                };
    }

    /**
     * Swap rgb channels in image, use with caching
     * 
     * @see Canvas::pixelOperation()
     * @see Color::rgb()
     * @see Color::rbg()
     * @see Color::bgr()
     * @see Color::brg()
     * @see Color::gbr()
     * @see Color::grb()
     * @param string  $swap type of color components swap
     * @return callable color swapper
     */
    public static function pixelSwapColor($swap = 'rgb') {
        if (!method_exists(Color::clear(), $swap))
            throw new InvalidArgumentException('Swap function does not exists');

//        return function(Color $pixel) use ($swap) {
//                    return $pixel->{$swap}(true);
//                };
        switch ($swap) {//optimized for speed x2
            case 'rbg': return function($p){ return $p&0x7fff0000                     | ($p&0xff00)>> 8 | ($p&0xff)<< 8;};
            case 'bgr': return function($p){ return $p&0x7f00ff00 | ($p&0xff0000)>>16                   | ($p&0xff)<<16;};
            case 'brg': return function($p){ return $p&0x7f000000 | ($p&0xff0000)>> 8 | ($p&0xff00)>> 8 | ($p&0xff)<<16;};
            case 'gbr': return function($p){ return $p&0x7f000000 | ($p&0xff0000)>>16 | ($p&0xff00)<< 8 | ($p&0xff)<< 8;};
            case 'grb': return function($p){ return $p&0x7f0000ff | ($p&0xff0000)>> 8 | ($p&0xff00)<< 8;};
            default   : return function($p){ return $p; };
        }
    }

    /**
     * Hue filter use with caching
     * 
     * @see Canvas::pixelOperation()
     * @param Color $color color to filter
     * @return callable filter
     */
    public static function pixelFilterHue(Color $color) {
        $rHue = $color->red   / $color->sum();
        $gHue = $color->green / $color->sum();
        $bHue = $color->blue  / $color->sum();
        return function(Color $pixel) use ($rHue, $gHue, $bHue) {
                    $r            = $pixel->red;
                    $g            = $pixel->green;
                    $b            = $pixel->blue;
                    $pixel->red   = (int)($r * $rHue + $g * $bHue + $b * $gHue);
                    $pixel->green = (int)($r * $gHue + $g * $rHue + $b * $bHue);
                    $pixel->blue  = (int)($r * $bHue + $g * $gHue + $b * $rHue);
                    return $pixel;
                };
    }

}
