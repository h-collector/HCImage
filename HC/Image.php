<?php

namespace HC;

/**
 * Description of Image
 *
 * @author h-collector <githcoll@gmail.com>
 * 
 * @link          http://hcoll.onuse.pl/view/HCImage
 * @package       HC
 * @license       GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 */
class Image {//\SplSubject

    private /** @var resource */ $handle     = null,
            /** @var string   */ $imageType  = IMAGETYPE_PNG, //'gd',
            /** @var string   */ $sourceFile = '',
            /** @var Canvas   */ $canvas     = null;

    const /** @var string   */ AUTO = 'auto'; //center merge or crop

    public function __construct($image, $fromString = false) {
        if (self::isValidImageHandle($image)) {
            $this->handle = $image;
        } elseif ($fromString) {
            $this->loadFromString($image);
        } elseif (is_string($image)) {
            $this->loadFromFile($image);
        }
        else
            throw new \InvalidArgumentException('Unknown image data');

        if (!imageistruecolor($this->handle)) {//allways 32bit color, more RAM, but alpha channel
            $this->replaceImage($this->copyAsTrueColorGDImage());
        } else {
            imagesavealpha($this->handle, true);
        }
    }

    public function __destruct() {
        if (self::isValidImageHandle($this->handle))
            imagedestroy($this->handle);
        unset($this->canvas, $this->handle);
    }

    /**
     * @param int $width
     * @param int $height
     * @param mixed $bg
     * @return Image
     */
    public static function create($width, $height, $bg = null) {
        $handle = self::createTrueColor($width, $height, $bg);
        return new self($handle);
    }

    private static function createTrueColor($width, $height, $bg = null) {
        $handle = imagecreatetruecolor((int) $width, (int) $height);
        imagefill($handle, 0, 0, Color::index($bg));
        imagesavealpha($handle, true);
        return $handle;
    }

    /**
     * @param mixed $image
     * @param bool $fromString
     * @return Image
     */
    public static function load($image, $fromString = false) {
        return new self($image, $fromString);
    }

    public static function isValidImageHandle($handle) {
        return (is_resource($handle) && get_resource_type($handle) == 'gd');
    }

    private function loadFromString($data) {
        $this->handle = imagecreatefromstring($data);
        if (!self::isValidImageHandle($this->handle))
            throw new \RuntimeException("Could not create image from data.");

        $mimetypes = array(
            'image/gif'  => IMAGETYPE_GIF,
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png'  => IMAGETYPE_PNG
        );

        $finfo           = new finfo(FILEINFO_MIME_TYPE);
        $mime            = $finfo->buffer($data);
        $this->imageType = isset($mimetypes[$mime]) ? $mimetypes[$mime] : 'gd';
    }

    private function loadFromFile($filename) {
        if (!is_file($filename) && strpos($filename, 'http://') !== 0)
            throw new \InvalidArgumentException("Image file [{$filename}] not found.");

        if (!function_exists('exif_imagetype')) {
            $imageInfo       = getimagesize($filename);
            $this->imageType = $imageInfo[2];
        } else {
            $this->imageType = exif_imagetype($filename);
        }

        switch ($this->imageType) {
            case IMAGETYPE_JPEG:
                $this->handle = imagecreatefromjpeg($filename);
                break;
            case IMAGETYPE_GIF:
                $this->handle = imagecreatefromgif($filename);
                break;
            case IMAGETYPE_PNG:
                $this->handle = imagecreatefrompng($filename);
                break;
            default:
                throw new \RuntimeException("Image type [$this->imageType] not supported");
        }
        if (!self::isValidImageHandle($this->handle))
            throw new \RuntimeException("Coulnd't load image [$filename]");

        $this->sourceFile = $filename;
    }

    public function save($filename = false, $imageType = false, $quality = false /* 85 | 6 */, $permissions = null) {
        if ($this->saveOrOutput($filename, $imageType, $quality) === false)
            throw new \ErrorException("Could't save file [{$filename}]");

        if ($permissions != null)
            chmod($filename, $permissions);
        return $this;
    }

    public function output($imageType = false) {
        if ($this->saveOrOutput(null, $imageType) === false)
            throw new \ErrorException("Could't output image to browser");
        return $this;
    }

    private function saveOrOutput($filename = false, $imageType = false, $quality = false /* 85 | 6 */) {
        if ($filename === false)
            $filename = $this->sourceFile;

        if (empty($this->sourceFile))
            $this->sourceFile = $filename;

        if ($imageType === false)
            $imageType = $this->imageType;

        if (!in_array($imageType, array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF)))
            throw new \InvalidArgumentException("Image type [$imageType] not supported");

