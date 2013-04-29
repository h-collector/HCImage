<?php

namespace HC;

/**
 * @author h-collector <githcoll@gmail.com>
 * 
 * @link          http://hcoll.onuse.pl/projects/view/HCImage
 * @package       HC
 * @license       GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 * 
 * @method mixed .*(mixed $params,..) mixed image*($this->handle, mixed $params,..)
 * @method int colorAllocateAlpha(int $red, int $green, int $blue, int $alpha)
 * @method bool line(int $x1, int $y1, int $x2, int $y2, int $color) 
 * @method bool rectangle(int $x1, int $y1, int $x2, int $y2, int $color)
 * @method bool ellipse(int $cx, int $cy, int $width, int $height, int $color)
 * @method bool polygon(int[] $points, int $num_points, int $color)
 * @method bool imagearc(int $cx, int $cy, int $width, int $height, int $start, int $end, int $color)
 * @method bool filledRectangle(int $x1, int $y1, int $x2, int $y2, int $color)
 * @method bool filledEllipse(int $cx, int $cy, int $width, int $height, int $color)
 * @method bool filledPolygon(int[] $points, int $num_points, int $color)
 * @method bool filledArc(int $cx, int $cy, int $width, int $height, int $start, int $end, int $color)
 * @method bool string(int $font, int $x, int $y, string $string, int $color)
 * @method bool stringUp(int $font, int $x, int $y, string $string, int $color)
 * @method bool filter(int $filtertype, int $arg1, int $arg2, int $arg3, int $arg4)
 * @method bool setPixel(int $x, int $y, int $color)
 * @method bool colorAt(int $x, int $y)
 * @method bool layerEffect(int $effect)
 */
class Canvas implements \ArrayAccess, \SeekableIterator, \Countable {// \SplObserver

    private $handle      = null,
            $width       = 0,
            $height      = 0,
            $offset      = 0,
            $blendmode   = true,
            $transparent = 0x7f000000; //transparent black

    const /** @var string */ BREAK_OP = 'break'; // break pixel operation if returned from callback

    public function __construct(Image $image) {
        $this->updateHandle($image);
    }

