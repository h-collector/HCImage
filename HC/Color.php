<?php

namespace HC;

/**
 * @author h-collector <githcoll@gmail.com>
 * 
 * @link          http://hcoll.onuse.pl/projects/view/HCImage
 * @package       HC
 * @license       GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 */
final class Color {

    private /* @var int */ $red,
            /* @var int */ $green,
            /* @var int */ $blue,
            /* @var int */ $alpha,
            /* @var int */ $index;

    public function getRed() {
        return $this->red;
    }

    public function getGreen() {
        return $this->green;
    }

    public function getBlue() {
        return $this->blue;
    }

    public function getAlpha() {
        return $this->alpha;
    }

    public function isOpaque() {
        return (0 === $this->alpha);
    }

    public function __construct($red, $green, $blue, $alpha = 0) {
        $this->red   = 0xff & $red;//((int) $red)
        $this->green = 0xff & $green;
        $this->blue  = 0xff & $blue;
        $this->alpha = 0x7f & $alpha;
    }
    
    private function _($red, $green, $blue, $alpha = 0) {
        //$this->__construct($red, $green, $blue, $alpha);//optim for speed
        $this->red   = 0xff & $red;
        $this->green = 0xff & $green;
        $this->blue  = 0xff & $blue;
        $this->alpha = 0x7f & $alpha;
        return $this;
    }

    public static function clear() {
        static $transparent;
        isset($transparent) || $transparent = new self(0, 0, 0, 127);
        return $transparent;
    }

    public static function fromRGBA($red, $green, $blue, $alpha = 0) {
        return new self($red, $green, $blue, $alpha = 0);
    }

    /**
     * Cone of HSV
     * @param float $h – Hue angle on color circle of base with values between 0° and 360°(0-255)
     * @param float $s – Saturation - base radius 0 - 1.0 (0 - 255)
     * @param float $v – Value aka Brightness 0 - 1.0 (0 - 255)
     * @return Color
     */
    public static function fromHSV($h, $s, $v, $limit255 = false) {
        $limit255 ? $s /= 256.0 : $v *= 255;
        if ($s == 0.0)
            return new self($v, $v, $v);//(int) $v, (int) $v, (int) $v
        $limit255 ? $h /= (256.0 / 6.0) : $h /= 60.0;
        $i = floor($h);
        $f = $h - $i;
        $p = $v * (1.0 - $s);//(integer)() 
        $q = $v * (1.0 - $s * $f);
        $t = $v * (1.0 - $s * (1.0 - $f));
        switch ($i) {
            case 1: return new self($q, $v, $p);
            case 2: return new self($p, $v, $t);
            case 3: return new self($p, $q, $v);
            case 4: return new self($t, $p, $v);
            case 5: return new self($v, $p, $q);
            default: return new self($v, $t, $p);
        }
    }
    
    public static function fromString($color) {
        $c = (string) $color;
        if (isset($c[0]) && $c[0] === '#') {
            switch (strlen($c)) {
                case 3: $c = "$c[0]$c[0]$c[1]$c[1]$c[2]$c[2]";
                case 6:
                case 8:
                    return self::fromInt(hexdec($c));
                default:
                    throw new \InvalidArgumentException(sprintf(
                            'Color must be a hex value in regular (6 characters), ' .
                            'short (3 characters) or argb (8 characters) notation, ' .
                            '"%s" given', $c
                    ));
            }
        } elseif (($c = self::namedColor2RGB($c)) !== false) {
            return self::fromArray($c);
        } else {
            throw new \InvalidArgumentException(sprintf(
                    'Named Color not found, "%s" given', $c
            ));
        }
    }

    public static function fromInt($color) {
        $color = intval($color);
        return new self(($color >> 16), ($color >> 8), ($color), ($color >> 24));
    }

    public static function fromArray(array $color) {
        if (($num = count($color)) !== 3 && $num !== 4)
            throw new \InvalidArgumentException(
            'Color argument if array, must look like array(R, G, B[, A]), ' .
            'where R, G, B are the integer values between 0 and 255 for ' .
            'red, green and blue color indexes accordingly and A is integer ' .
            'value between 0 and 127 for alfa'
            );
        return new self($color[0], $color[1], $color[2], ($num === 4 ? $color[3] : 0));
    }

