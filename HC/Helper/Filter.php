<?php

namespace HC\Helper;

use HC\GDResource;

use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;

/**
 * Helper class, wrapper around imagefilter
 *
 * @package    HC
 * @subpackage Helper
 * 
 * @author  h-collector <githcoll@gmail.com>
 * @link    http://hcoll.onuse.pl/projects/view/HCImage
 * @license GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 * 
 * @uses GDResource
 */
class Filter {

    private /** @var GDResource */ $resource = null;

    /**
     * Note: Sets imagealphablending to false
     * 
     * @param resource|GDResource $resource
     * @throws InvalidArgumentException
     */
    public function __construct($resource) {
        if (!($resource instanceof GDResource))
            $resource = new GDResource($resource);
 
        imagealphablending($resource->gd, false);
        $this->resource = $resource;
    }

    /**
     * 
     * @see imagefilter()
     * @param int $brightness -255 = min brightness, 0 = no change, +255 = max brightness
     * @return Filter
     * @throws RuntimeException
     * @throws OutOfBoundsException
     */
    public function brightness($brightness) {
        if (-255 > $brightness || $brightness > 255)
            throw new OutOfBoundsException("In " . __METHOD__ . " value of brightness out of bounds -255 < $brightness < 255");
        if (false === imagefilter($this->resource->gd, IMG_FILTER_BRIGHTNESS, $brightness))
            throw new RuntimeException('Appling ' . __METHOD__ . ' failed');
        return $this;
    }

    /**
     * 
     * @see imagefilter()
     * @param int $contrast -100 = max contrast, 0 = no change, +100 = min contrast
     * @return Filter
     * @throws RuntimeException
     * @throws OutOfBoundsException
     */
    public function contrast($contrast) {
        if (-100 > $contrast || $contrast > 100)
            throw new OutOfBoundsException("In " . __METHOD__ . " value of contrast out of bounds -100 < $contrast < 100");
        if (false === imagefilter($this->resource->gd, IMG_FILTER_CONTRAST, $contrast))
            throw new RuntimeException('Appling ' . __METHOD__ . ' failed');
        return $this;
    }

    /**
     * Adds (subtracts) specified RGB values to each pixel. 
     * 
     * @see imagefilter()
     * @param int $red   -255 = min, 0 = no change, +255 = max
     * @param int $green -255 = min, 0 = no change, +255 = max
     * @param int $blue  -255 = min, 0 = no change, +255 = max
     * @param int $alpha -255 = min, 0 = no change, +255 = max
     * @return Filter
     * @throws RuntimeException
     * @throws OutOfBoundsException
     */
    public function colorize($red, $green, $blue, $alpha = 0) {
        if (-255 > $red || $red > 255)
            throw new OutOfBoundsException("In " . __METHOD__ . " value of red out of bounds -255 <= $red <= 255");
        if (-255 > $green || $green > 255)
            throw new OutOfBoundsException("In " . __METHOD__ . " value of green out of bounds -255 <= $green <= 255");
        if (-255 > $blue || $blue > 255)
            throw new OutOfBoundsException("In " . __METHOD__ . " value of blue out of bounds -255 <= $blue <= 255");
        if (-127 > $alpha || $alpha > 127)
            throw new OutOfBoundsException("In " . __METHOD__ . " value of alpha out of bounds -127 <= $alpha <= 127");
        if (false === imagefilter($this->resource->gd, IMG_FILTER_COLORIZE, $red, $green, $blue, $alpha))
            throw new RuntimeException('Appling ' . __METHOD__ . ' failed');
        return $this;
    }

    /**
     * 
     * Applies a 9-cell convolution matrix|
     * [1.0   1.0   1.0]
     * [1.0 $weight 1.0]
     * [1.0   1.0   1.0]
     * The result is normalized by dividing by $weight + 8.0
     * 
     * @see imagefilter(),imageconvolution()
     * @param float $weight smothness level
     * @return Filter
     * @throws RuntimeException
     */
    public function smooth($weight) {
        if (false === imagefilter($this->resource->gd, IMG_FILTER_SMOOTH, $weight))
            throw new RuntimeException('Appling ' . __METHOD__ . ' failed');
        return $this;
    }

    /**
     * 
     * @see imagefilter()
     * @param int $blocksize block size in pixels
     * @param bool $advanced use advanced pixelation effect
     * @return Filter
     * @throws RuntimeException
     */
    public function pixelate($blocksize, $advanced = false) {
        if (false === imagefilter($this->resource->gd, IMG_FILTER_PIXELATE, $blocksize, $advanced))
            throw new RuntimeException('Appling ' . __METHOD__ . ' failed');
        return $this;
    }

    /**
     * @see imagefilter()
     * @return Filter
     * @throws RuntimeException
     */
    public function negate() {
        if (false === imagefilter($this->resource->gd, IMG_FILTER_NEGATE))
            throw new RuntimeException('Appling ' . __METHOD__ . ' failed');
        return $this;
    }

    /**
     * @see imagefilter()
     * @return Filter
     * @throws RuntimeException
     */
    public function grayScale() {
        if (false === imagefilter($this->resource->gd, IMG_FILTER_GRAYSCALE))
            throw new RuntimeException('Appling ' . __METHOD__ . ' failed');
        return $this;
    }

    /**
     * @see imagefilter(),imageconvolution()
     * @return Filter
     * @throws RuntimeException
     */
    public function emboss() {
        if (false === imagefilter($this->resource->gd, IMG_FILTER_EMBOSS))
            throw new RuntimeException('Appling ' . __METHOD__ . ' failed');
        return $this;
    }

    /**
     * @see imagefilter(),imageconvolution()
     * @return Filter
     * @throws RuntimeException
     */
    public function meanRemoval() {
        if (false === imagefilter($this->resource->gd, IMG_FILTER_MEAN_REMOVAL))
            throw new RuntimeException('Appling ' . __METHOD__ . ' failed');
        return $this;
    }

    /**
     * @see imagefilter(),imageconvolution()
     * @return Filter
     * @throws RuntimeException
     */
    public function edgeDetect() {
        if (false === imagefilter($this->resource->gd, IMG_FILTER_EDGEDETECT))
            throw new RuntimeException('Appling ' . __METHOD__ . ' failed');
        return $this;
    }

    /**
     * @see imagefilter(),imageconvolution()
     * @return Filter
     * @throws RuntimeException
     */
    public function selectiveBlur() {
        if (false === imagefilter($this->resource->gd, IMG_FILTER_SELECTIVE_BLUR))
            throw new RuntimeException('Appling ' . __METHOD__ . ' failed');
        return $this;
    }

    /**
     * @see imagefilter(),imageconvolution()
     * @return Filter
     * @throws RuntimeException
     */
    public function gaussianBlur() {
        if (false === imagefilter($this->resource->gd, IMG_FILTER_GAUSSIAN_BLUR))
            throw new RuntimeException('Appling ' . __METHOD__ . ' failed');
        return $this;
    }

}
