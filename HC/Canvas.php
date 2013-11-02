<?php

namespace HC;

use HC\GDResource;
use HC\Helper\Font;
use HC\Helper\Filter;
use HC\Helper\PixelOps;
use HC\Helper\Convolution;

use Closure;
use Countable;
use ArrayAccess;
use RuntimeException;
use InvalidArgumentException;
use BadFunctionCallException;
use ReflectionFunction;

/**
 * Image canvas
 * 
 * @package HC
 * 
 * @author  h-collector <githcoll@gmail.com>
 * @link    http://hcoll.onuse.pl/projects/view/HCImage       
 * @license GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 * 
 * @uses    GDResource
 * @method  bool gif([string $filename = null])
 * @method  bool jpeg([string $filename = null[, int $quality = null]])
 * @method  bool png([string $filename = null[, int $quality = null[, int $filters = null]]])
 * @method  bool colorAt(int $x, int $y)
 * @method  bool setPixel(int $x, int $y, int $color)
 * @method  bool fill(int $x, int $y, int $color)
 * @method  bool fillToBorder(int $x, int $y, int $border, int $color)
 * @method  bool filledArc(int $cx, int $cy, int $width, int $height, int $start, int $end, int $color, int $style)
 * @method  bool filledEllipse(int $cx, int $cy, int $width, int $height, int $color)
 * @method  bool filledPolygon(int[] $points, int $num_points, int $color)
 * @method  bool filledRectangle(int $x1, int $y1, int $x2, int $y2, int $color)
 * @method  bool arc(int $cx, int $cy, int $width, int $height, int $start, int $end, int $color)
 * @method  bool ellipse(int $cx, int $cy, int $width, int $height, int $color)
 * @method  bool polygon(int[] $points, int $num_points, int $color)
 * @method  bool rectangle(int $x1, int $y1, int $x2, int $y2, int $color)
 * @method  bool line(int $x1, int $y1, int $x2, int $y2, int $color)
 * @method  bool dashedLine(int $x1, int $y1, int $x2, int $y2, int $color)
 * @method  bool antiAlias(bool $enabled)
 * @method  bool string(int $font, int $x, int $y, string $string, int $color)
 * @method  bool stringUp(int $font, int $x, int $y, string $string, int $color)
 * @method  bool filter(int $filtertype, int $arg1, int $arg2, int $arg3, int $arg4) apply gd filter to image
 * @method  bool layerEffect(int $effect)
 * @method  bool trueColorToPalette(bool $dither, int $ncolors)
 * @method  bool isTrueColor()
 * @method  int  colorsTotal()
 * @method  int  colorAllocate(int $red, int $green, int $blue)
 * @method  int  colorAllocateAlpha(int $red, int $green, int $blue, int $alpha)
 * @method  array colorsForIndex(int $index)
 * @method  mixed .*(mixed $params,..) mixed image*($this->resource->gd, mixed $params,..)
 */
class Canvas implements ArrayAccess, Countable {

    private /** @var GDResource */ $resource  = null,
            /** @var int        */ $width     = 0,
            /** @var int        */ $height    = 0,
            /** @var bool       */ $blendmode = true,
            /** @var int        */ $clear     = 0x7f000000;

    /**
     * Break pixel operation if returned from callback 
     * @var string 
     */

    const BREAK_OP = 'break';

    /**
     * @param resource|GDResource $resource gd image handle or GDResource resource
     */
    public function __construct($resource = null) {
        if (!($resource instanceof GDResource))
            $resource = new GDResource($resource);
        
        $this->resource = $resource;
        $this->update();
    }

    public function __destruct() {
        unset($this->resource);
    }

