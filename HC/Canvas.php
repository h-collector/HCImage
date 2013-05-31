<?php

namespace HC;

use HC\Helper\Filter;
use HC\Helper\PixelOps;
use HC\Helper\Convolution;

use Countable;
use ArrayAccess;
use SeekableIterator;
use ErrorException;
use InvalidArgumentException;
use BadFunctionCallException;
use OutOfBoundsException;
use ReflectionFunction;

/**
 * Image canvas
 * 
 * @package HC
 * @author  h-collector <githcoll@gmail.com>
 *          
 * @license GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 *          
 * @link    http://hcoll.onuse.pl/projects/view/HCImage
 * @method  bool colorAt(int $x, int $y)
 * @method  bool ellipse(int $cx, int $cy, int $width, int $height, int $color)
 * @method  bool filledArc(int $cx, int $cy, int $width, int $height, int $start, int $end, int $color, int $style)
 * @method  bool filledEllipse(int $cx, int $cy, int $width, int $height, int $color)
 * @method  bool filledPolygon(int[] $points, int $num_points, int $color)
 * @method  bool filledRectangle(int $x1, int $y1, int $x2, int $y2, int $color)
 * @method  bool filter(int $filtertype, int $arg1, int $arg2, int $arg3, int $arg4) apply gd filter to image
 * @method  bool arc(int $cx, int $cy, int $width, int $height, int $start, int $end, int $color)
 * @method  bool line(int $x1, int $y1, int $x2, int $y2, int $color) 
 * @method  bool polygon(int[] $points, int $num_points, int $color)
 * @method  bool rectangle(int $x1, int $y1, int $x2, int $y2, int $color)
 * @method  bool setPixel(int $x, int $y, int $color)
 * @method  bool string(int $font, int $x, int $y, string $string, int $color)
 * @method  bool stringUp(int $font, int $x, int $y, string $string, int $color)
 * @method  bool layerEffect(int $effect)
 * @method  bool trueColorToPalette(bool $dither, int $ncolors)
 * @method  bool isTrueColor()
 * @method  int  colorsTotal()
 * @method  int  colorAllocate(int $red, int $green, int $blue)
 * @method  int  colorAllocateAlpha(int $red, int $green, int $blue, int $alpha)
 * @method  array colorsForIndex(int $index)
 * @method  mixed .*(mixed $params,..) mixed image*($this->handle, mixed $params,..)
 */
class Canvas implements ArrayAccess, SeekableIterator, Countable {// \SplObserver

    private /* @var int  */ $handle    = null,
            /* @var bool */ $ownHandle = false,
            /* @var int  */ $width     = 0,
            /* @var int  */ $height    = 0,
            /* @var int  */ $offset    = 0,
            /* @var bool */ $blendmode = true,
            /* @var int  */ $clear     = 0x7f000000;

    /**
     * Break pixel operation if returned from callback 
     * @var string 
     */

    const BREAK_OP = 'break';

    /**
     * @param resource $handle gd resource handle
     * @param bool $ownHandle if should destroy handle on destruct
     */
    public function __construct($handle, $ownHandle = true) {
        $this->updateHandle($handle, $ownHandle);
    }

    public function __destruct() {
        if ($this->ownHandle)
            imagedestroy($this->handle);
    }

