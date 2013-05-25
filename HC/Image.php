<?php

namespace HC;

use Exception;
use ErrorException;
use RuntimeException;
use InvalidArgumentException;
use finfo;
use stdClass;

/**
 * Image
 *
 * @package HC
 * @author  h-collector <githcoll@gmail.com>
 *          
 * @link    http://hcoll.onuse.pl/view/HCImage
 * @license GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 */
class Image {//\SplSubject

    private /** @var resource */ $handle      = null,
            /** @var string   */ $imageType   = IMAGETYPE_PNG, //'gd',
            /** @var string   */ $sourceFile  = '',
            /** @var Canvas   */ $canvas      = null,
            /** @var int      */ $transparent = 0;

    const /** @var string   */ AUTO = 'auto'; //center merge or crop

    /**
     * Create new image from disk, url or binary string
     * 
     * @see Image::load()
     * @param string|resource|Canvas|Image $image      filepath, url binary string, 
     *                                                 Image to copy, Canvas to own
     * @param bool                         $fromString create image from string
     * @param bool                         $forceAlpha try to add/preserve alpha channel
     * @throws InvalidArgumentException
     */

    public function __construct($image, $fromString = false, $forceAlpha = true) {
        if ($image instanceof Image) {//clone image
            $this->handle = $image->copyAsTrueColorGDImage($forceAlpha);
        } elseif ($image instanceof Canvas) {
            $this->handle = $image->getHandle();
            $this->canvas = $image;
        } elseif (self::isValidImageHandle($image)) {
            $this->handle = $image;
        } elseif ($fromString) {
            $this->loadFromString($image);
        } elseif (is_string($image)) {
            $this->loadFromFile($image);
        }
        else
            throw new InvalidArgumentException('Unknown image data');

        if (imageistruecolor($this->handle)) {
            imagesavealpha($this->handle, true);
        } elseif ($forceAlpha) {
            //imagepalettetotruecolor 
            $this->replaceImage($this->copyAsTrueColorGDImage($forceAlpha));
        }
    }

    public function __destruct() {
        if (self::isValidImageHandle($this->handle))
            imagedestroy($this->handle);
        unset($this->canvas, $this->handle);
    }

    /**
     * Make new image given dimensions
     * 
     * @param int   $width  width of new image
     * @param int   $height height of new image
     * @param mixed $bg     color of image canvas
     * @return Image
     */
    public static function create($width, $height, $bg = null) {
        $handle = self::createTrueColor($width, $height, $bg);
        return new self($handle);
    }

    /**
     * Create new gdimage resource
     * 
     * @param int   $width  width of image
     * @param int   $height height of image
     * @param mixed $bg     background color
     * @return resource handle to new image
     */
    private static function createTrueColor($width, $height, $bg = null) {
        $handle = imagecreatetruecolor((int) $width, (int) $height);
        $bg !== -1 && imagefill($handle, 0, 0, Color::index($bg));
        imagesavealpha($handle, true);
        return $handle;
    }

    /**
     * Load image from disk, url or binary
     * 
     * @uses Image::loadFromString() autodetected if not resource
     * @uses Image::loadFromFile()
     * @param string $image      path, url or binary string
     * @param bool   $forceAlpha try to add/preserve alpha channel
     * @throws InvalidArgumentException
     * @return Image
     */
    public static function load($image, $forceAlpha = true) {
        return new self($image, is_string($image) && !ctype_print($image), $forceAlpha);
    }

    /**
     * Check if image handle is valid
     * 
     * @param resource $handlehandle to image resource
     * @return bool true if is valid
     */
    public static function isValidImageHandle($handle) {
        return (is_resource($handle) && get_resource_type($handle) === 'gd');
    }