    /**
     * 
     * @param type $color
     * @param type $green
     * @param type $blue
     * @param type $alpha
     * @return Color
     * @throws \InvalidArgumentException
     */
    public static function get($color = null, $green = null, $blue = null, $alpha = 0) {
        if ($color instanceof Color)    return clone $color;
        if ($color === null)        	return self::clear();
        if (is_array($color))   	return self::fromArray($color);
        if (is_string($color))          return self::fromString($color);
        if ($blue !== null)             return new self($color, $green, $blue, $alpha);
        if (is_int($color))             return self::fromInt($color);

        throw new \InvalidArgumentException(sprintf(
                        'Color must be specified as a hexadecimal string, color name, ' .
                        'array or integer, %s given', gettype($color)
        ));
    }
    
    public static function index($color = null, $green = null, $blue = null, $alpha = 0) {
        if(is_int($color) && $green === null) 
            return $color & 0x7fffffff;
        return self::get($color, $green, $blue, $alpha)->toInt();
    }

    /**
     * @param integer $alpha
     * @return Color
     */
    public function dissolve($alpha, $self = false) {
        $alpha = min(max($this->alpha + $alpha, 0), 0x7f);
        if($self)   return $this->_($this->red, $this->green, $this->blue, $alpha);
                    return new self($this->red, $this->green, $this->blue, $alpha);
    }

    /**
     * @param integer $shade
     * @return Color
     */
    public function adjustBrightness($shade, $self = false) {
        $r = min(max(0, $this->red   + $shade), 255);
        $g = min(max(0, $this->green + $shade), 255);
        $b = min(max(0, $this->blue  + $shade), 255);
        if($self)   return $this->_($r, $g, $b, $this->alpha);
                    return new self($r, $g, $b, $this->alpha);
    }

    public function gray($self = false) {
        $r = 0.2989 * $this->red;
        $g = 0.5870 * $this->green;
        $b = 0.1140 * $this->blue;
        if($self)   return $this->_($r, $g, $b, $this->alpha);
                    return new self($r, $g, $b, $this->alpha);
    }

    public function sum() {
        return $this->red + $this->green + $this->blue/* + $this->alpha*/;
    }

    public function rgb($self = false) {
        return $self ? $this : clone $this;
    }
    
    public function rbg($self = false) {
        if($self)   return $this->_($this->red, $this->blue, $this->green, $this->alpha);
                    return new self($this->red, $this->blue, $this->green, $this->alpha);
    }

    public function bgr($self = false) {
        if($self)   return $this->_($this->blue, $this->green, $this->red, $this->alpha);
                    return new self($this->blue, $this->green, $this->red, $this->alpha);
    }

    public function brg($self = false) {
        if($self)   return $this->_($this->blue, $this->red, $this->green, $this->alpha);
                    return new self($this->blue, $this->red, $this->green, $this->alpha);
    }

    public function gbr($self = false) {
        if($self)   return $this->_($this->green, $this->blue, $this->red, $this->alpha);
                    return new self($this->green, $this->blue, $this->red, $this->alpha);
    }

    public function grb($self = false) {
        if($self)   return $this->_($this->green, $this->red, $this->blue, $this->alpha);
                    return new self($this->green, $this->red, $this->blue, $this->alpha);
    }

    /**
     * @deprecated use 0xAARRGGBB hex instead
     * @param resource $image
     * @return int
     * @throws \InvalidArgumentException
     */
    public function allocateColor($image) {
        if (!Image::isValidImageHandle($image))
            throw new \InvalidArgumentException('Invalid image handle');

        $r     = $this->red;
        $g     = $this->green;
        $b     = $this->blue;
        $a     = $this->alpha;
        $color = imagecolorexactalpha($image, $r, $g, $b, $a);
        if ($color === -1) {
            if (imagecolorstotal($image) >= 255) {
                $color = imagecolorclosestalpha($image, $r, $g, $b, $a);
            } else {
                $color = imagecolorallocatealpha($image, $r, $g, $b, $a);
            }
        }
        return $color;
    }

    public function mixWithColor(Color $color, $self = false) {
        $r = (($this->red   + $color->red)   / 2) + 0.5;//(int) ()
        $g = (($this->green + $color->green) / 2) + 0.5;
        $b = (($this->blue  + $color->blue)  / 2) + 0.5;
        $a = (($this->alpha + $color->alpha) / 2) + 0.5;
        if($self)   return $this->_($r, $g, $b, $a);
                    return new self($r, $g, $b, $a);
    }

