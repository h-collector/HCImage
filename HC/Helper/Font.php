<?php

namespace HC\Helper;

use HC\GDResource;
use HC\Color;
use InvalidArgumentException;
use RuntimeException;

/**
 * Description of Font
 *
 * Helper class, text writing using font file
 * 
 * @todo fix bounds calculations, buggy
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
class Font {

    private /** @var GDResource */ $resource = null,
            /** @var string     */ $fontFile = '',
            /** @var Color      */ $color    = null,
            /** @var int        */ $size     = 10;

    /**
     * 
     * @param resource|GDResource|null $resource
     * @throws InvalidArgumentException
     */
    public function __construct($resource, $fontFile, $color = 0x000000, $size = 10) {
        $this->setResource($resource);

        $this->resource = $resource;
        $this->fontFile = $fontFile;
        $this->color    = Color::get($color);
        $this->size     = intval($size);
    }

    /**
     * Draw text using specified font
     * Note: Sets imagealphablending to true (antialiasing)
     * 
     * @param string $string
     * @param int $x
     * @param int $y
     * @param float $angle
     * @param GDResource|resource $resource
     * @return array <pre>
     *  [x4, y4], [x3, y3],
     *  [x1, y1], [x2, y2]
     * </pre>
     * @throws RuntimeException
     */
    public function text($string, $x = 0, $y = 0, $angle = 0, $resource = null) {
        if (is_object($angle)) {
            $bBox  = $angle;
            $angle = $angle->angle;
        } else {
            $bBox = $this->box($string, $angle);
        }
        //$x -= $bBox->left;
        $y += $bBox->height;
        
        if ($resource === null) {
            $resource = $this->resource->gd;
        } elseif ($resource instanceof GDResource) {
            $resource = $resource->gd;
        } elseif (!self::check($resource))
            throw new InvalidArgumentException("Invalid image handle: " . gettype($resource));
        
        imagealphablending($resource, true);

        if (false === ($box = imagettftext($resource, $this->size
                , $angle, $x, $y, $this->color->toInt(), $this->fontFile, $string)))
            throw new RuntimeException('Calling ' . __METHOD__ . ' failed');
        return $box;
    }

    /**
     * Give the bounding box of a text using TrueType fonts.
     * Relative to angle.
     * 
     * @param string $string
     * @param float $angle
     * @return array <pre>
     *  [x4, y4], [x3, y3],
     *  [x1, y1], [x2, y2]
     * </pre>
     * @throws RuntimeException
     */
    public function nativeBox($string, $angle = 0) {
        if (false === ($box = imagettfbbox($this->size, $angle, $this->fontFile, $string)))
            throw new RuntimeException('Calling ' . __METHOD__ . ' failed');
        return $box;
    }

    /**
     * Give the bounding box of a text using TrueType fonts
     * Absolute regardless of angle.
     * 
     * @param string $string
     * @param float $angle
     * @return object <pre>
     *   upLeft{x,y},  upRight{x,y},
     *  lowLeft{x,y}, lowRight{x,y},
     *  left, top, width, height, angle
     * </pre>
     * @throws RuntimeException
     */
    public function box($string, $angle = 0) {
        $bBox = $native = $this->nativeBox($string, 0);

        if ($angle !== 0) {// Rotate the boundingbox
            $rad = pi() * 2 - $angle * pi() * 2 / 360;
            for ($i = 0; $i < 4; ++$i) {
                $x                = $bBox[$i * 2];
                $y                = $bBox[$i * 2 + 1];
                $bBox[$i * 2]     = cos($rad) * $x - sin($rad) * $y;
                $bBox[$i * 2 + 1] = sin($rad) * $x + cos($rad) * $y;
            }
        }
        // * 72/96 = 3/4
        return (object) array(
                    'native'   => $native,
                    'lowLeft'  => (object) array('x' => $bBox[0], 'y' => $bBox[1]),
                    'lowRight' => (object) array('x' => $bBox[2], 'y' => $bBox[3]),
                    'upRight'  => (object) array('x' => $bBox[4], 'y' => $bBox[5]),
                    'upLeft'   => (object) array('x' => $bBox[6], 'y' => $bBox[7]),
                    'left'     => min($bBox[0], $bBox[2], $bBox[4], $bBox[6]),
                    'top'      => min($bBox[1], $bBox[3], $bBox[5], $bBox[7]),
                    'width'    => abs($bBox[0] + $bBox[4]),
                    'height'   => abs($bBox[1] - $bBox[5]),
                    'angle'    => $angle
        );
    }
    
    public function getResource() {
        return $this->resource;
    }

    public function setResource($resource) {
        if (!($resource instanceof GDResource))
            $resource = new GDResource($resource);

        $this->resource = $resource;
        return $this;
    }

    public function getFontFile() {
        return $this->fontFile;
    }

    public function setFontFile($fontFile) {
        $this->fontFile = $fontFile;
        return $this;
    }

    public function getColor() {
        return $this->color;
    }

    public function setColor(Color $color) {
        $this->color = $color;
        return $this;
    }

    public function getSize() {
        return $this->size;
    }

    public function setSize($size) {
        $this->size = intval($size);
        return $this;
    }

}