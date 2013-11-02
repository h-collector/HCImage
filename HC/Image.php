<?php

namespace HC;

use HC\Color;
use HC\Canvas;
use HC\GDResource;
use HC\Helper\Cache;

use Exception;
use ErrorException;
use RuntimeException;
use InvalidArgumentException;
use finfo;
use Closure;
use stdClass;
use ReflectionFunction;

/**
 * Image
 *
 * @package HC
 * 
 * @author  h-collector <githcoll@gmail.com>
 * @link    http://hcoll.onuse.pl/projects/view/HCImage
 * @license GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 * 
 * @uses    GDResource
 */
class Image  {

    private /** @var GDResource */ $resource    = null,
            /** @var string     */ $imageType   = IMAGETYPE_PNG, //'gd',
            /** @var string     */ $sourceFile  = '',
            /** @var Canvas     */ $canvas      = null,
            /** @var int        */ $transparent = 0;

    const /** @var string */ AUTO = 'auto'; //center merge or crop
    const /** @var string */ MAX  = 'max';  //align to bottom or right while merge or crop

    /**
     * Create new image from disk, url or binary string
     * 
     * @uses GDResource::check()
     * @see Image::load()
     * @param string|resource|Canvas|Image $image      filepath, url binary string, 
     *                                                 Image to copy, Canvas to own
     * @param bool                         $fromString create image from string
     * @param bool                         $forceAlpha try to add/preserve alpha channel
     * @throws InvalidArgumentException
     */

    public function __construct($image, $fromString = false, $forceAlpha = true) {
        if ($image instanceof Image) {//clone image
            $this->resource = new GDResource($image->copyAsTrueColorGDImage($forceAlpha));
        } elseif ($image instanceof Canvas) {
            $this->resource = $image->getGDResource();
            $this->canvas   = $image;
        } elseif (GDResource::check($image)) {
            $this->resource = new GDResource($image, false);
        } elseif ($fromString) {
            $this->loadFromString($image);
        } elseif (is_string($image)) {
            $this->loadFromFile($image);
        }
        else
            throw new InvalidArgumentException('Unknown image data');

        if (imageistruecolor($this->resource->gd)) {
            imagesavealpha($this->resource->gd, true);
        } elseif ($forceAlpha) {
            //imagepalettetotruecolor 
            $this->replaceImage($this->copyAsTrueColorGDImage($forceAlpha));
        }
    }

    public function __destruct() {
        unset($this->canvas, $this->resource);
    }

    /**
     * Make new image given dimensions
     * 
     * @param int   $width  width of new image
     * @param int   $height height of new image
     * @param mixed $bg     color of image canvas, -1 to not fill any background, null for transparent
     * @return Image
     */
    public static function create($width, $height, $bg = null) {
        $gd = self::createTrueColor($width, $height, $bg);
        return new self($gd);
    }

    /**
     * Create new gdimage resource
     * 
     * @param int   $width  width of image
     * @param int   $height height of image
     * @param mixed $bg     background color, -1 to not fill any background, null for transparent
     * @return resource handle to new image
     */
    private static function createTrueColor($width, $height, $bg = null) {
        $gd = imagecreatetruecolor((int) $width, (int) $height);
        $bg !== -1 && imagefill($gd, 0, 0, Color::index($bg));
        imagesavealpha($gd, true);
        return $gd;
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
     * Load image from binary string
     * (does not convert to 32bit truecolor)
     * 
     * @uses GDResource::check()
     * @see finfo,imagecreatefromstring()
     * @param string $data binary data
     * @throws RuntimeException
     */
    private function loadFromString($data) {
        $gd = imagecreatefromstring($data);
        if (!GDResource::check($gd))
            throw new RuntimeException("Could not create image from data");

        $this->resource = new GDResource($gd, false);

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
     * @uses GDResource::check()
     * @see exif_imagetype(),getimagesize(),imagecreatefromjpeg(),imagecreatefrompng(),imagecreatefromgif()
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
                $gd = imagecreatefromjpeg($filename);
                break;
            case IMAGETYPE_GIF:
                $gd = imagecreatefromgif($filename);
                break;
            case IMAGETYPE_PNG:
                $gd = imagecreatefrompng($filename);
                break;
            default:
                throw new RuntimeException("Image type [$this->imageType] not supported");
        }
        if (!GDResource::check($gd))
            throw new RuntimeException("Coulnd't load image [$filename]");
        
        $this->resource     = new GDResource($gd, false);
        $this->sourceFile = $filename;
    }

    /**
     * Save image to disk
     * 
     * @uses Image::saveOrOutput()
     * @see chmod()
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
            case IMAGETYPE_JPEG: $success = imagejpeg($this->resource->gd, $filename, $quality ? $quality : 85);
                break;
            case IMAGETYPE_GIF: $success = imagegif($this->resource->gd, $filename);
                break;
            case IMAGETYPE_PNG:
                $quality = $quality ? $quality : 6;
                $filters = $quality === 9 ? PNG_ALL_FILTERS : null;
                $success = imagepng($this->resource->gd, $filename, $quality, $filters);
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
        return imagesx($this->resource->gd);
    }

    /**
     * Get image height
     * 
     * @return int
     */
    public function getHeight() {
        return imagesy($this->resource->gd);
    }