    public static function namedColor2RGB($color) {
        static $colornames = null;
        isset($colornames) || $colornames = array(
            'aliceblue'            => array(240, 248, 255),
            'antiquewhite'         => array(250, 235, 215),
            'aqua'                 => array(0, 255, 255),
            'aquamarine'           => array(127, 255, 212),
            'azure'                => array(240, 255, 255),
            'beige'                => array(245, 245, 220),
            'bisque'               => array(255, 228, 196),
            'black'                => array(0, 0, 0),
            'blanchedalmond'       => array(255, 235, 205),
            'blue'                 => array(0, 0, 255),
            'blueviolet'           => array(138, 43, 226),
            'brown'                => array(165, 42, 42),
            'burlywood'            => array(222, 184, 135),
            'cadetblue'            => array(95, 158, 160),
            'chartreuse'           => array(127, 255, 0),
            'chocolate'            => array(210, 105, 30),
            'coral'                => array(255, 127, 80),
            'cornflowerblue'       => array(100, 149, 237),
            'cornsilk'             => array(255, 248, 220),
            'crimson'              => array(220, 20, 60),
            'cyan'                 => array(0, 255, 255),
            'darkblue'             => array(0, 0, 13),
            'darkcyan'             => array(0, 139, 139),
            'darkgoldenrod'        => array(184, 134, 11),
            'darkgray'             => array(169, 169, 169),
            'darkgreen'            => array(0, 100, 0),
            'darkkhaki'            => array(189, 183, 107),
            'darkmagenta'          => array(139, 0, 139),
            'darkolivegreen'       => array(85, 107, 47),
            'darkorange'           => array(255, 140, 0),
            'darkorchid'           => array(153, 50, 204),
            'darkred'              => array(139, 0, 0),
            'darksalmon'           => array(233, 150, 122),
            'darkseagreen'         => array(143, 188, 143),
            'darkslateblue'        => array(72, 61, 139),
            'darkslategray'        => array(47, 79, 79),
            'darkturquoise'        => array(0, 206, 209),
            'darkviolet'           => array(148, 0, 211),
            'deeppink'             => array(255, 20, 147),
            'deepskyblue'          => array(0, 191, 255),
            'dimgray'              => array(105, 105, 105),
            'dodgerblue'           => array(30, 144, 255),
            'firebrick'            => array(178, 34, 34),
            'floralwhite'          => array(255, 250, 240),
            'forestgreen'          => array(34, 139, 34),
            'fuchsia'              => array(255, 0, 255),
            'gainsboro'            => array(220, 220, 220),
            'ghostwhite'           => array(248, 248, 255),
            'gold'                 => array(255, 215, 0),
            'goldenrod'            => array(218, 165, 32),
            'gray'                 => array(128, 128, 128),
            'green'                => array(0, 128, 0),
            'greenyellow'          => array(173, 255, 47),
            'honeydew'             => array(240, 255, 240),
            'hotpink'              => array(255, 105, 180),
            'indianred'            => array(205, 92, 92),
            'indigo'               => array(75, 0, 130),
            'ivory'                => array(255, 255, 240),
            'khaki'                => array(240, 230, 140),
            'lavender'             => array(230, 230, 250),
            'lavenderblush'        => array(255, 240, 245),
            'lawngreen'            => array(124, 252, 0),
            'lemonchiffon'         => array(255, 250, 205),
            'lightblue'            => array(173, 216, 230),
            'lightcoral'           => array(240, 128, 128),
            'lightcyan'            => array(224, 255, 255),
            'lightgoldenrodyellow' => array(250, 250, 210),
            'lightgreen'           => array(144, 238, 144),
            'lightgrey'            => array(211, 211, 211),
            'lightpink'            => array(255, 182, 193),
            'lightsalmon'          => array(255, 160, 122),
            'lightseagreen'        => array(32, 178, 170),
            'lightskyblue'         => array(135, 206, 250),
            'lightslategray'       => array(119, 136, 153),
            'lightsteelblue'       => array(176, 196, 222),
            'lightyellow'          => array(255, 255, 224),
            'lime'                 => array(0, 255, 0),
            'limegreen'            => array(50, 205, 50),
            'linen'                => array(250, 240, 230),
            'magenta'              => array(255, 0, 255),
            'maroon'               => array(128, 0, 0),
            'mediumaquamarine'     => array(102, 205, 170),
            'mediumblue'           => array(0, 0, 205),
            'mediumorchid'         => array(186, 85, 211),
            'mediumpurple'         => array(147, 112, 219),
            'mediumseagreen'       => array(60, 179, 113),
            'mediumslateblue'      => array(123, 104, 238),
            'mediumspringgreen'    => array(0, 250, 154),
            'mediumturquoise'      => array(72, 209, 204),
            'mediumvioletred'      => array(199, 21, 133),
            'midnightblue'         => array(25, 25, 112),
            'mintcream'            => array(245, 255, 250),
            'mistyrose'            => array(255, 228, 225),
            'moccasin'             => array(255, 228, 181),
            'navajowhite'          => array(255, 222, 173),
            'navy'                 => array(0, 0, 128),
            'oldlace'              => array(253, 245, 230),
            'olive'                => array(128, 128, 0),
            'olivedrab'            => array(107, 142, 35),
            'orange'               => array(255, 165, 0),
            'orangered'            => array(255, 69, 0),
            'orchid'               => array(218, 112, 214),
            'palegoldenrod'        => array(238, 232, 170),
            'palegreen'            => array(152, 251, 152),
            'paleturquoise'        => array(175, 238, 238),
            'palevioletred'        => array(219, 112, 147),
            'papayawhip'           => array(255, 239, 213),
            'peachpuff'            => array(255, 218, 185),
            'peru'                 => array(205, 133, 63),
            'pink'                 => array(255, 192, 203),
            'plum'                 => array(221, 160, 221),
            'powderblue'           => array(176, 224, 230),
            'purple'               => array(128, 0, 128),
            'red'                  => array(255, 0, 0),
            'rosybrown'            => array(188, 143, 143),
            'royalblue'            => array(65, 105, 225),
            'saddlebrown'          => array(139, 69, 19),
            'salmon'               => array(250, 128, 114),
            'sandybrown'           => array(244, 164, 96),
            'seagreen'             => array(46, 139, 87),
            'seashell'             => array(255, 245, 238),
            'sienna'               => array(160, 82, 45),
            'silver'               => array(192, 192, 192),
            'skyblue'              => array(135, 206, 235),
            'slateblue'            => array(106, 90, 205),
            'slategray'            => array(112, 128, 144),
            'snow'                 => array(255, 250, 250),
            'springgreen'          => array(0, 255, 127),
            'steelblue'            => array(70, 130, 180),
            'tan'                  => array(210, 180, 140),
            'teal'                 => array(0, 128, 128),
            'thistle'              => array(216, 191, 216),
            'tomato'               => array(255, 99, 71),
            'turquoise'            => array(64, 224, 208),
            'violet'               => array(238, 130, 238),
            'wheat'                => array(245, 222, 179),
            'white'                => array(255, 255, 255),
            'whitesmoke'           => array(245, 245, 245),
            'yellow'               => array(255, 255, 0),
            'yellowgreen'          => array(154, 205, 50)
        );

        $color = strtolower($color);
        if (isset($colornames[$color]))
            return $colornames[$color];
        return false;
    }