    /**
     * Load image from binary string
     * (does not convert to 32bit truecolor)
     * 
     * @see finfo
     * @see imagecreatefromstring
     * @param string $data binary data
     * @throws RuntimeException
     */
    private function loadFromString($data) {
        $this->handle = imagecreatefromstring($data);
        if (!self::isValidImageHandle($this->handle))
            throw new RuntimeException("Could not create image from data");

        $mimetypes = array(
            'image/gif'  => IMAGETYPE_GIF,
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png'  => IMAGETYPE_PNG
        );

        $finfo           = new finfo(FILEINFO_MIME_TYPE);
        $mime            = $finfo->buffer($data);
        $this->imageType = isset($mimetypes[$mime]) ? $mimetypes[$mime] : 'gd';
    }

    /**
     * Load image from disk or url
     * (does not convert to 32bit truecolor)
     * 
     * @see exif_imagetype
     * @see getimagesize
     * @see imagecreatefromjpeg,
     * @see imagecreatefrompng
     * @see imagecreatefromgif
     * @param string $filename filepath or url to image
     * @throws \RuntimeException|\InvalidArgumentException
     */
    private function loadFromFile($filename) {
        if (!is_file($filename) && strpos($filename, 'http://') !== 0)
            throw new InvalidArgumentException("Image file [{$filename}] not found");

        if (function_exists('exif_imagetype')) {
            $this->imageType = exif_imagetype($filename);
        } else {
            $imageInfo       = getimagesize($filename);
            $this->imageType = $imageInfo[2];
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
                throw new RuntimeException("Image type [$this->imageType] not supported");
        }
        if (!self::isValidImageHandle($this->handle))
            throw new RuntimeException("Coulnd't load image [$filename]");

        $this->sourceFile = $filename;
    }

    /**
     * Save image to disk
     * 
     * @uses Image::saveOrOutput()
     * @see chmod
     * @param bool|string     $filename     filepath or false for the same as source
     * @param bool|int|string $imageType    type of image or false for the same as source
     * @param bool|int        $quality      quality or compression level 
     *                                      (9 for png also apply all compression filters)
     * @param int             $permissions  changes file mode
     * @return Image
     * @throws ErrorException
     */
    public function save($filename = false, $imageType = false, $quality = false /* 85 | 6 */, $permissions = null) {
        if ($this->saveOrOutput($filename, $imageType, $quality) === false)
            throw new ErrorException("Could't save file [{$filename}]");
        if ($permissions != null)
            chmod($filename, $permissions);
        return $this;
    }

    /**
     * Output image to browser or stdout
     * 
     * @uses Image::saveOrOutput()
     * @param bool|int|string $imageType type of image (IMAGETYPE_ or one of ext: png, jpg, gif) 
     *                                   or false for the same as source
     * @param bool|int $quality   quality or compression level
     * @param bool     $headers   emit mimetype http headers for output, also attachment name
     * @return Image
     * @throws ErrorException
     */
    public function output($imageType = false, $quality = false, $headers = true) {
        if ($this->saveOrOutput(null, $imageType, $quality, $headers) === false)
            throw new ErrorException("Could't output image to browser");
        return $this;
    }

    /**
     * Save to disk or output image to browser (only jpg, png, gif)
     * 
     * @param bool|string     $filename  filepath or false for the same as source, null for output
     * @param bool|int|string $imageType type of image (IMAGETYPE_ or one of ext: png, jpg, gif) 
     *                                   or false for the same as source
     * @param bool|int        $quality   quality for jpg [1-100] defaults to 85 
     *                                   or compression level for png [0-9] defaults to 6
     *                                   (9 for png also apply all compression filters)
     * @param bool|string     $headers   emit mimetype http headers for output, also attachment name
     * @return bool success or failure
     * @throws InvalidArgumentException
     */
    private function saveOrOutput($filename = false, $imageType = false, $quality = false, $headers = true) {
        if ($filename === false) {
            if ($this->sourceFile === "")
                throw new InvalidArgumentException('Filepath for new image must be set');
            $filename = $this->sourceFile;
        }

        if ($imageType === false) {
            $imageType = $this->imageType;
        } elseif (is_string($imageType)) {
            $imageType = self::extensionToImageType($imageType);
        }

        if (!in_array($imageType, array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF)))
            throw new InvalidArgumentException("Image type [$imageType] not supported");