    /**
     * Call native functions with internal handle
     * 
     * @param string $method method name
     * @param array  $p      arguments
     * @return mixed return function result
     * @throws BadFunctionCallException|RuntimeException
     */
    public function __call($method, $p) {
        if (!$this->resource->isValid())
            throw new RuntimeException("Invalid image handle.");

        $func = 'image' . $method;
        if (function_exists($func)) {
            $gd = $this->resource->gd;
            switch (count($p)) {
                case 0: return $func($gd);
                case 1: return $func($gd, $p[0]);
                case 2: return $func($gd, $p[0], $p[1]);
                case 3: return $func($gd, $p[0], $p[1], $p[2]);
                case 4: return $func($gd, $p[0], $p[1], $p[2], $p[3]);
                case 5: return $func($gd, $p[0], $p[1], $p[2], $p[3], $p[4]);
                case 6: return $func($gd, $p[0], $p[1], $p[2], $p[3], $p[4], $p[5]);
                case 7: return $func($gd, $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6]);
                default: $return = call_user_func_array($func, array_merge((array) $gd, $p));
                    $this->update(); //eg imagecopyresized or resampled
                    return $return;
            }
        }
        throw new BadFunctionCallException("Function doesn't exist: {$func}.");
    }

    /**
     * Update internals
     * @return Canvas
     */
    public function update() {
        $resource = $this->resource;
        if (!$resource->isValid()) 
            return $this;
        if (($transparent = imagecolortransparent($resource->gd)) !== -1)
            $this->clear = $transparent;
        $this->width  = imagesx($resource->gd);
        $this->height = imagesy($resource->gd);
        return $this;
    }

   /**
     * Implicitly create gd resource
     * In most case you should create new Canvas/Image
     * 
     * @return bool
     */
    public function create($width, $height) {
        return $this->resource->replace(imagecreate($width, $height)) && $this->update();
    }
    
    public function createTrueColor($width, $height) {
        return $this->resource->replace(imagecreatetruecolor($width, $height)) && $this->update();
    }

    public function createFromGif($filename) {
        return $this->resource->replace(imagecreatefromgif($filename)) && $this->update();
    }

    public function createFromJpeg($filename) {
        return $this->resource->replace(imagecreatefromjpeg($filename)) && $this->update();
    }

    public function createFromPng($filename) {
        return $this->resource->replace(imagecreatefrompng($filename)) && $this->update();
    }
    
    public function createFromGD($filename) {
        return $this->resource->replace(imagecreatefromgd($filename)) && $this->update();
    }
    
    public function createFromGD2($filename) {
        return $this->resource->replace(imagecreatefromgd2($filename)) && $this->update();
    }
    
    public function createFromGD2Part($filename, $srcX, $srcY, $width, $height) {
        return $this->resource->replace(imagecreatefromgd2part($filename, $srcX, $srcY, $width, $height)) && $this->update();
    }
    
    public function createFromString($image) {
        return $this->resource->replace(imagecreatefromstring($image)) && $this->update();
    }
    
    /**
     * Implicitly destroy gd resource
     * In most case you should destroy 
     * parent Canvas/Image/Helper insteed
     * 
     * @return bool
     */
    public function destroy() {
        return $this->resource->destroy();
    }

    /**
     * 
     * @return GDResource
     */
    public function getGDResource() {
        return $this->resource;
    }
    
    /**
     * 
     * @return resource
     */
    public function getHandle() {
        return $this->resource->gd;
    }
    
     /**
     * Return canvas width
     * 
     * @see imagesx()
     * @return int
     */
    public function sX() {
        return $this->width;
    }

    /**
     * Return canvas height
     * 
     * @see imagesy()
     * @return int
     */
    public function sY() {
        return $this->height;
    }
    
    /**
     * @param string $file
     */
    public static function loadFont($file) {
        return imageloadfont($file);
    }
    
    /**
     * @param int $font
     */
    public static function fontWidth($font) {
        return imagefontwidth($font);
    }
    
    /**
     * @param int $font
     */
    public static function fontHeight($font) {
        return imagefontheight($font);
    }

    /**
     * Set blend mode
     * 
     * @todo get initial flag state
     * @see alphablending()
     * @param bool $blendmode enable or disable.
     *             On true color images the default value is true otherwise the default value is false
     * @return bool   
     * @throws RuntimeException
     */
    public function alphaBlending($blendmode) {
        if (!$this->resource->isValid())
            throw new RuntimeException("Invalid image handle.");
//        if($blendmode === false) 
//            return $this->blendmode === null ? $this->isTrueColor() : $this->blendmode;
        $this->blendmode = $blendmode;
        return imagealphablending($this->resource->gd, $blendmode);
    }

    /**
     * Use convolution matrix on Image
     * 
     * @see imageconvolution()
     * @param array   $matrix float[3][3]
     * @param integer $offset color offset
     * @return bool
     * @throws RuntimeException
     */
    public function convolution(array $matrix, $offset = 0) {
        if (!$this->resource->isValid())
            throw new RuntimeException("Invalid image handle.");
        $divisor = array_sum(array_map('array_sum', $matrix));
        return imageconvolution($this->resource->gd, $matrix, $divisor, $offset);
    }

    /**
     * Allocate color for paletted images, for truecolor use 0xAARRGGBB hex instead
     * 
     * @see imagecolorexactalpha(),imagecolorclosestalpha(),imagecolorallocatealpha()
     * @param Color $color color to allocate
     * @param bool  $alpha allocate also alpha value
     * @return int                      
     * @throws RuntimeException
     */
    public function allocateColor(Color $color, $alpha = true) {
        if (!$this->resource->isValid())
            throw new RuntimeException("Invalid image handle.");
        $gd = $this->resource->gd;
        $r  = $color->red;
        $g  = $color->green;
        $b  = $color->blue;
        if ($alpha) {
            $a     = $color->alpha;
            $index = imagecolorexactalpha($gd, $r, $g, $b, $a);
            if ($index === -1) {
                if (imagecolorstotal($gd) >= 255) {
                    $index = imagecolorclosestalpha($gd, $r, $g, $b, $a);
                } else {
                    $index = imagecolorallocatealpha($gd, $r, $g, $b, $a);
                }
            }
        } else {
            $index = imagecolorexact($gd, $r, $g, $b);
            if ($index === -1) {
                if (imagecolorstotal($gd) >= 255) {
                    $index = imagecolorclosest($gd, $r, $g, $b);
                } else {
                    $index = imagecolorallocate($gd, $r, $g, $b);
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
     * @param callback $operation  Color|int function([Color|int $rgb[, int $x[, int $y[, $gd]]]])
     * @param array    $forOpts    options for traversing canvas as matrix of pixels
     *                             beginX=beginY=0, dX=dY=1, endX=width, endY=height
     * @param int      $returnMode declare what callback will return 0-int, 1-Color, 2-Color|int, 3-int|Color
     * @param int      $mode       declare what callback want for argument as 0-Color, 1-index, 2-0, 
     *                             by default automatic (typehint Color->1, $rgb = $unused -> 2)
     * @param bool     $cache      try to cache output of callback to speed up calculating pixels color
     * @throws InvalidArgumentException|RuntimeException
     * @return Canvas
     */
    public function pixelOperation($operation, array $forOpts = array(), $returnMode = 3, $mode = 0, $cache = false) {
        if (!is_callable($operation))
            throw new InvalidArgumentException('Operation must be a valid callable or Closure');
        if (!$this->resource->isValid())
            throw new RuntimeException("Invalid image handle.");
        
        $gd     = $this->resource->gd;
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
        if (4 <= $paramsCount)   $params .= ',$gd';
        if (5 <= $paramsCount)   $params .= str_repeat(',0', $paramsCount - 4);
        
        $stepX = $dX === 1 ? '++$x' : '$x+=$dX';
        $stepY = $dY === 1 ? '++$y' : '$y+=$dY';

        $call = 'if (($pixel = $operation(' . $params . ')) === \'' . self::BREAK_OP . '\') break 2;';
        $loop = 'namespace ' . __NAMESPACE__ . ';';
        $loop .= 'for ($y = $beginY; $y < $endY; ' . $stepY . ') { for ($x = $beginX; $x < $endX; ' . $stepX . ') {';
        switch ($mode) {
            case 2: $loop.= $call;
                break;
            case 1:         $loop.= '$rgb = imagecolorat($gd, $x, $y);';
                if ($cache) $loop.='if(isset($cached[$rgb])) $pixel = $cached[$rgb]; else {';
                            $loop.= $call;
                if ($cache) $loop.='$cached[$rgb]=$pixel;}';
                break;
            default:        $loop.= '$rgb = imagecolorat($gd, $x, $y);';
                if ($cache) $loop.='if(isset($cached[$rgb])) $pixel = $cached[$rgb]; else {';
                            $loop.= $call;
                if ($cache) $loop.='$cached[$rgb]=$pixel;}';
        }
        switch ($returnMode) {//return
            case 0:
                $loop.= 'if ($pixel !== null) imagesetpixel($gd, $x, $y, $pixel);';
                break;
            case 1:
                $loop.= 'if ($pixel !== null) imagesetpixel($gd, $x, $y, $pixel->toInt());';
                break;
            case 2:
                $loop.= '    if ($pixel instanceof Color) imagesetpixel($gd, $x, $y, $pixel->toInt());';
                $loop.= 'elseif (is_int($pixel))  imagesetpixel($gd, $x, $y, $pixel);';
                break;
            default:
                $loop.= '    if (is_int($pixel))  imagesetpixel($gd, $x, $y, $pixel);';
                $loop.= 'elseif ($pixel instanceof Color) imagesetpixel($gd, $x, $y, $pixel->toInt());';
        }
        eval($loop . '}}'); //eval because of speed and code space
        return $this;
    }
    
    /**
     * Get new helper Filter class on canvas resource
     * Note: Sets imagealphablending to false
     * 
     * @see imagefilter(),imageconvolution(),Canvas::pixelOperation()
     * @return Filter
     */
    public function getFilter() {
        return new Filter($this->resource);
    }
    
    /**
     * Use helper Filter class on canvas
     * 
     * @uses Canvas::getFilter
     * @param Closure $closure void function(Filter $filter){}
     * @return Canvas
     */
    public function useFilter(Closure $closure) {
        $closure($this->getFilter());
        return $this;
    }
    
    /**
     * Get new helper Convolution class on canvas resource
     * Note: Sets imagealphablending to false
     * 
     * @see imagefilter(),imageconvolution(),Canvas::pixelOperation()
     * @return Convolution
     */
    public function getConvolution() {
        return new Convolution($this->resource);
    }
    
    /**
     * Use helper Convolution class on canvas
     * 
     * @uses Canvas::getConvolution
     * @param Closure $closure void function(Convolution $covolution){}
     * @return Canvas
     */
    public function useConvolution(Closure $closure) {
        $closure($this->getConvolution());
        return $this;
    }
    
    /**
     * Get helper Font class on canvas resource
     * 
     * @param string|Font $fontFile
     * @param mixed $color
     * @param int $size
     * @return Font
     */
    public function getFont($fontFile, $color = 0x000000, $size = 10) {
        if ($fontFile instanceof Font)
            return $fontFile->setResource($this->resource);
        return new Font($this->resource, $fontFile, $color, $size);
    }
    
    /**
     * Use helper Font class on canvas
     * 
     * @uses Canvas::getFont
     * @param string|Font $fontFile
     * @param Closure $closure void function(Font $font){}
     * @param mixed $color
     * @param int $size
     * @return Canvas
     */
    public function useFont($fontFile, Closure $closure, $color = 0x000000, $size = 10) {
        $closure($this->getFont($fontFile, $color, $size));
        return $this;
    }
    
    /**
     * Apply predefined Canvas::pixelOperation (one time)
     * Note: by default sets imagealphablending to false
     * 
     * @see Canvas::pixelOperation()
     * @param int  $beginX
     * @param int  $beginY
     * @param int  $endX   canvas full height if null
     * @param int  $endY   canvas full height if null
     * @param int  $stepX
     * @param int  $stepY
     * @param bool $cache speed up color calculations but use more memory
     * @param bool $alphaBlending
     * @return PixelOps
     */
    public function usePixelOps(
            $beginX = 0, $beginY = 0, $endX = null, $endY = null, $stepX = 1, $stepY = 1, 
            $cache = false, $alphaBlending = false) {
        return PixelOps::on($this, $beginX, $beginY, $endX, $endY, $stepX, $stepY, $cache, $alphaBlending);
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
        return imagecolorat($this->resource->gd, $x, $y);
    }

    /**
     * @param int   $offset
     * @param mixed $color
     * @return void   
     */
    public function offsetSet($offset, $color) {
        $x = (int) ($offset % $this->width);
        $y = (int) ($offset / $this->width);
        imagesetpixel($this->resource->gd, $x, $y, is_int($color) ? $color : Color::index($color));
    }

    /**
     * @param int $offset  
     */
    public function offsetUnset($offset) {
        $x  = (int) ($offset % $this->width);
        $y  = (int) ($offset / $this->width);
        $gd = $this->resource->gd;
        imagealphablending($gd, false);
        imagesetpixel($gd, $x, $y, $this->clear);
        imagealphablending($gd, $this->blendmode);
    }

    /**
     * @return int Return total number of pixels in image canvas
     */
    public function count() {
        return $this->width * $this->height;
    }

}