    public function getAsHSV() {
        $min = min($this->red, $this->green, $this->blue);
        $val = max($this->red, $this->green, $this->blue);
        if ($min === $val) {
            $hue = 0;
            $sat = 0;
        } else {
            switch ($min) {
                case $this->red: $f   = $this->green - $this->blue;
                    $i   = 3 * 255;
                    break;
                case $this->green: $f = $this->blue - $this->red;
                    $i   = 5 * 255;
                    break;
                case $this->blue: $f  = $this->red - $this->green;
                    $i   = 1 * 255;
            }
            $hue = (($i - $f / ($val - $min)) * 60) % 360;
            $sat = (($val - $min) / $val);
        }
        return (object) array('hue' => $hue, 'sat' => $sat, 'val' => $val / 255.0);
    }
    
    public function toInt() {
        if (!isset($this->index)) {
            $this->index =
                    ($this->alpha << 24) +
                    ($this->red   << 16) +
                    ($this->green << 8) +
                    ($this->blue);
        }
        return $this->index;
    }

    public function toArray() {
        return array(
            'r' => $this->red,
            'g' => $this->green,
            'b' => $this->blue,
            'a' => $this->alpha
        );
    }
  
    public function __get($name) {
        if (!property_exists($this, $name))
            throw new \InvalidArgumentException('Invalid property');
        return ($name === 'index') ? $this->toInt() : $this->{$name};
    }

    /**
     * @example (new Color(255,0,0))->dissolve(20)->getIndex() 
     *          <=> (new Color(255,0,0,20))->getIndex()
     *          <=> (new Color(255,0,0))(20) 
     *          <=> (new Color(255,0,0,20)) 
     *          <=> Color(255,0,0,20)
     * @param type $dissolve
     * @return type
     */
    public function __invoke($dissolve = null) {
        if (is_int($dissolve))
            return $this->dissolve($dissolve)->toInt();
        return $this->toInt();
    }

    public function __toString() {
//        return sprintf('#%08x', $this->toInt());
        return sprintf('#%02x%02x%02x%02x', $this->alpha, $this->red, $this->green, $this->blue);
    }

}