    /**
     * Call native functions with internal handle
     * 
     * @param string $method method name
     * @param array  $p      arguments
     * @return mixed return function result
     * @throws BadFunctionCallException|ErrorException
     */
    public function __call($method, $p) {
        if (!is_resource($this->handle))
            throw new ErrorException("Invalid image handle.");

        $func = 'image' . $method;
        if (function_exists($func)) {
            switch (count($p)) {
                case 0: return $func($this->handle);
                case 1: return $func($this->handle, $p[0]);
                case 2: return $func($this->handle, $p[0], $p[1]);
                case 3: return $func($this->handle, $p[0], $p[1], $p[2]);
                case 4: return $func($this->handle, $p[0], $p[1], $p[2], $p[3]);
                case 5: return $func($this->handle, $p[0], $p[1], $p[2], $p[3], $p[4]);
                case 6: return $func($this->handle, $p[0], $p[1], $p[2], $p[3], $p[4], $p[5]);
                case 7: return $func($this->handle, $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6]);
                default: $return = call_user_func_array($func, array_merge((array) $this->handle, $p));
                    $this->updateHandle($this->handle, $this->ownHandle); //eg imagecopyresized or resampled
                    return $return;
            }
        }
        throw new BadFunctionCallException("Function doesn't exist: {$func}.");
    }

    /**
     * Update internals
     * Should not be used if owned by Image instance
     * 
     * @param resource $handle gd resource handle
     * @param bool $ownHandle if should destroy handle on destruct
     * @return Canvas
     */
    public function updateHandle($handle, $ownHandle = true) {
        if (!is_resource($handle) || get_resource_type($handle) !== 'gd')
            throw new InvalidArgumentException("Invalid image handle");

        //imagecolortransparent($handle, $this->clear);
        if (($transparent = imagecolortransparent($handle)) !== -1)
            $this->clear = $transparent;

        $this->handle    = $handle;
        $this->ownHandle = $ownHandle;
        $this->width     = imagesx($handle);
        $this->height    = imagesy($handle);
        return $this;
    }

    /**
     * Return shareable handle
     * 
     * @return resource
     * @throws ErrorException
     */
    public function getHandle() {
        if ($this->ownHandle)
            trigger_error('This image handle is owned');
        return $this->handle;
    }

    /**
     * Destroy owned image handle
     * 
     * @return bool
     * @throws ErrorException
     */
    public function destroy() {
        if (!$this->ownHandle)
            throw new ErrorException('Cannot destroy not owned image');
        return imagedestroy($this->handle);
    }

    /**
     * Set blend mode
     * 
     * @see alphablending
     * @param bool $blendmode enable or disable.
     *             On true color images the default value is true otherwise the default value is false
     * @return bool   
     */
    public function alphaBlending($blendmode) {
//        if($blendmode === false) 
//            return $this->blendmode === null ? $this->isTrueColor() : $this->blendmode;
        $this->blendmode = $blendmode;
        return imagealphablending($this->handle, $blendmode);
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
        return imageconvolution($this->handle, $matrix, $divisor, $offset);
    }

    /**
     * Allocate color for paletted images, for truecolor use 0xAARRGGBB hex instead
     * 
     * @see imagecolorexactalpha,imagecolorclosestalpha,imagecolorallocatealpha
     * @param Color $color color to allocate
     * @param bool  $alpha allocate also alpha value
     * @return int                      
     * @throws InvalidArgumentException
     */
    public function allocateColor(Color $color, $alpha = true) {
        $handle = $this->handle;
        $r      = $color->red;
        $g      = $color->green;
        $b      = $color->blue;
        if ($alpha) {
            $a     = $color->alpha;
            $index = imagecolorexactalpha($handle, $r, $g, $b, $a);
            if ($index === -1) {
                if (imagecolorstotal($handle) >= 255) {
                    $index = imagecolorclosestalpha($handle, $r, $g, $b, $a);
                } else {
                    $index = imagecolorallocatealpha($handle, $r, $g, $b, $a);
                }
            }
        } else {
            $index = imagecolorexact($handle, $r, $g, $b);
            if ($index === -1) {
                if (imagecolorstotal($handle) >= 255) {
                    $index = imagecolorclosest($handle, $r, $g, $b);
                } else {
                    $index = imagecolorallocate($handle, $r, $g, $b);
                }
            }
        }
        return $index;
    }

    /**
     * Per pixel operations on image canvas, before use set appropriate alpha blending
     * 
     * Optimized for speed method to do get and set operations on separate pixels using callback
     * 
     * @param callback $operation  Color|int function([Color|int $rgb[, int $x[, int $y[, $handle]]]])
     * @param array    $forOpts    options for traversing canvas as matrix of pixels
     *                             beginX=beginY=0, dX=dY=1, endX=width, endY=height
     * @param int      $returnMode declare what callback will return 0-int, 1-Color, 2-Color|int, 3-int|Color
     * @param int      $mode       declare what callback want for argument as 0-Color, 1-index, 2-0, 
     *                             by default automatic (typehint Color->1, $rgb = $unused -> 2)
     * @param bool     $cache      try to cache output of callback to speed up calculating pixels color
     * @throws InvalidArgumentException
     * @return Canvas
     */
    public function pixelOperation($operation, array $forOpts = array(), $returnMode = 3, $mode = 0, $cache = false) {
        if (!is_callable($operation))
            throw new InvalidArgumentException('Operation must be a valid callable or Closure');

        $handle = $this->handle;
        $beginX = $beginY = 0;
        $dX     = $dY     = 1;
        $endX   = $this->width;
        $endY   = $this->height;
        $opts   = array('beginX', 'beginY', 'dX', 'dY', 'endX', 'endY');
        extract(array_intersect_key(array_map('intval', $forOpts), array_flip($opts)));

        if (max($beginX, $endX) > $this->width 
                || max($beginY, $endY) > $this->height 
                || min($beginX, $endX, $beginY, $endY) < 0)
            throw new InvalidArgumentException('One or more for loop options values out of bounds');

        $reflection  = new ReflectionFunction($operation);
        $paramsCount = $reflection->getNumberOfParameters();
        if ($mode === 0) {//speed up a little if default, aka runtime optimalization
            if ($paramsCount >= 1) {//what callback expect as first param
                $refParams = $reflection->getParameters();
                if ($refParams[0]->getName() === 'unused')//param will not be used
                    $mode      = 2; //expect nothing - can pass 0
                elseif ($refParams[0]->getClass() === null)//param will not be a Color Object
                    $mode      = 1; //expect int
            }
            else
                $mode = 2; //expect nothing - can pass 0
        }//else mode 0 slowest - expect Color

        if ($cache) {//can callback output really be cached?
            ob_start();
            var_dump($reflection->getStaticVariables());
            if (preg_match('/]=>\n\s+(&|object)/', ob_get_clean()) || $paramsCount > 1)
                $cache = false;
            else $cached = array();
        }

        if ($returnMode === 3 && $mode === 0)
            $returnMode = 2;

                                 $params  = '';
        if (1 <= $paramsCount)
            if ($mode === 2)     $params .= '0';
            elseif ($mode === 1) $params .= '$rgb';
            else                 $params .= 'Color::fromInt($rgb)';
        if (2 <= $paramsCount)   $params .= ',$x';
        if (3 <= $paramsCount)   $params .= ',$y';
        if (4 <= $paramsCount)   $params .= ',$handle';
        if (5 <= $paramsCount)   $params .= str_repeat(',0', $paramsCount - 4);
        
        $stepX = $dX === 1 ? '++$x' : '$x+=$dX';
        $stepY = $dY === 1 ? '++$y' : '$y+=$dY';

        $call = 'if (($pixel = $operation(' . $params . ')) === \'' . self::BREAK_OP . '\') break 2;';
        $loop = 'namespace ' . __NAMESPACE__ . ';';
        $loop .= 'for ($y = $beginY; $y < $endY; ' . $stepY . ') { for ($x = $beginX; $x < $endX; ' . $stepX . ') {';
        switch ($mode) {
            case 2: $loop.= $call;
                break;
            case 1:         $loop.= '$rgb = imagecolorat($handle, $x, $y);';
                if ($cache) $loop.='if(isset($cached[$rgb])) $pixel = $cached[$rgb]; else {';
                            $loop.= $call;
                if ($cache) $loop.='$cached[$rgb]=$pixel;}';
                break;
            default:        $loop.= '$rgb = imagecolorat($handle, $x, $y);';
                if ($cache) $loop.='if(isset($cached[$rgb])) $pixel = $cached[$rgb]; else {';
                            $loop.= $call;
                if ($cache) $loop.='$cached[$rgb]=$pixel;}';
        }
        switch ($returnMode) {//return
            case 0:
                $loop.= 'if ($pixel !== null) imagesetpixel($handle, $x, $y, $pixel);';
                break;
            case 1:
                $loop.= 'if ($pixel !== null) imagesetpixel($handle, $x, $y, $pixel->toInt());';
                break;
            case 2:
                $loop.= '    if ($pixel instanceof Color) imagesetpixel($handle, $x, $y, $pixel->toInt());';
                $loop.= 'elseif (is_int($pixel))  imagesetpixel($handle, $x, $y, $pixel);';
                break;
            default:
                $loop.= '    if (is_int($pixel))  imagesetpixel($handle, $x, $y, $pixel);';
                $loop.= 'elseif ($pixel instanceof Color) imagesetpixel($handle, $x, $y, $pixel->toInt());';
        }
        eval($loop . '}}'); //eval because of speed and code space
        return $this;
    }

    /**
     * Calculate average image luminance
     * 
     * @return number avg luminance
     */
    function getAverageLuminance() {
        $width        = $this->width;
        $height       = $this->height;
        $handle       = $this->handle;
        $luminanceSum = 0;
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $rgb = imagecolorat($handle, $x, $y);
                //$rgb = imagecolorsforindex($handle, $rgb);
                $r   = ($rgb >> 16) & 0xFF;
                $g   = ($rgb >> 8) & 0xFF;
                $b   = ($rgb) & 0xFF;
                $luminanceSum += (0.30 * $r) + (0.59 * $g) + (0.11 * $b);
            }
        }
        return $luminanceSum / ($width * $height);
    }
    
    /**
     * Use helper filter class on canvas
     * Note: Sets imagealphablending to false
     * 
     * @see imagefilter,imageconvolution,Canvas::pixelOperation
     * @return Filter
     */
    public function useFilter() {
        return new Filter($this->handle);
    }
    
    /**
     * Use helper Convolution class on canvas
     * Note: Sets imagealphablending to false
     * 
     * @see imagefilter,imageconvolution,Canvas::pixelOperation
     * @return Convolution
     */
    public function useConvolution() {
        return new Convolution($this->handle);
    }
    
    
    /**
     * Apply predefined Canvas::pixelOperation (one time)
     * Note: Sets imagealphablending to false
     * 
     * @see Canvas::pixelOperation
     * @param int  $beginX
     * @param int  $beginY
     * @param int  $endX   canvas full height if null
     * @param int  $endY   canvas full height if null
     * @param int  $stepX
     * @param int  $stepY
     * @param bool $cache
     * @return PixelOps
     */
    public function usePixelOps(
            $beginX = 0, $beginY = 0, $endX = null, $endY = null, $stepX = 1, $stepY = 1, 
            $cache = false) {
        return PixelOps::on($this, $beginX, $beginY, $endX, $endY, $stepX, $stepY, $cache);
    }

    /**
     * Return canvas width
     * 
     * @see imagesx
     * @return int
     */
    public function sX() {
        return $this->width;
    }

    /**
     * Return canvas height
     * 
     * @see imagesy
     * @return int
     */
    public function sY() {
        return $this->height;
    }

    /**
     * @param int $offset
     * @return bool
     */
    public function offsetExists($offset) {
        return (($offset >= 0) && ($offset < $this->width * $this->height));
    }

    /**
     * @param int  $offset
     * @return int
     */
    public function offsetGet($offset) {
        $x = (int) ($offset % $this->width);
        $y = (int) ($offset / $this->width);
        return imagecolorat($this->handle, $x, $y);
    }

    /**
     * @param int   $offset
     * @param mixed $color
     * @return void   
     */
    public function offsetSet($offset, $color) {
        $x = (int) ($offset % $this->width);
        $y = (int) ($offset / $this->width);
        imagesetpixel($this->handle, $x, $y, is_int($color) ? $color : Color::index($color));
    }

    /**
     * @param int $offset  
     */
    public function offsetUnset($offset) {
        $x = (int) ($offset % $this->width);
        $y = (int) ($offset / $this->width);
        imagealphablending($this->handle, false);
        imagesetpixel($this->handle, $x, $y, $this->clear);
        imagealphablending($this->handle, $this->blendmode);
    }

    /**
     * @param mixed $color
     */
    public function currentSet($color) {
        $this->offsetSet($this->offset, $color);
    }

    /**
     * @return int
     */
    public function current() {
        return $this->offsetGet($this->offset);
    }

    /**
     * @return int.
     */
    public function key() {
        return $this->offset;
    }

    public function next() {
        ++$this->offset;
    }

    public function rewind() {
        $this->offset = 0;
    }

    /**
     * @return bool
     */
    public function valid() {
        return $this->offsetExists($this->offset);
    }

    /**
     * @param int $offset
     */
    public function seek($offset) {
        $this->offset = $offset;
        if (!$this->valid())
            throw new OutOfBoundsException("Invalid seek position ($offset)");
    }

    /**
     * @return int Return total number of pixels in image canvas
     */
    public function count() {
        return $this->width * $this->height;
    }

}