        if ($filename === null) {
            if ($headers)
                header('Content-Type: ' . image_type_to_mime_type($imageType));
            if (is_string($headers)) {
                header("Content-Disposition: attachment; filename=" . urlencode($headers));
                header("Content-Type: application/force-download");
                header("Content-Type: application/octet-stream");
                header("Content-Type: application/download");
                header("Content-Description: File Transfer");
                if (($this->sourceFile === $filename) && is_readable($filename))
                    header("Content-Length: " . filesize($filename));
            }
        } else {
            $info      = pathinfo($filename);
            $extByPath = &$info['extension'];
            $extByMime = str_replace('jpeg', 'jpg', image_type_to_extension($imageType, false));
            if (isset($extByPath)) {
                if (str_replace('jpeg', 'jpg', $extByPath) !== $extByMime)
                    trigger_error("Specified file extension [{$extByPath}] doesn't match it's type [{$extByMime}]");
            } else {//append extension
                $filename = "{$filename}.{$extByMime}";
            }
        }

        switch ($imageType) {
            case IMAGETYPE_JPEG: $success = imagejpeg($this->handle, $filename, $quality ? $quality : 85);
                break;
            case IMAGETYPE_GIF: $success = imagegif($this->handle, $filename);
                break;
            case IMAGETYPE_PNG:
                $quality = $quality ? $quality : 6;
                $filters = $quality === 9 ? PNG_ALL_FILTERS : null;
                $success = imagepng($this->handle, $filename, $quality, $filters);
                break;
        }
        if ($success && $filename !== null) {
            $this->sourceFile = $filename;
            $this->imageType  = $imageType;
        }
        return $success;
    }

    /**
     * 
     * @param string $extension
     * @return string|int
     */
    public static function extensionToImageType($extension) {
        switch (strtolower($extension)) {
            case 'jpg':
            case 'jpeg': return IMAGETYPE_JPEG;
            case 'png': return IMAGETYPE_PNG;
            case 'gif': return IMAGETYPE_GIF;
            default: return $extension;
        }
    }

    /**
     * Get image resource handle
     * 
     * @return resource
     */
    private function getHandle() {
        return $this->handle;
    }

    /**
     * Get image type
     * 
     * @return int type of image as constant IMAGE_
     */
    public function getImageType() {
        return $this->imageType;
    }

    /**
     * Get filepath of image source
     * 
     * @return string
     */
    public function getSourceFile() {
        return $this->sourceFile;
    }

    /**
     * Get Image width
     * 
     * @return int
     */
    public function getWidth() {
        return imagesx($this->handle);
    }

    /**
     * Get image height
     * 
     * @return int
     */
    public function getHeight() {
        return imagesy($this->handle);
    }

    /**
     * Get image transparent color
     * 
     * @see imagecolortransparent
     * @see  Color::clear()
     * @see  Color::fromInt
     * @param bool $returnObj return color object or index
     * @return Color|int
     */
    public function getTransparentColor($returnObj = false, $noAlpha = false) {
        $color = imagecolortransparent($this->handle);
        if ($noAlpha)
            $color = ($color === -1) ? $this->transparent : $color & 0x00ffffff;
        if ($returnObj) {
            if ($color === -1) {
                $color = Color::clear();
            } else {
                $color = Color::fromInt($color);
            }
        }
        return $color;
    }

    /**
     * Set transparent color for image
     * 
     * @see imagecolortransparent
     * @param mixed $color 
     * @return int
     */
    public function setTransparentColor($color = null) {
        $this->transparent = Color::index($color);
        return imagecolortransparent($this->handle, $this->transparent);
    }

    /**
     * Resize image to height with aspect ratio
     * 
     * @see imagecopyresampled
     * @see imagecopyresized
     * @param int  $height   new height
     * @param bool $resample resample or not
     * @return Image
     */
    public function resizeToHeight($height, $resample = true) {
        $ratio = $height / $this->getHeight();
        $width = $this->getWidth() * $ratio;
        return $this->resize($width, $height, $resample);
    }

    /**
     * Resize image to width with aspect ratio
     * 
     * @see imagecopyresampled
     * @see imagecopyresized
     * @param int  $width    new image width
     * @param bool $resample
     * @return Image
     */
    public function resizeToWidth($width, $resample = true) {
        $ratio  = $width / $this->getWidth();
        $height = $this->getheight() * $ratio;
        return $this->resize($width, $height, $resample);
    }

    /**
     * Scale image
     * 
     * @see imagecopyresampled
     * @see imagecopyresized
     * @param int  $scale    percent
     * @param bool $resample
     * @return Image
     */
    public function scale($scale, $resample = true) {
        $width  = $this->getWidth() * $scale / 100;
        $height = $this->getheight() * $scale / 100;
        return $this->resize($width, $height, $resample);
    }

    /**
     * Resize image
     * 
     * @param int|'auto' $width  new image width,  if auto resize to width
     * @param int|'auto' $height new image height, if auto resize to height
     * @param bool       $resample   resample or resize
     * @param bool       $keepAspect keep aspect ratio
     * @param mixed      $bgColor   padding color in case of keep aspect
     * @return Image
     * @throws RuntimeException
     */
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
            throw new RuntimeException('Resize operation failed');
        $this->replaceImage($newImage);
        return $this;
    }

    /**
     * Scale image using scale2x alghoritm
     * 
     * @link http://scale2x.sourceforge.net/algorithm.html
     * @return Image
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

    /**
     * Rotate image
     * 
     * @see imagerotate
     * @param float $angle             rotate image clockwise [0-360deg]
     * @param mixed $bgColor           padding color
     * @param int   $ignoreTransparent If set and non-zero, transparent colors 
     *                                 are ignored (otherwise kept).
     * @return Image
     * @throws RuntimeException
     */
    function rotate($angle, $bgColor = null, $ignoreTransparent = 0) {
        $angle = -floatval($angle);
        $angle = ($angle < 0) ? 360 + $angle : $angle;
        $angle = $angle % 360;

        if ($angle === 0)
            return $this;

        $bgColor = $bgColor === null ? $this->getTransparentColor(true) : Color::get($bgColor);
        if (false === ($rotated = imagerotate($this->handle, $angle, $bgColor->toInt(), $ignoreTransparent)))
            throw new RuntimeException('Rotate operation failed');
        $this->replaceImage($rotated);
        return $this;
    }

    /**
     * Calculate centered box for image merge and crop
     * 
     * @param Image|int $widthOrImg width of image or image to merge with or width to crop
     * @param int       $height     height of image to merge with or height to crop
     * @return stdClass {x,y,w,h}
     */
    public function centeredBox($widthOrImg, $height = 0) {
        if ($widthOrImg instanceof Image) {
            $height     = $widthOrImg->getHeight();
            $widthOrImg = $widthOrImg->getWidth();
        }
        if ($widthOrImg <= 0 || $height <= 0)
            throw new InvalidArgumentException("Width {$widthOrImg} and height {$height} should be > 0");
        return (object) array(
                    'x' => (int) (($this->getWidth() - $widthOrImg) / 2),
                    'y' => (int) (($this->getHeight() - $height) / 2),
                    'w' => $widthOrImg,
                    'h' => $height
        );
    }

    /**
     * Crop or expand image to given size
     * 
     * @see imagecopy
     * @uses Image::centeredBox()
     * @param int|'auto' $x       x position to crop from or 'auto' to auto center horizontaly, negative to expand
     * @param int|'auto' $y       y position to crop from or 'auto' to auto center verticaly, negative to expand
     * @param int|null   $width   new or source width
     * @param int|null   $height  new or source height
     * @param mixed      $bgColor padding color
     * @return Image
     * @throws RuntimeException
     */
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
            throw new RuntimeException('Crop operation failed');
        }
        $this->replaceImage($newImage);
        return $this;
    }

    /**
     * Merge 2 images
     * 
     * @see imagecopy
     * @see imagecopymergegray
     * @see imagecopymerge
     * @param Color       $image image to merge with
     * @param int|'auto' $x      x position to start from or 'auto' to auto center horizontaly, can be negative
     * @param int|'auto' $y      y position to start from or 'auto' to auto center verticaly, can be negative
     * @param int        $pct    The two images will be merged according to pct which can range from 0 to 100. 
     * @return Image
     * @throws RuntimeException
     */
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
                    throw new RuntimeException('Merge operation failed');
                }
                return $this;
            case -1: $func = 'imagecopymergegray';
                break;
            default:
//                $cut = self::createTrueColor($pct, $height);
//                // copying relevant section from background to the cut resource 
//                imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
//                // copying relevant section from watermark to the cut resource 
//                imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
//                // insert cut resource to destination image 
//                imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
                $func = 'imagecopymerge';
                break;
        }
        if (false === $func($this->handle, $image->handle
                        , max(0, $x), max(0, $y), -min($x, 0), -min($y, 0)
                        , $image->getWidth(), $image->getHeight(), $pct)) {
            throw new RuntimeException('Merge operation failed');
        }
        return $this;
    }

    /**
     * Flip image verticaly or horizontaly 
     * 
     * @see imagecopy
     * @param bool $vertical flip image verticaly
     * @return Image
     * @throws RuntimeException
     */
    public function flip($vertical = true) {
        if (function_exists('imageflip')) {//php 5.5
            if (false === imageflip($this->handle, $vertical ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL))
                throw new RuntimeException('Image flip operation failed');
            return $this;
        }

        $width  = $this->getWidth();
        $height = $this->getHeight();
        $dest   = self::createTrueColor($width, $height);

        if ($vertical) {
            for ($i = 0; $i < $height; $i++)
                if (false === imagecopy($dest, $this->handle, 0, $i, 0, ($height - 1) - $i, $width, 1))
                    throw new RuntimeException('Vertical flip operation failed');
        } else {
            for ($i = 0; $i < $width; $i++)
                if (false === imagecopy($dest, $this->handle, $i, 0, ($width - 1) - $i, 0, 1, $height))
                    throw new RuntimeException('Horizontal flip operation failed');
        }

        $this->replaceImage($dest);
        return $this;
    }

    /**
     * Calculate boxa to trim padding from image
     * 
     * @link http://stackoverflow.com/questions/1669683/crop-whitespace-from-image-in-php
     * @param mixed $color color of padding to trim or id -1 color of pixel at [0,0]
     * @return stdClass  {l,t,r,b,w,h}
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

    /**
     * Trim image padding
     * 
     * @uses Image::trimmedBox()
     * @uses Image::crop()
     * @param mixed $color color of padding to trim or id -1 color of pixel at [0,0]
     * @return Image
     * @throws RuntimeException
     */
    public function trim($color = -1) {
        if (($box = $this->trimmedBox($color)) === false)
            throw new RuntimeException('Image would be blanked after trim');
        return $this->crop($box->l, $box->t, $box->w, $box->h);
    }

    /**
     * Compare 2 images for diffirences
     * 
     * @param Image      $image     image to compare with, must have the same dimensions
     * @param array      &$info     comparsion result info
     * @param bool|Image $diffImage return image diffirence, or not of false
     * @return Image
     * @throws InvalidArgumentException
     */
    public function compare(Image $image, array &$info = null, $diffImage = true) {
        $width  = $this->getWidth();
        $height = $this->getHeight();
        if ($width !== $image->getWidth() || $height !== $image->getHeight())
            throw new InvalidArgumentException(
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

    /**
     * Calculate histogram of image
     * 
     * @return array number of pixels with specified color
     */
    public function histogram() {
        for ($y = 0, $width = $this->getWidth(), $height = $this->getHeight(); $y < $height; ++$y)
            for ($x = 0; $x < $width; ++$x) {
                $c          = imagecolorat($this->handle, $x, $y);
                (isset($colors[$c]) && ++$colors[$c]) || ($colors[$c] = 1);
            }
        ksort($colors);
        return $colors;
    }

    /**
     * Replace image
     * 
     * @param Image|resource $newImage image to replace with or image resource handle
     * @return Image
     * @throws ErrorException
     */
    public function replaceImage($newImage) {
        if ($newImage instanceof Image)
            $newImage = $newImage->getHandle(); //copyAsTrueColorGDImage() ?

        if (!self::isValidImageHandle($newImage))
            throw new ErrorException("Invalid image handle");
        imagedestroy($this->handle);
        $this->handle = $newImage;
        $this->updateCanvas();
        return $this;
    }

    /**
     * Get image canvas
     * 
     * @return Canvas
     */
    public function getCanvas() {
        if ($this->canvas === null)
            $this->canvas = new Canvas($this->handle, false);
        return $this->canvas;
    }

    /**
     * Update canvas handle
     * 
     * @return Image
     */
    public function updateCanvas() {
        if (isset($this->canvas))
            $this->canvas->updateHandle($this->handle, false);
        return $this;
    }

    /**
     * Make a copy of image
     * 
     * @param bool $preferAlphaChannel prefer alpha channel over single transparent color
     * @return resource clone image handle
     * @throws RuntimeException
     */
    public function copyAsTrueColorGDImage($preferAlphaChannel = true) {
        $width  = $this->getWidth();
        $height = $this->getHeight();
        $bg     = $this->getTransparentColor(true)->toInt();
        if (($bg & 0x7f000000) === 0 && $preferAlphaChannel) {
            $this->transparent = $bg; //save old transparent color
            $bg |= 0x7f000000;
        }
        $newImage = self::createTrueColor($width, $height, $bg);
        if (false === imagecopy($newImage, $this->handle, 0, 0, 0, 0, $width, $height))
            throw new RuntimeException('Copy operation failed');

        //copy and save transparency
        if ($bg & 0x7f000000) {
//            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        } else {//one color transparency
            imagesavealpha($newImage, false);
            imagecolortransparent($newImage, $bg);
        }
        return $newImage;
    }

    /**
     * Return html tags for image
     * 
     * @return string
     */
    public function html() {
        return "<img src=\"{$this->sourceFile}\" alt=\"\" />";
    }

    /**
     * Returns image stream (as data-url)
     * 
     * @uses Image::saveOrOutput()
     * @param bool|int $imageType type of image or false for the same as source (also send headers)
     * @param bool|int $quality   quality for jpg [1-100] defaults to 85 
     *                            or compression level for png [0-9] defaults to 6
     * @return string
     * @throws ErrorException
     */
    public function encode($imageType = false, $quality = false /* 85 | 6 */) {
        if ($imageType === false) {
            $imageType = $this->imageType;
        } elseif (is_string($imageType)) {
            $imageType = self::extensionToImageType($imageType);
        }
        try {
            ob_start();
            if (false === $this->saveOrOutput(null, $imageType, $quality, false))
                throw new ErrorException("Encoding image failed");
            $binary = ob_get_contents();
            ob_end_clean();
        } catch (Exception $exc) {
            ob_end_clean();
            throw $exc;
        }

        return sprintf('data:%s;base64,%s', image_type_to_mime_type($imageType), base64_encode($binary));
    }

    public function __clone() {
        $this->handle = $this->copyAsTrueColorGDImage();
        $this->canvas = null;
    }

    /**
     * Returns image stream
     *
     * @return string
     */
    public function __toString() {
        try {
            return $this->encode();
        } catch (Exception $exc) {
            return $exc->getMessage();
        }
    }

}
