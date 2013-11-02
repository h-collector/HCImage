<?php

namespace HC\Helper;

use HC\Image;
use HC\Canvas;
use HC\Color;
use RuntimeException;

/**
 * Description of Effect
 *
 * Helper class, 
 *
 * @package    HC
 * @subpackage Helper
 * 
 * @author  h-collector <githcoll@gmail.com>
 * @link    http://hcoll.onuse.pl/projects/view/HCImage
 * @license GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 * 
 */
class Effect {

    /**
     * Merge watermark onto source image at given position and return Image instance
     * To save ->save(), to output ->output(), to encode ->encode().
     * Alias to Image::load($srcImage)->merge($watermark)
     * 
     * @param mixed $srcImage
     * @param mixed $watermark
     * @param int|AUTO|MAX $x    x position to start from, can be negative
     *                           AUTO center horizontally, MAX to align right
     * @param int|AUTO|MAX $y    y position to start from, can be negative
     *                           AUTO center vertically, MAX to align bottom
     * @param int          $pct  The two images will be merged according to pct which can range from -100 to 100.
     *                           null - use imagecopy, -100-0 - useimagecopygray, 1-100 - use imagecopymerge
     *                           (doesn't copy alpha of merged image if pct <> null, use setTransparency w. null)
     * @param bool         $copy copy and return new image, this image will not be modified
     *                           (if true and $pct = 100, blend alpha, replace otherwise)
     * @return Image
     * @throws RuntimeException
     */
    public static function watermark($srcImage, $watermark, $x = 0, $y = 0, $pct = null, $copy = false) {
        $srcImage  = $srcImage instanceof Image ? $srcImage : Image::load($srcImage);
        $watermark = $watermark instanceof Image ? $watermark : Image::load($watermark);
        return $srcImage->merge($watermark, $x, $y, $pct, $copy);
    }

    /**
     * 
     * @param mixed $srcImage
     * @param int $size
     * @param int $transparency
     * @param int $angle        anticlockwise reflection rotation [0-360deg], 
     *                          0 bottom, 90 right, 180 top, 270 left
     * @param int $bgColor
     * @return Image
     */
    public static function reflection($srcImage, $size = null, $transparency = 0x7f, $angle = 0, $bgColor = null) {
        $srcImage    = $srcImage instanceof Image ? $srcImage : Image::load($srcImage);
        $height      = $srcImage->getHeight();
        $size        = $size ? $size : $height;
        $opacityStep = $transparency / $size;
        if ($angle)
            $srcImage->rotate($angle, $bgColor);
        $srcImage->crop(0, 0, null, $height + $size)
                ->useCanvas(function(Canvas $canvas) use ($height, $opacityStep) {
                    $color = null;
                    $canvas->usePixelOps(0, $height)
                           ->apply(function($unused, $x, $y, $handle) use (&$color, $height, $opacityStep) {
                                $i = $y - $height + 1;
                                if ($i <= $height){
                                    $rgba  = imagecolorat($handle, $x, $height - $i);
                                    $color = Color::fromInt($rgba)
                                            ->dissolve($opacityStep * $i)
                                            ->toInt();
                                }
                                return $color;
                            }, 0);
                });
        if ($angle)
            $srcImage->rotate(-$angle, $bgColor);
        return $srcImage;
    }

}