    public function __call($method, $p) {
        if (!Image::isValidImageHandle($this->handle))
            throw new \ErrorException("Invalid image handle.");

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
                default: array_unshift($p, $this->handle);
                    return call_user_func_array($func, $p);
            }
        }
        else
            throw new \BadFunctionCallException("Function doesn't exist: {$func}.");
    }

    public function updateHandle(Image $image) {
        if (($this->transparent = $image->getTransparentColor()) === -1)
            $this->transparent = $image->setTransparentColor();

        $this->handle = $image->getHandle();
        $this->width  = $image->getWidth();
        $this->height = $image->getHeight();
    }

    public function alphaBlending($blendmode) {
        $this->blendmode = $blendmode;
        imagealphablending($this->handle, $blendmode);
    }

    public function convolution(array $matrix, $offset = 0) {
        $divisor = array_sum(array_map('array_sum', $matrix));
        imageconvolution($this->handle, $matrix, $divisor, $offset);
    }

    /**
     * 
     * @param callback $operation Color|int function([Color|int $rgb[, int $x[, int $y]]])
     * @param array $forOpts beginX=beginY=0, dX=dY=1, endX=width, endY=height
     * @param int $returnMode declare what callback will return 0-int, 1-Color, def-auto
     * @param int $mode force return color for callback as 0-Color, 1-index, 2-0, 
     *        by default automatic (and $rgb = unused -> 2)
     * @throws \InvalidArgumentException
     */
    public function pixelOperation($operation, array $forOpts = array(), $returnMode = 3, $mode = 0) {
        if (!is_callable($operation))
            throw new \InvalidArgumentException('Operation must be a valid callable or Closure');

        $beginX = $beginY = 0;
        $dX     = $dY     = 1;
        $endX   = $this->width;
        $endY   = $this->height;
        $opts   = array('beginX', 'beginY', 'dX', 'dY', 'endX', 'endY');
        extract(array_intersect_key($forOpts, array_flip($opts)));

        if (max($beginX, $endX) > $this->width || max($beginY, $endY) > $this->height || min($beginX, $endX, $beginY, $endY) < 0)
            throw new \InvalidArgumentException('One or more for loop options values out of bounds');

        if ($mode === 0) {//speed up a little if default, aka runtime optimalization
            $ref       = new \ReflectionFunction($operation); //isClosure()
            $refParams = $ref->getParameters();
            if ($ref->getNumberOfParameters() >= 1) {//what callback expect as first param
                if ($refParams[0]->getName() === 'unused')//param will not be used
                    $mode = 2; //expect nothing - can pass 0
                elseif ($refParams[0]->getClass() === null)//param will not be a Color Object
                    $mode = 1; //expect int
            }
            else
                $mode = 2; //expect nothing - can pass 0
        }//else mode 0 slowest

        if ($returnMode === 3 && $mode === 0)
            $returnMode = 2;

        $break  = self::BREAK_OP;
        $handle = $this->handle;
        $loop   = 'namespace ' . __NAMESPACE__ . ';';
        $loop.= 'for ($y = $beginY; $y < $endY; $y+=$dY) { for ($x = $beginX; $x < $endX; $x+=$dX) {';
        switch ($mode) {//break operation
            case 2:
                $loop.= 'if (($pixel = $operation(0, $x, $y)) === $break) break 2;';
                break;
            case 1:
                $loop.= '$rgb = imagecolorat($handle, $x, $y);';
                $loop.= 'if (($pixel = $operation($rgb, $x, $y)) === $break) break 2;';
                break;
            default:
                $loop.= '$rgb = imagecolorat($handle, $x, $y);';
                $loop.= 'if (($pixel = $operation(Color::fromInt($rgb), $x, $y)) === $break) break 2;';
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
//        foreach ($this as $idx => $rgb) {
//            $pixel = $operation(new Color($rgb), $x, $y);
//            if ($pixel instanceof Color)
//                $this[$idx]($pixel->getIndex());
//        }
    }

    function getAverageLuminance() {
        $luminanceSum = 0;
        for ($y = 0; $y < $this->height; ++$y) {
            for ($x = 0; $x < $this->width; ++$x) {
                $rgb = imagecolorat($this->handle, $x, $y);
                $r   = ($rgb >> 16) & 0xFF;
                $g   = ($rgb >> 8) & 0xFF;
                $b   = ($rgb) & 0xFF;
                $luminanceSum += (0.30 * $r) + (0.59 * $g) + (0.11 * $b);
            }
        }
        return $luminanceSum / $this->count();
    }

    public function sX() {
        return $this->width;
    }

    public function sY() {
        return $this->height;
    }

    public function offsetExists($offset) {
        return (($offset >= 0) && ($offset < $this->width * $this->height));
    }

    public function offsetGet($offset) {
        $x = (int) ($offset % $this->width);
        $y = (int) ($offset / $this->width);
        return imagecolorat($this->handle, $x, $y);
    }

    public function offsetSet($offset, $color) {
        $x = (int) ($offset % $this->width);
        $y = (int) ($offset / $this->width);
        imagesetpixel($this->handle, $x, $y, is_int($color) ? $color : Color::index($color));
    }

    public function offsetUnset($offset) {
        $x = (int) ($offset % $this->width);
        $y = (int) ($offset / $this->width);
        imagealphablending($this->handle, false);
        imagesetpixel($this->handle, $x, $y, $this->transparent);
        imagealphablending($this->handle, $this->blendmode);
    }

    public function currentSet($color) {
        $this->offsetSet($this->offset, $color);
    }

    public function current() {
        return $this->offsetGet($this->offset);
    }

    public function key() {
        return $this->offset;
    }

    public function next() {
        ++$this->offset;
    }

    public function rewind() {
        $this->offset = 0;
    }

    public function valid() {
        return $this->offsetExists($this->offset);
    }

    public function seek($offset) {
        $this->offset = $offset;
        if (!$this->valid())
            throw new \OutOfBoundsException("Invalid seek position ($offset)");
    }

    public function count() {
        return $this->width * $this->height;
    }

}

