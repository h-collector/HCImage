<?php

namespace HC\Helper;

use InvalidArgumentException;
use RuntimeException;

/**
 * Description of Convolution
 *
 * Helper class, wrapper around imageconvolution
 *
 * @package HC\Helper
 * @author  h-collector <githcoll@gmail.com>
 *          
 * @link    http://hcoll.onuse.pl/projects/view/HCImage
 * @license GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 */
class Convolution {

    private $handle = null;

    /**
     * Note: Sets imagealphablending to false
     * 
     * @param resource $handle
     * @throws InvalidArgumentException
     */
    public function __construct($handle) {
        if (!is_resource($handle) || get_resource_type($handle) !== 'gd')
            throw new InvalidArgumentException("Invalid image handle");
        $this->handle = $handle;
        imagealphablending($handle, false);
    }

    /**
     * Use convolution matrix on Image
     * 
     * @see imageconvolution
     * @param array   $matrix float[3][3]
     * @param integer $offset color offset
     * @return bool
     */
    public function convolution(array $matrix, $offset = 0) {
        $divisor = array_sum(array_map('array_sum', $matrix));
        if (false === imageconvolution($this->handle, $matrix, $divisor, $offset))
            throw new RuntimeException('Appling ' . print_r($matrix, true) . ' as convolution matrix failed');
        return $this;
    }

    /**
     * 
     * @see imageconvolution,imagefilter,IMG_FILTER_MEAN_REMOVAL
     * @return Convolution
     * @throws RuntimeException
     */
    public function meanRemoval() {
        return $this->convolution(array(
                    array(-1, -1, -1),
                    array(-1, 9, -1),
                    array(-1, -1, -1)
        ));
    }

    /**
     * 
     * @see imageconvolution
     * @return Convolution
     * @throws RuntimeException
     */
    public function sharpen() {
        return $this->convolution(array(
                    array(0, -2, 0),
                    array(-2, 11, -2),
                    array(0, -2, 0)
        ));
    }

    /**
     * 
     * @see imageconvolution
     * @return Convolution
     * @throws RuntimeException
     */
    public function sharpenNice() {
        return $this->convolution(array(
                    array(-1.2, -1, -1.2),
                    array(-1.0, 20, -1.0),
                    array(-1.2, -1, -1.2)
        ));
    }

    /**
     * 
     * @see imageconvolution,imagefilter,IMG_FILTER_SMOOTH
     * @return Convolution
     * @throws RuntimeException
     */
    public function unsharpen() {
        return $this->convolution(array(
                    array(-1, -1, -1),
                    array(-1, 17, -1),
                    array(-1, -1, -1)
        ));
    }

    /**
     * 
     * @see imageconvolution
     * @return Convolution
     * @throws RuntimeException
     */
    public function dilate() {
        return $this->convolution(array(
                    array(0, 1, 0),
                    array(1, 1, 1),
                    array(0, 1, 0)
        ));
    }

    /**
     * @see imageconvolution,imagefilter,IMG_FILTER_GAUSSIAN_BLUR,IMG_FILTER_SELECTIIVE_BLUR
     * @return Convolution
     * @throws RuntimeException
     */
    public function blur() {
        return $this->convolution(array(
                    array(1, 2, 1),
                    array(2, 4, 2),
                    array(1, 2, 1)
        ));
    }

    /**
     * 
     * @see imageconvolution,imagefilter,IMG_FILTER_EMBOSS
     * @return Convolution
     * @throws RuntimeException
     */
    public function emboss() {
        return $this->convolution(array(
                    array(2, 0, 0),
                    array(0, -1, 0),
                    array(0, 0, -1)
        ));
    }

    /**
     * 
     * @see imageconvolution,imagefilter,IMG_FILTER_EMBOSS
     * @return Convolution
     * @throws RuntimeException
     */
    public function embossSubtle() {
        return $this->convolution(array(
                    array(1, 1, -1),
                    array(1, 3, -1),
                    array(1, -1, -1)
        ));
    }

    /**
     * 
     * @see imageconvolution,imagefilter,IMG_FILTER_EDGEDETECT
     * @return Convolution
     * @throws RuntimeException
     */
    public function edgeDetect($offset = 127) {
        return $this->convolution(array(
                    array(1, 1, 1),
                    array(1, -7, 1),
                    array(1, 1, 1)
                        ), $offset);
    }

    /**
     * 
     * @see imageconvolution,imagefilter,IMG_FILTER_EDGEDETECT
     * @return Convolution
     * @throws RuntimeException
     */
    public function edgeDetect2($offset = 0) {
        return $this->convolution(array(
                    array(-5, 0, 0),
                    array(0, 0, 0),
                    array(0, 0, 5)
                        ), $offset);
    }

}

