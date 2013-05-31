<?php

namespace HC;

use InvalidArgumentException;

/**
 * Some useful methods for Canvas::pixelOperation and Canvas::convolution
 *
 * @package HC
 * @author  h-collector <githcoll@gmail.com>
 *          
 * @link    http://hcoll.onuse.pl/view/HCImage
 * @license GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 */
class PixelOps {

    /**#@+ @var string */
    const MAT_MEAN_REMOVAL  = 'mean removal (sharpen)';
    const MAT_SHARPEN       = 'sharpen';
    const MAT_SHARPEN_NICE  = 'sharpen nice';
    const MAT_UNSHARPEN     = 'unsharpen';
    const MAT_DILATE        = 'dilate';
    const MAT_BLUR          = 'blur';
    const MAT_EMBOSS        = 'emboss';
    const MAT_EMBOSS_SUBTLE = 'emboss subtle';
    const MAT_EDGE_DETECT   = 'edge detect';
    const MAT_EDGE_DETECT2  = 'edge detect2';
    /**#@-*/

    /**
     * Get Convolution matrix by type
     * 
     * @see imagefilter,imageconvolution,imagelayer
     * @param string $type type of matrix
     * @return array float|int[3][3]
     */
    public static function getConvMatrix($type) {
        switch ($type) {
            case self::MAT_MEAN_REMOVAL://IMG_FILTER_MEAN_REMOVAL
                return array(
                    array(-1, -1, -1), array(-1,  9, -1), array(-1, -1, -1)
                );
            case self::MAT_SHARPEN:
                return array(
                    array( 0, -2,  0), array(-2, 11, -2), array( 0, -2,  0)
                );
            case self::MAT_SHARPEN_NICE:
                return array(
                    array(-1.2, -1, -1.2), array(-1.0, 20, -1.0), array(-1.2, -1, -1.2)
                );
            case self::MAT_UNSHARPEN://IMG_FILTER_SMOOTH
                return array(
                    array(-1, -1, -1), array(-1, 17, -1), array(-1, -1, -1)
                );
            case self::MAT_DILATE:
                return array(
                    array( 0,  1,  0), array( 1,  1,  1), array( 0,  1,  0)
                );
            case self::MAT_BLUR://IMG_FILTER_GAUSSIAN_BLUR
                return array(
                    array( 1,  2,  1), array( 2,  4,  2), array( 1,  2,  1)
                );
            case self::MAT_EMBOSS://IMG_FILTER_EMBOSS
                return array(
                    array( 2,  0,  0), array( 0, -1,  0), array( 0,  0, -1)
                );
            case self::MAT_EMBOSS_SUBTLE:
                return array(
                    array( 1,  1, -1), array( 1,  3, -1), array( 1, -1, -1)
                );
            case self::MAT_EDGE_DETECT://IMG_FILTER_EDGEDETECT
                return array(
                    array( 1,  1,  1), array( 1, -7,  1), array( 1,  1,  1)
                );//offset 127
            case self::MAT_EDGE_DETECT2:
                return array(
                    array(-5,  0,  0), array( 0,  0,  0), array( 0,  0,  5)
                );
            default: throw new InvalidArgumentException('Invalid Matix type');
        }
    }

    /**
     * Add alpha to image
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
     * Add salt and pepper to image
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
     * Add rgb noise to image
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
     * Swap rgb channels in image
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