    /**
     * Get image transparent color
     * 
     * @see imagecolortransparent(),Color::clear(),Color::fromInt()
     * @param bool $returnObj return color object or index
     * @return Color|int
     */
    public function getTransparentColor($returnObj = false, $noAlpha = false) {
        $color = imagecolortransparent($this->resource->gd);
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
     * @see imagecolortransparent()
     * @param mixed $color 
     * @return int
     */
    public function setTransparentColor($color = null) {
        $this->transparent = Color::index($color);
        return imagecolortransparent($this->resource->gd, $this->transparent);
    }

    /**
     * Resize image to height with aspect ratio
     * 
     * @see imagecopyresampled(),imagecopyresized()
     * @uses Image::resize()
     * @param int  $height   new height
     * @param bool $resample resample or not
     * @param bool $copy     copy and return new image, this image will not be modified
     * @return Image
     */
    public function resizeToHeight($height, $resample = true, $copy = false) {
        $ratio = $height / $this->getHeight();
        $width = $this->getWidth() * $ratio;
        return $this->resize($width, $height, $resample, false, null, $copy);
    }

    /**
     * Resize image to width with aspect ratio
     * 
     * @see imagecopyresampled(),imagecopyresized()
     * @uses Image::resize()
     * @param int  $width    new image width
     * @param bool $resample
     * @param bool $copy     copy and return new image, this image will not be modified
     * @return Image
     */
    public function resizeToWidth($width, $resample = true, $copy = false) {
        $ratio  = $width / $this->getWidth();
        $height = $this->getheight() * $ratio;
        return $this->resize($width, $height, $resample, false, null, $copy);
    }

    /**
     * Scale image
     * 
     * @see imagecopyresampled(),imagecopyresized()
     * @uses Image::resize()
     * @param int  $scale    percent
     * @param bool $resample
     * @param bool $copy     copy and return new image, this image will not be modified
     * @return Image
     */
    public function scale($scale, $resample = true, $copy = false) {
        $width  = $this->getWidth() * $scale / 100;
        $height = $this->getheight() * $scale / 100;
        return $this->resize($width, $height, $resample, false, null, $copy);
    }

    /**
     * Resize image
     * 
     * @param int|AUTO $width      new image width,  if auto resize to width
     * @param int|AUTO $height     new image height, if auto resize to height
     * @param bool     $resample   resample or resize
     * @param bool     $keepAspect keep aspect ratio
     * @param mixed    $bgColor    padding color in case of keep aspect
     * @param bool     $copy       copy and return new image, this image will not be modified
     * @return Image
     * @throws RuntimeException
     */
    public function resize($width, $height, $resample = true, $keepAspect = false, $bgColor = null, $copy = false) {
        if ($width === self::AUTO && $height === self::AUTO)
            return $this;
        if ($width === self::AUTO)
            return $this->resizeToHeight($height, $resample);
        if ($height === self::AUTO)
            return $this->resizeToWidth($width, $resample);

        $dstX = 0;
        $dstY = 0;
        $dstW = $srcW = $this->getWidth();
        $dstH = $srcH = $this->getHeight();

        if ($width instanceof Image) {
            $dstW = $width->getWidth();
        } elseif ($width > 0)
            $dstW = (int) ceil($width); //ceil?
        if ($height instanceof Image) {
            $dstH = $height->getHeight();
        } elseif ($height > 0)
            $dstH = (int) ceil($height); //ceil?

        if ($srcW === $dstW && $srcH === $dstH)
            return $copy ? clone $this : $this;

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
        if (false === $resizeFunc($newImage, $this->resource->gd, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH))
            throw new RuntimeException('Resize operation failed');
        
        return $copy ? new self($newImage) : $this->replaceImage($newImage);
    }

    /**
     * Scale image using scale2x algorithm
     *
     * @param bool $copy copy and return new image, this image will not be modified
     * @todo opimizing border situations (x=y=0, y=width-1, x=width-1, by moving ifs
     *       give at most + 5% speed boost for testing image (>30% px have same color), 
     *       same as with prebuffering, find better implementation
     * @link http://scale2x.sourceforge.net/algorithm.html
     * @return Image
     */
    public function scale2x($copy = false) {
        $gd       = $this->resource->gd;
        $width    = $this->getWidth();
        $height   = $this->getHeight();
        $bg       = $this->getTransparentColor(true);
        $newImage = self::createTrueColor($width * 2, $height * 2, $bg);
        for ($y = 0, $dy = 0, $wl = $width-1, $hl = $height-1; $y < $height; ++$y, $dy+=2)
            for ($x = 0, $dx = 0; $x < $width; ++$x, $dx+=2) {
                #A (-1,-1)  B (0,-1) C (1,-1)
                #D (-1,0)   E (0,0)  F (1,0)
                #G (-1,1)   H (0,1)  I (1,1)
                $E  = imagecolorat($gd, $x,  $y);
                if($y === 0)   $B = $E; else $B = imagecolorat($gd, $x,  $y - 1);
                if($y === $hl) $H = $E; else $H = imagecolorat($gd, $x,  $y + 1);
                if ($Z  = $B !== $H){
                    if($x === 0)   $D = $E; else $D = imagecolorat($gd, $x - 1, $y);
                    if($x === $wl) $F = $E; else $F = imagecolorat($gd, $x + 1, $y);
                }
                if ($Z && $D !== $F) {
                    imagesetpixel($newImage, $dx,     $dy,     $D === $B ? $D : $E);//$E0
                    imagesetpixel($newImage, $dx + 1, $dy,     $B === $F ? $F : $E);//$E1
                    imagesetpixel($newImage, $dx,     $dy + 1, $D === $H ? $D : $E);//$E2
                    imagesetpixel($newImage, $dx + 1, $dy + 1, $H === $F ? $F : $E);//$E3
                } else { //$E0 = $E1 = $E2 = $E3 = $E;
                    imagefilledrectangle($newImage, $dx, $dy, $dx + 1, $dy + 1, $E);
                }
            }
        return $copy ? new self($newImage) : $this->replaceImage($newImage);
    }

    /**
     * Scale image using scale3x algorithm
     * 
     * @param bool $copy copy and return new image, this image will not be modified
     * @link http://scale2x.sourceforge.net/algorithm.html
     * @return Image
     */
    public function scale3x($copy = false) {
        $gd       = $this->resource->gd;
        $width    = $this->getWidth();
        $height   = $this->getHeight();
        $bg       = $this->getTransparentColor(true);
        $newImage = self::createTrueColor($width * 3, $height * 3, $bg);
        for ($y = 0, $dy = 0, $wl = $width-1, $hl = $height-1; $y < $height; ++$y, $dy+=3)
            for ($x = 0, $dx = 0; $x < $width; ++$x, $dx+=3) {
                #A (-1,-1)  B (0,-1) C (1,-1)
                #D (-1,0)   E (0,0)  F (1,0)
                #G (-1,1)   H (0,1)  I (1,1)        
                $y1 = $y - 1;
                $y2 = $y + 1;
                $E  = imagecolorat($gd, $x,  $y);
                if($y === 0)   $B = $E; else $B = imagecolorat($gd, $x,  $y1);
                if($y === $hl) $H = $E; else $H = imagecolorat($gd, $x,  $y2);
                if ($Z  = $B !== $H){
                    $x1 = $x - 1;
                    $x2 = $x + 1;
                    if($x === 0)   $D = $E; else $D = imagecolorat($gd, $x1, $y);
                    if($x === $wl) $F = $E; else $F = imagecolorat($gd, $x2, $y);
                }
                if ($Z && $D !== $F) {
                    if($x === 0   || $y === 0)   $A = $E; else $A = imagecolorat($gd, $x1, $y1);
                    if($x === $wl || $y === 0)   $C = $E; else $C = imagecolorat($gd, $x2, $y1);
                    if($x === 0   || $y === $hl) $G = $E; else $G = imagecolorat($gd, $x1, $y2);
                    if($x === $wl || $y === $hl) $I = $E; else $I = imagecolorat($gd, $x2, $y2);
                    
                    imagesetpixel($newImage, $dx,  $dy,   $D===$B?$D:$E);//$E0
                    imagesetpixel($newImage, $dx+1,$dy,  ($D===$B&&$E!==$C)||($B===$F&&$E!==$A)?$B:$E);//$E1
                    imagesetpixel($newImage, $dx+2,$dy,   $B===$F?$F:$E);//$E2
                    imagesetpixel($newImage, $dx,  $dy+1,($D===$B&&$E!==$G)||($D===$H&&$E!==$A)?$D:$E);//$E3
                    imagesetpixel($newImage, $dx+1,$dy+1, $E);//$E4
                    imagesetpixel($newImage, $dx+2,$dy+1,($B===$F&&$E!==$I)||($H===$F&&$E!==$C)?$F:$E);//$E5
                    imagesetpixel($newImage, $dx,  $dy+2, $D===$H?$D:$E);//$E6
                    imagesetpixel($newImage, $dx+1,$dy+2,($D===$H&&$E!==$I)||($H===$F&&$E!==$G)?$H:$E);//$E7
                    imagesetpixel($newImage, $dx+2,$dy+2, $H===$F?$F:$E);//$E8
                } else {// $E0 = $E1 = $E2 = $E3 = $E4 = $E5 = $E6 = $E7 = $E8 = $E;
                    imagefilledrectangle($newImage, $dx, $dy, $dx + 2, $dy + 2, $E);
                }
            }
        return $copy ? new self($newImage) : $this->replaceImage($newImage);
    }

    /**
     * Rotate image
     * 
     * @see imagerotate()
     * @param float $angle             rotate image clockwise [0-360deg]
     * @param mixed $bgColor           padding color
     * @param int   $ignoreTransparent If set and non-zero, transparent colors 
     *                                 are ignored (otherwise kept).
     * @param bool  $copy              copy and return new image, this image will not be modified
     * @return Image
     * @throws RuntimeException
     */
    function rotate($angle, $bgColor = null, $ignoreTransparent = 0, $copy = false) {
        $angle = -floatval($angle);
        $angle = ($angle < 0) ? 360 + $angle : $angle;
        $angle = $angle % 360;

        if ($angle === 0)
            return $copy ? clone $this : $this;

        $bgColor = $bgColor === null ? $this->getTransparentColor(true) : Color::get($bgColor);
        if (false === ($newImage = imagerotate($this->resource->gd, $angle, $bgColor->toInt(), $ignoreTransparent)))
            throw new RuntimeException('Rotate operation failed');
        imagesavealpha($newImage , true);
        
        return $copy ? new self($newImage) : $this->replaceImage($newImage);
    }

    /**
     * Calculate centered box
     * 
     * @uses Image::positionBox
     * @param Image|int $widthOrImg width of image or image to merge with or width to crop
     * @param int       $height     height of image to merge with or height to crop
     */
    public function centeredBox($widthOrImg, $height = 0){
        $this->positionBox($widthOrImg, $height, self::AUTO, self::AUTO);
    }

    /**
     * Calculate box position for image merge and crop
     * 
     * @param Image|int    $widthOrImg width of image or image to merge with or width to crop
     * @param int          $height     height of image to merge with or height to crop
     * @param int|AUTO|MAX $x position horizontally (auto to center, max to right)
     * @param int|AUTO|MAX $y position vertically   (auto to middle, max to bottom)
     * @return stdClass {x,y,w,h}
     */
    public function positionBox($widthOrImg, $height = 0, $x = self::AUTO, $y = self::AUTO) {
        if ($widthOrImg instanceof Image) {
            $height     = $widthOrImg->getHeight();
            $widthOrImg = $widthOrImg->getWidth();
        }
        if ($widthOrImg <= 0 || $height <= 0)
            throw new InvalidArgumentException("Width {$widthOrImg} and height {$height} should be > 0");

        switch (true) {
            case (self::AUTO === $x): $x = (int) (($this->getWidth() - $widthOrImg) / 2); break;
            case (self::MAX === $x):  $x = $this->getWidth() - $widthOrImg; break;
        }
        switch (true) {
            case (self::AUTO === $y): $y = (int) (($this->getHeight() - $height) / 2); break;
            case (self::MAX === $y):  $y = $this->getHeight() - $height; break;
        }

        return (object) array('x' => $x, 'y' => $y, 'w' => $widthOrImg, 'h' => $height);
    }

    /**
     * Crop, expand or copy region of image to given size
     * 
     * @see imagecopy()
     * @uses Image::positionBox()
     * @param int|AUTO|MAX $x       x position to crop from, negative to expand, 
     *                              AUTO center horizontally, MAX to align right
     * @param int|AUTO|MAX $y       y position to crop from, negative to expand,
     *                              AUTO center vertically, MAX to align bottom
     * @param int|null     $width   new or source width
     * @param int|null     $height  new or source height
     * @param mixed        $bgColor new background/padding color
     * @param bool         $copy    copy and return new image, this image will not be modified
     * @return Image
     * @throws RuntimeException
     */
    public function crop($x = 0, $y = 0, $width = null, $height = null, $bgColor = null, $copy = false) {
        if ($x == 0 && $y == 0 && $width == null && $height == null && $bgColor === null)
            return $copy ? clone $this : $this;
        $srcWidth  = $this->getWidth();
        $srcHeight = $this->getHeight();
        $width     = $width ? $width : $srcWidth;
        $height    = $height ? $height : $srcHeight;
        $pos       = $this->positionBox($width, $height, $x, $y);
        $bgColor   = $bgColor === null ? $this->getTransparentColor(true) : $bgColor;
        $newImage  = self::createTrueColor($width, $height, $bgColor);
        if (false === imagecopy($newImage, $this->resource->gd
                        , -min($pos->x, 0), -min($pos->y, 0), max(0, $pos->x), max(0, $pos->y)
                        , $srcWidth, $srcHeight)) {
            throw new RuntimeException('Crop operation failed');
        }
        return $copy ? new self($newImage) : $this->replaceImage($newImage);
    }
    
    /**
     * Copy region of image
     * 
     * @uses Image::crop()
     * @param int|AUTO|MAX $x       x position to copy from, negative to use padding,
     *                              AUTO center horizontally, MAX to align right
     * @param int|AUTO|MAX $y       y position to copy from, negative to use padding,
     *                              AUTO center vertically, MAX to align bottom
     * @param int|null     $width   width of region
     * @param int|null     $height  height of region
     * @param mixed        $bgColor new background/padding color
     * @return Image
     */
    public function copyRegion($x = 0, $y = 0, $width = null, $height = null, $bgColor = null) {
        return $this->crop($x, $y, $width, $height, $bgColor, true);
    }
    
    /**
     * Expand/shrink image to given dimensions (center new image)
     * 
     * @uses Image::crop()
     * @param int|null $width   new width
     * @param int|null $height  new height
     * @param mixed    $bgColor new background/padding color
     * @param bool     $copy    copy and return new image, this image will not be modified
     * @return Image
     */
    public function expand($width = null, $height = null, $bgColor = null, $copy = false) {
        return $this->crop(self::AUTO, self::AUTO, $width, $height, $bgColor, $copy);
    }

    /**
     * Merge 2 images
     * 
     * @see imagecopy(),imagecopymergegray(),imagecopymerge()
     * @uses Image::positionBox()
     * @param Image|mixed            $image image to merge with
     * @param int|AUTO|MAX $x        x position to start from, can be negative
     *                               AUTO center horizontally, MAX to align right
     * @param int|AUTO|MAX $y        y position to start from, can be negative
     *                               AUTO center vertically, MAX to align bottom
     * @param int          $pct      The two images will be merged according to pct which can range from -100 to 100.
     *                               null - use imagecopy, -100-0 - useimagecopygray, 1-100 - use imagecopymerge
     *                               (doesn't copy alpha of merged image if pct <> null, use setTransparency w. null) 
     * @param bool         $blending turn on/off alpha blending
     * @param bool         $copy     copy and return new image, this image will not be modified
     * @return Image
     * @throws RuntimeException
     */
    public function merge($image, $x = 0, $y = 0, $pct = null, $blending = true, $copy = false) {
        $image    = $image instanceof Image ? $image : self::load($image);
        $pos      = $this->positionBox($image, 0, $x, $y);
        $dstX     = max(0, $pos->x);
        $dstY     = max(0, $pos->y);
        $srcX     = -min($pos->x, 0);
        $srcY     = -min($pos->y, 0);
        $srcImage = $image->resource->gd;
        $newImage = $copy ? $this->copyAsTrueColorGDImage() : $this->resource->gd;
        imagealphablending($newImage, $blending);
        switch (true) {
            case ($pct === null):
                if (false === imagecopy($newImage, $srcImage
                        , $dstX, $dstY, $srcX, $srcY, $pos->w, $pos->h))
                    throw new RuntimeException('Merge operation failed');
                break;
            case ($pct <= 0):
                if (false === imagecopymergegray($newImage, $srcImage
                        , $dstX, $dstY, $srcX, $srcY, $pos->w, $pos->h, -$pct))
                    throw new RuntimeException('Merge operation failed');
                break;
            default://imagecopymerge, function.imagecopymerge.html#92787?
//                $cut  = self::createTrueColor($this->getWidth(), $this->getHeight());
//                imagecopy($cut, $newImage, 0, 0, $dstX, $dstY, $pos->w, $pos->h);
//                imagecopy($cut, $srcImage, 0, 0, $srcX, $srcY, $pos->w, $pos->h);
//                imagecopymerge($newImage, $cut, $dstX, $dstY, 0, 0, $pos->w, $pos->h, $pct);
                if (false === imagecopymerge($newImage, $srcImage
                        , $dstX, $dstY, $srcX, $srcY, $pos->w, $pos->h, $pct))
                    throw new RuntimeException('Merge operation failed');
                break;
        }
        return $copy ? new self($newImage) : $this;
    }

    /**
     * Apply given image as alpha mask on current image
     * Note! Mask will be expanded or cropped to image size from the given position
     *
     * @param mixed $mask
     * @param bool  $invert
     * @param bool  $useAlpha use mask alpha channel or red channel if false
     * @param int   $x
     * @param int   $y
     * @param bool  $copy     copy and return new image, this image will not be modified
     * @return Image
     */
    public function mask($mask, $invert = false, $useAlpha = true, $x = self::AUTO, $y = self::AUTO, $copy = false) {
        $width    = $this->getWidth();
        $height   = $this->getHeight();
        $clear    = Color::index();
        $bgColor  = $useAlpha ? ($invert ? 0 : null) : ($invert ? 0x00ffff : 0xff0000);
        $mask     = $mask instanceof Image ? clone $mask : new self($mask);
        $maskGd   = $mask->crop($x, $y, $width, $height, $bgColor)->resource->gd;
        $newImage = $copy ? $this->copyAsTrueColorGDImage() : $this->resource->gd;
        //////////////////////////////////////
        imagealphablending($newImage, false);
        if ($useAlpha) {
            for ($x = 0; $x < $width; ++$x)
                for ($y = 0; $y < $height; ++$y) {
                    $rgba  = imagecolorat($newImage, $x, $y);
                    $alpha = imagecolorat($maskGd, $x, $y) & 0x7f000000; //alpha channel
                    if ($invert) $alpha ^= 0xff000000;
                    if ($alpha === 0x7f000000)
                        imagesetpixel($newImage, $x, $y, $clear);
                    elseif (($rgba & 0x7f000000) < $alpha)
                        imagesetpixel($newImage, $x, $y, ($rgba & 0x00ffffff) + $alpha);
                }
        } else {
            for ($x = 0; $x < $width; ++$x)
                for ($y = 0; $y < $height; ++$y) {
                    $rgba  = imagecolorat($newImage, $x, $y);
                    $red   = imagecolorat($maskGd, $x, $y) & 0x00ff0000; //alpha channel == red/2
                    if ($invert) $red ^= 0x00ff0000;
                    if ($red === 0x00ff0000)
                        imagesetpixel($newImage, $x, $y, $clear);
                    elseif (($rgba & 0x7f000000) < ($alpha = ($red << 7) & 0x7f000000))
                        imagesetpixel($newImage, $x, $y, ($rgba & 0x00ffffff) + $alpha);
                }
        }
        return $copy ? new self($newImage) : $this;
    }
    
    /**
     * Apply given image as overlay on current image (for black/transparent img same as mask)
     * Note! Overlay will be expanded or cropped to image size from the given position
     *
     * @param mixed $overlay
     * @param bool  $invert
     * @param bool  $useAlpha use overlay alpha channel or red channel if false
     * @param int   $x
     * @param int   $y
     * @param bool  $copy     copy and return new image, this image will not be modified
     * @return Image
     */
    public function overlay($overlay, $invert = false, $useAlpha = true, $x = self::AUTO, $y = self::AUTO, $copy = false) {
        $width    = $this->getWidth();
        $height   = $this->getHeight();
        $bgColor  = $useAlpha ? ($invert ? 0 : null) : ($invert ? 0x00ffff : 0xff0000);
        $overlay  = $overlay instanceof Image ? clone $overlay : new self($overlay);
        $maskGd   = $overlay->crop($x, $y, $width, $height, $bgColor)->resource->gd;
        $newImage = $copy ? $this->copyAsTrueColorGDImage() : $this->resource->gd;

        imagelayereffect($newImage, IMG_EFFECT_OVERLAY);
        imagecopy($newImage, $maskGd, 0, 0, 0, 0, $width, $height);
        imagelayereffect($newImage, IMG_EFFECT_NORMAL);
        return $copy ? new self($newImage) : $this;
    }

    /**
     * 
     * @param int $transparency 0-127
     * @param bool $copy
     * @return Image
     */
    public function setTransparency($transparency, $copy = false) {
        if ($transparency <= 127)
            $transparency <<= 24;
        $width  = $this->getWidth();
        $height = $this->getHeight();

        $newImage = $copy ? $this->copyAsTrueColorGDImage() : $this->resource->gd;
        imagealphablending($newImage, false);
        for ($x = 0; $x < $width; ++$x)
            for ($y = 0; $y < $height; ++$y) {
                $rgba = imagecolorat($newImage, $x, $y);
                imagesetpixel($newImage, $x, $y, ($rgba & 0x00ffffff) + $transparency);
            }
        return $copy ? new self($newImage) : $this;
    }

    /**
     * Flip image vertically or horizontally 
     * 
     * @see imagecopy()
     * @param bool $vertical flip image vertically
     * @param bool $copy     copy and return new image, this image will not be modified 
     * @return Image
     * @throws RuntimeException
     */
    public function flip($vertical = true, $copy = false) {
        if (function_exists('imageflip')) {//php 5.5
            if (false === imageflip($this->resource->gd, $vertical ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL))
                throw new RuntimeException('Image flip operation failed');
            return $this;
        }
        
        $gd       = $this->resource->gd;
        $width    = $this->getWidth();
        $height   = $this->getHeight();
        $newImage = self::createTrueColor($width, $height);

        if ($vertical) {
            for ($i = 0; $i < $height; $i++)
                if (false === imagecopy($newImage, $gd, 0, $i, 0, ($height - 1) - $i, $width, 1))
                    throw new RuntimeException('Vertical flip operation failed');
        } else {
            for ($i = 0; $i < $width; $i++)
                if (false === imagecopy($newImage, $gd, $i, 0, ($width - 1) - $i, 0, 1, $height))
                    throw new RuntimeException('Horizontal flip operation failed');
        }
        return $copy ? new self($newImage) : $this->replaceImage($newImage);
    }
    
    /**
     * Fill image with given color or image source at position x,y
     *
     * @param Image|mixed $image
     * @param int   $x
     * @param int   $y
     * @param bool  $copy
     * @return Image
     */
    public function fill($image, $x = 0, $y = 0, $copy = false) {
        $gd = $copy ? $this->copyAsTrueColorGDImage() : $this->resource->gd;
        try {//try to use or load image from source
            $image = $image instanceof Image ? $image : self::load($image, false);
            $color = IMG_COLOR_TILED;
            imagesettile($gd, $image->resource->gd);
        } catch (Exception $exc) {//if fail try to use as color
            $color = Color::index($image);
        }
        if (false === imagefill($gd, $x, $y, $color))
            throw new RuntimeException('Fill operation failed');
        return $copy ? new self($gd) : $this;
    }

    /**
     * Calculate box to trim padding from image 
     * (if color is full transparent trim regardless of rgb values)
     * 
     * @link http://stackoverflow.com/questions/1669683/crop-whitespace-from-image-in-php
     * @param mixed $color color of padding to trim or id -1 color of pixel at [0,0]
     * @return stdClass  {l,t,r,b,w,h}
     */
    public function trimmedBox($color = -1) {
        $gd      = $this->resource->gd;
        $color   = $color === -1 ? imagecolorat($gd, 0, 0) : Color::index($color);
        $width   = $this->getWidth();
        $height  = $this->getHeight();
        $bTop    = 0;
        $bLeft   = 0;
        $bBottom = $height - 1;
        $bRight  = $width - 1;
        if (($color & 0x7f000000) === 0x7f000000) {
            for (; $bTop < $height; ++$bTop)  //top
                for ($x = 0; $x < $width; ++$x)
                    if ((imagecolorat($gd, $x, $bTop) & 0x7f000000) !== 0x7f000000)
                        break 2;
            // return false when all pixels are trimmed  
            if ($bTop === $height)
                return false;
            for (; $bBottom >= 0; --$bBottom) // bottom
                for ($x = 0; $x < $width; ++$x)
                    if ((imagecolorat($gd, $x, $bBottom) & 0x7f000000) !== 0x7f000000)
                        break 2;
            for (; $bLeft < $width; ++$bLeft) // left
                for ($y = $bTop; $y <= $bBottom; ++$y)
                    if ((imagecolorat($gd, $bLeft, $y) & 0x7f000000) !== 0x7f000000)
                        break 2;
            for (; $bRight >= 0; --$bRight)   // right
                for ($y = $bTop; $y <= $bBottom; ++$y)
                    if ((imagecolorat($gd, $bRight, $y) & 0x7f000000) !== 0x7f000000)
                        break 2;
        } else {
            for (; $bTop < $height; ++$bTop)  //top
                for ($x = 0; $x < $width; ++$x)
                    if (imagecolorat($gd, $x, $bTop) !== $color)
                        break 2;
            // return false when all pixels are trimmed
            if ($bTop === $height)
                return false;
            for (; $bBottom >= 0; --$bBottom) // bottom
                for ($x = 0; $x < $width; ++$x)
                    if (imagecolorat($gd, $x, $bBottom) !== $color)
                        break 2;
            for (; $bLeft < $width; ++$bLeft) // left
                for ($y = $bTop; $y <= $bBottom; ++$y)
                    if (imagecolorat($gd, $bLeft, $y) !== $color)
                        break 2;
            for (; $bRight >= 0; --$bRight)  // right
                for ($y = $bTop; $y <= $bBottom; ++$y)
                    if (imagecolorat($gd, $bRight, $y) !== $color)
                        break 2;
        }
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
     * @param bool  $copy  copy and return new image, this image will not be modified
     * @return Image
     * @throws RuntimeException
     */
    public function trim($color = -1, $copy = false) {
        if (($box = $this->trimmedBox($color)) === false)
            throw new RuntimeException('Image would be blanked after trim');
        return $this->crop($box->l, $box->t, $box->w, $box->h, null, $copy);
    }

    /**
     * Compare 2 images for diffirences
     * 
     * @param Image|mixed $image     image to compare with, must have the same dimensions
     * @param array       &$info     comparsion result info
     * @param bool|Image  $diffImage return image diffirence, or not of false
     * @return Image
     * @throws InvalidArgumentException
     */
    public function compare($image, array &$info = null, $diffImage = true) {
        $image  = $image instanceof Image ? $image : self::load($image);
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

        $srcHandle  = $this->resource->gd;
        $destHandle = $image->resource->gd;
        if ($diffImage) {
            $diffImage  = clone $this; //Image::create($width, $height);
            $diffHandle = $diffImage->resource->gd;
            $diffCanvas = $diffImage->getCanvas();
            $diffCanvas->filter(IMG_FILTER_GRAYSCALE);
            $diffCanvas->filter(IMG_FILTER_BRIGHTNESS, 200);

            for ($y = 0; $y < $height; ++$y) {
                for ($x = 0; $x < $width; ++$x) {
                    $pix1 = imagecolorat($srcHandle, $x, $y); //$srcCanvas->colorAt($x, $y);
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
                    if (imagecolorat($srcHandle, $x, $y) !== imagecolorat($destHandle, $x, $y))
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
     * @return array number of pixels with specified color (index  for paletted)
     */
    public function histogram() {
        for ($y = 0, $w = $this->getWidth(), $h = $this->getHeight(), $gd = $this->resource->gd; $y < $h; ++$y)
            for ($x = 0; $x < $w; ++$x) {
                $c = imagecolorat($gd, $x, $y);
                if (isset($colors[$c])) {
                    ++$colors[$c];
                } else {
                    $colors[$c] = 1;
                }
            }
        ksort($colors);
        return $colors;
    }
    
    /**
     * Read Exif data from the current image
     *
     * @param  string $key
     * @return mixed
     */
    public function exif($key = null) {
        $data = exif_read_data($this->sourceFile, 'EXIF', false);
        return isset($key) ? (isset($data[$key]) ? $data[$key] : null) : $data;
    }

    /**
     * Calculate average image luminance
     * 
     * @return number avg luminance
     */
    public function getAverageLuminance() {
        $width        = $this->getWidth();
        $height       = $this->getHeight();
        $gd           = $this->resource->gd;
        $luminanceSum = 0;
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $rgb = imagecolorat($gd, $x, $y);
                //$rgb = imagecolorsforindex($gd, $rgb);
                $r   = ($rgb >> 16) & 0xFF;
                $g   = ($rgb >> 8) & 0xFF;
                $b   = ($rgb) & 0xFF;
                $luminanceSum += (0.30 * $r) + (0.59 * $g) + (0.11 * $b);
            }
        }
        return $luminanceSum / ($width * $height);
    }

    /**
     * Replace image
     * 
     * @param Image|Canvas|GDResource|resource $newImage image to replace with or image resource handle
     * @return Image
     * @throws InvalidArgumentException
     */
    public function replaceImage($newImage) {
        if ($newImage instanceof Image)
            $newImage = $newImage->resource; //copyAsTrueColorGDImage() ?
        if ($newImage instanceof Canvas)
            $newImage = $newImage->getGDResource();
        if ($newImage instanceof GDResource)
            $this->resource = $newImage;
        elseif (false === $this->resource->replace($newImage))
            throw new InvalidArgumentException('Invalid image handle');
        
        if (isset($this->canvas))
            $this->canvas->update();
        return $this;
    }

    /**
     * Get image canvas
     * 
     * @return Canvas
     */
    public function getCanvas() {
        if ($this->canvas === null)
            $this->canvas = new Canvas($this->resource);
        return $this->canvas;
    }
    
    /**
     * Use image canvas in closure
     * 
     * @param Closure $closure void function($canvas){}
     * @return Canvas
     */
    public function useCanvas(Closure $closure) {
        $closure($this->getCanvas());
        return $this;
    }
    
    /**
     * 
     * @param Closure $closure  Image  function($image){..}
     * @param Closure $cacheGet string function(string $hash){..}
     * @param Closure $cacheSet void   function(string $hash, string $serialized){..}
     * @return Image|mixed
     */
    public function useCache(Closure $closure, Closure $cacheGet, Closure $cacheSet) {
        $self = $this;
        $hash = Cache::closureHash($closure);

        $ref = new ReflectionFunction($closure);
        if ($ref->getNumberOfParameters())
            $hash .= '_' . md5(serialize($self));

        if (false == ($image = unserialize($cacheGet($hash)))) {
            $image = $closure($self);
            $cacheSet($hash, serialize($image));
        }
        return $image;
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
        if (false === imagecopy($newImage, $this->resource->gd, 0, 0, 0, 0, $width, $height))
            throw new RuntimeException('Copy operation failed');

        //copy and save transparency
        if ($bg & 0x7f000000) {//
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
     * Returns image stream (as base64 encoded data-uri)
     * 
     * @uses Image::saveOrOutput()
     * @param bool|int $imageType type of image or false for the same as source (also send headers)
     * @param bool|int $quality   quality for jpg [1-100] defaults to 85 
     *                            or compression level for png [0-9] defaults to 6
     * @return string
     * @throws RuntimeException|InvalidArgumentException
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
                throw new RuntimeException("Encoding image failed");
            $binary = ob_get_clean();
        } catch (Exception $exc) {
            ob_end_clean();
            throw $exc;
        }

        return sprintf('data:%s;base64,%s', image_type_to_mime_type($imageType), base64_encode($binary));
    }
    
    /**
     * return copy/clone
     * @return Image
     */
    public function copy() {
        return clone $this;
    }

    public function __clone() {
        $this->resource = new GDResource($this->copyAsTrueColorGDImage());
        $this->canvas = null;
    }

    /**
     * Returns base64 encoded image data stream
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