        if (is_null($filename))
            header('Content-Type: ' . image_type_to_mime_type($imageType));

        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagejpeg($this->handle, $filename, $quality ? $quality : 85);
            case IMAGETYPE_GIF:
                return imagegif($this->handle, $filename);
            case IMAGETYPE_PNG:
                return imagepng($this->handle, $filename, $quality ? $quality : 6);
        }
    }

    public function getHandle() {
        return $this->handle;
    }

    public function getImageType() {
        return $this->imageType;
    }

    public function getSourceFile() {
        return $this->sourceFile;
    }

    public function getWidth() {
        return imagesx($this->handle);
    }

    public function getHeight() {
        return imagesy($this->handle);
    }

    public function getTransparentColor($returnObj = false) {
        $color = imagecolortransparent($this->handle);
        if ($returnObj) {
            if ($color === -1) {
                $color = Color::clear();
            } else {
                $color = Color::fromInt(imagecolorsforindex($this->handle, $color));
            }
        }
        return $color;
    }

    public function setTransparentColor($color = null) {
        return imagecolortransparent($this->handle, Color::index($color));
    }

    public function resizeToHeight($height, $resample = true) {
        $ratio = $height / $this->getHeight();
        $width = $this->getWidth() * $ratio;
        return $this->resize($width, $height, $resample);
    }

    public function resizeToWidth($width, $resample = true) {
        $ratio  = $width / $this->getWidth();
        $height = $this->getheight() * $ratio;
        return $this->resize($width, $height, $resample);
    }

    public function scale($scale, $resample = true) {
        $width  = $this->getWidth() * $scale / 100;
        $height = $this->getheight() * $scale / 100;
        return $this->resize($width, $height, $resample);
    }

    public function resize($width, $height, $resample = true, $keepAspect = false, $bgColor = null) {
        if ($width === self::AUTO && $height === self::AUTO)
            return $this;
        if ($width === self::AUTO)
            return $this->resizeToHeight($height, $resample);
        if ($height === self::AUTO)
            return $this->resizeToWidth($width, $resample);
        $dstX       = 0;
        $dstY       = 0;
        $srcW       = $this->getWidth();
        $srcH       = $this->getHeight();
        $dstW       = $width > 0 ? (int) ceil($width) : $srcW; //ceil?
        $dstH       = $height > 0 ? (int) ceil($height) : $srcH; //ceil?
        if ($srcW === $dstW && $srcH === $dstH)
            return $this;
        $resizeFunc = $resample ? 'imagecopyresampled' : 'imagecopyresized';
        $bgColor    = $bgColor === null ? $this->getTransparentColor(true) : $bgColor;
        $newImage   = self::createTrueColor($dstW, $dstH, $bgColor);
        if ($keepAspect) {
            if (abs($dstH - $srcH) > abs($dstW - $srcW)) {
                $dstH = (int) ceil($dstW * $srcH / $srcW);
                $dstY = (int) (($height - $dstH) / 2);
            } else {
                $dstW = (int) ceil($dstH * $srcW / $srcH);
                $dstX = (int) (($width - $dstW) / 2);
            }
        }
        if (false === $resizeFunc($newImage, $this->handle, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH))
            throw new \RuntimeException('Resize operation failed');
        $this->replaceImage($newImage);
        return $this;
    }

    /**
     * @link http://scale2x.sourceforge.net/algorithm.html
     * @return \HC\Image
     */
    public function scale2x() {
        $width    = $this->getWidth();
        $height   = $this->getHeight();
        $bg       = $this->getTransparentColor(true);
        $newImage = self::createTrueColor($width * 2, $height * 2, $bg);
        for ($x = 0, $dx = 0; $x < $width; ++$x, $dx+=2)//x -> y?
            for ($y = 0, $dy = 0; $y < $height; ++$y, $dy+=2) {
                $x1 = $x === 0 ? 0 : $x - 1;
                $y1 = $y === 0 ? 0 : $y - 1;
                $x2 = $x === $width - 1 ? $x : $x + 1;
                $y2 = $y === $height - 1 ? $y : $y + 1;
                #A (-1,-1)	B (0,-1) C (1,-1)
                #D (-1,0)	E (0,0)	 F (1,0)
                #G (-1,1)   H (0,1)	 I (1,1)
                $B  = imagecolorat($this->handle, $x, $y1);
                $D  = imagecolorat($this->handle, $x1, $y);
                $E  = imagecolorat($this->handle, $x, $y);
                $F  = imagecolorat($this->handle, $x2, $y);
                $H  = imagecolorat($this->handle, $x, $y2);
                if ($B !== $H && $D !== $F) {
                    $E0 = $D === $B ? $D : $E;
                    $E1 = $B === $F ? $F : $E;
                    $E2 = $D === $H ? $D : $E;
                    $E3 = $H === $F ? $F : $E;
                } else {
                    $E0 = $E1 = $E2 = $E3 = $E;
                }
                imagesetpixel($newImage, $dx, $dy, $E0);
                imagesetpixel($newImage, $dx + 1, $dy, $E1);
                imagesetpixel($newImage, $dx, $dy + 1, $E2);
                imagesetpixel($newImage, $dx + 1, $dy + 1, $E3);
            }
        $this->replaceImage($newImage);
        return $this;
    }

    function rotate($angle, $bgColor = null, $ignoreTransparent = 0) {
        $angle = -floatval($angle);
        $angle = ($angle < 0) ? 360 + $angle : $angle;
        $angle = $angle % 360;

        if ($angle === 0)
            return $this;

        $bgColor = $bgColor === null ? $this->getTransparentColor(true) : Color::get($bgColor);
        $rotated = imagerotate($this->handle, $angle, $bgColor->toInt(), $ignoreTransparent);
        $this->replaceImage($rotated);
        return $this;
    }

    public function centeredBox($widthOrImg, $height = 0) {
        if ($widthOrImg instanceof Image) {
            $height     = $widthOrImg->getHeight();
            $widthOrImg = $widthOrImg->getWidth();
        }
        if ($widthOrImg <= 0 || $height <= 0)
            throw new \InvalidArgumentException("Width {$widthOrImg} and height {$height} should be > 0");
        return (object) array(
                    'x' => (int) (($this->getWidth() - $widthOrImg) / 2),
                    'y' => (int) (($this->getHeight() - $height) / 2),
                    'w' => $widthOrImg,
                    'h' => $height
        );
    }

    public function crop($x = 0, $y = 0, $width = null, $height = null, $bgColor = null) {
        $srcWidth  = $this->getWidth();
        $srcHeight = $this->getHeight();
        $width     = $width ? $width : $srcWidth;
        $height    = $height ? $height : $srcHeight;
        if ($x === self::AUTO || $y === self::AUTO) {
            $box = $this->centeredBox($width, $height);
            $x   = $x === self::AUTO ? $box->x : $x;
            $y   = $y === self::AUTO ? $box->y : $y;
        }
        $bgColor  = $bgColor === null ? $this->getTransparentColor(true) : $bgColor;
        $newImage = self::createTrueColor($width, $height, $bgColor);
        if (false === imagecopy($newImage, $this->handle
                        , -min($x, 0), -min($y, 0), max(0, $x), max(0, $y)
                        , $srcWidth, $srcHeight)) {
            throw new \RuntimeException('Crop operation failed');
        }
        $this->replaceImage($newImage);
        return $this;
    }

    public function merge(Image $image, $x = 0, $y = 0, $pct = 100) {
        if ($x === self::AUTO || $y === self::AUTO) {
            $box = $this->centeredBox($image);
            $x   = $x === self::AUTO ? $box->x : $x;
            $y   = $y === self::AUTO ? $box->y : $y;
        }
        switch ($pct) {
            case 100 : $func = 'imagecopy'; //imagecopymerge, function.imagecopymerge.html#92787?
                if (false === imagecopy($this->handle, $image->handle
                                , max(0, $x), max(0, $y), -min($x, 0), -min($y, 0)
                                , $image->getWidth(), $image->getHeight())) {
                    throw new \RuntimeException('Merge operation failed');
                }
                return $this;
            case -1: $func = 'imagecopymergegray';
                break;
            default: $func = 'imagecopymerge';
                break;
        }
        if (false === $func($this->handle, $image->handle
                        , max(0, $x), max(0, $y), -min($x, 0), -min($y, 0)
                        , $image->getWidth(), $image->getHeight(), $pct)) {
            throw new \RuntimeException('Merge operation failed');
        }
        return $this;
    }

    public function flip($vertical = true) {
        $width  = $this->getWidth();
        $height = $this->getHeight();
        $dest   = self::createTrueColor($width, $height);

        if ($vertical) {
            for ($i = 0; $i < $height; $i++)
                if (false === imagecopy($dest, $this->handle, 0, $i, 0, ($height - 1) - $i, $width, 1))
                    throw new \RuntimeException('Vertical flip operation failed');
        } else {
            for ($i = 0; $i < $width; $i++)
                if (false === imagecopy($dest, $this->handle, $i, 0, ($width - 1) - $i, 0, 1, $height))
                    throw new \RuntimeException('Horizontal flip operation failed');
        }

        $this->replaceImage($dest);
        return $this;
    }

    /**
     * @link http://stackoverflow.com/questions/1669683/crop-whitespace-from-image-in-php
     */
    public function trimmedBox($color = -1) {
        $color   = $color === -1 ? imagecolorat($this->handle, 0, 0) : Color::index($color);
        $width   = $this->getWidth();
        $height  = $this->getHeight();
        $bTop    = 0;
        $bLeft   = 0;
        $bBottom = $height - 1;
        $bRight  = $width - 1;
        //top
        for (; $bTop < $height; ++$bTop)
            for ($x = 0; $x < $width; ++$x)
                if (imagecolorat($this->handle, $x, $bTop) !== $color)
                    break 2;
        // return false when all pixels are trimmed
        if ($bTop === $height)
            return false;
        // bottom
        for (; $bBottom >= 0; --$bBottom)
            for ($x = 0; $x < $width; ++$x)
                if (imagecolorat($this->handle, $x, $bBottom) !== $color)
                    break 2;
        // left
        for (; $bLeft < $width; ++$bLeft)
            for ($y = $bTop; $y <= $bBottom; ++$y)
                if (imagecolorat($this->handle, $bLeft, $y) !== $color)
                    break 2;
        // right
        for (; $bRight >= 0; --$bRight)
            for ($y = $bTop; $y <= $bBottom; ++$y)
                if (imagecolorat($this->handle, $bRight, $y) !== $color)
                    break 2;
        ++$bBottom;
        ++$bRight;
        return (object) array(
                    'l' => $bLeft,
                    't' => $bTop,
                    'r' => $bRight,
                    'b' => $bBottom,
                    'w' => $bRight - $bLeft,
                    'h' => $bBottom - $bTop
        );
    }

    public function trim($color = -1) {
        if (($box = $this->trimmedBox($color)) === false)
            throw new \RuntimeException('Image would be blanked after trim');
        return $this->crop($box->l, $box->t, $box->w, $box->h);
    }

    public function compare(Image $image, array &$info = null, $diffImage = true) {
        $width  = $this->getWidth();
        $height = $this->getHeight();
        if ($width !== $image->getWidth() || $height !== $image->getHeight())
            throw new \InvalidArgumentException(
            'Source and destination images ' .
            'should have the same dimensions');

        $info = array(
            'diffPixels' => 0,
            'ratio'      => 0
        );

        $destHandle = $image->getHandle();
        if ($diffImage) {
            $diffImage  = clone $this; //Image::create($width, $height);
            $diffHandle = $diffImage->getHandle();
            $diffCanvas = $diffImage->getCanvas();
            $diffCanvas->filter(IMG_FILTER_GRAYSCALE);
            $diffCanvas->filter(IMG_FILTER_BRIGHTNESS, 200);

            for ($y = 0; $y < $height; ++$y) {
                for ($x = 0; $x < $width; ++$x) {
                    $pix1 = imagecolorat($this->handle, $x, $y); //$srcCanvas->colorAt($x, $y);
                    $pix2 = imagecolorat($destHandle, $x, $y); //$dstCanvas->colorAt($x, $y);
                    if ($pix1 !== $pix2) {
                        ++$info['diffPixels'];
                        imagesetpixel($diffHandle, $x, $y, $pix2); //$diffCanvas->setPixel($x, $y, $pix2);
                    }
                }
            }
        } else {
            for ($y = 0; $y < $height; ++$y) {
                for ($x = 0; $x < $width; ++$x) {
                    if (imagecolorat($this->handle, $x, $y) !== imagecolorat($destHandle, $x, $y))
                        ++$info['diffPixels'];
                }
            }
        }

        $info['ratio'] = round((100 * $info['diffPixels']) / ($width * $height), 2) . '%';
        return $diffImage;
    }

    public function histogram() {
        for ($y = 0, $width = $this->getWidth(), $height = $this->getHeight(); $y < $height; ++$y)
            for ($x = 0; $x < $width; ++$x) {
                $c          = imagecolorat($this->handle, $x, $y);
                (isset($colors[$c]) && ++$colors[$c]) || ($colors[$c] = 1);
            }
        ksort($colors);
        return $colors;
    }

    public function replaceImage($newImage) {
        if ($newImage instanceof Image)
            $newImage = $newImage->getHandle();

        if (!self::isValidImageHandle($this->handle))
            throw new \ErrorException("Invalid image handle.");
        imagedestroy($this->handle);
        $this->handle = $newImage;
        $this->updateCanvas();
    }

    /**
     * @return Canvas
     */
    public function getCanvas() {
        if ($this->canvas == null)
            $this->canvas = new Canvas($this);
        return $this->canvas;
    }

    public function updateCanvas() {
        if (isset($this->canvas))
            $this->canvas->updateHandle($this);
    }

    public function copyAsTrueColorGDImage() {
        $width    = $this->getWidth();
        $height   = $this->getHeight();
        $bg       = $this->getTransparentColor(true);
        $newImage = self::createTrueColor($width, $height, $bg);
        imagecopy($newImage, $this->handle, 0, 0, 0, 0, $width, $height);
        return $newImage;
    }

    public function __clone() {
        $this->handle = $this->copyAsTrueColorGDImage();
        $this->canvas = null;
    }

    public function __toString() {
        return "<img src=\"{$this->sourceFile}\" alt=\"\">";
    }

}
