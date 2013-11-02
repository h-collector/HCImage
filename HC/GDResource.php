<?php

namespace HC;

use Serializable;
use InvalidArgumentException;

/**
 *
 * GD image resource handler, destroy image if not needed
 * sharable, serializable, noncopyable
 * 
 * @package HC
 * @author  h-collector <githcoll@gmail.com>
 *          
 * @link    http://hcoll.onuse.pl/projects/view/HCImage
 * @license GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 */
class GDResource implements Serializable {

    public static $debug = false;
    public /** @var resource */ $gd = null;

    /**
     * 
     * @param resource $gd
     * @param bool $check
     * @throws InvalidArgumentException
     */
    public function __construct($gd, $check = true) {
        if ($check && $gd !== null && !self::check($gd))
            throw new InvalidArgumentException("Invalid image handle: " . gettype($gd));
        $this->gd  = $gd;
        if (self::$debug) echo "New gd " . ($this->uid = uniqid()) . " : {$this->gd}\n";
    }

    public function __destruct() {
        $this->destroy();
        if (self::$debug) echo "Destruct handle {$this->uid}\n";
    }

    private function __clone() {
        
    }

    public function destroy() {
        if (self::$debug) {
            $mem1 = round(memory_get_usage() / 1024, 2);
            $ret = $this->isValid() && imagedestroy($this->gd);
            $mem2 = round(memory_get_usage() / 1024, 2);
            echo "Destroy gd {$this->gd}, mem before: {$mem1}kB, after: {$mem2}kB\n";
            return $ret;
        }
        return $this->isValid() && imagedestroy($this->gd);
    }

    public function replace($gd = null) {
        $this->destroy();
        $this->gd = $gd;
        if (self::$debug) echo "Replace with gd {$gd}\n";
        return $this->isValid();//throw Exception?
    }

    public function isValid() {
        return self::check($this->gd);
    }

    public static function check($gd) {
        return (is_resource($gd) && get_resource_type($gd) === 'gd');
    }

    public function serialize() {
        ob_start();
        imagegd2($this->gd, null, null, IMG_GD2_COMPRESSED);
        return serialize(ob_get_clean());
    }

    public function unserialize($serialized) {
        if (($binary = unserialize($serialized)) !== null) {
            $this->gd  = imagecreatefromstring($binary);
            if (!self::check($this->gd))
                throw new RuntimeException("Could not unserialize resource data");
            imagesavealpha($this->gd, true);
        }
        if (self::$debug) echo "Unserialize gd " . ($this->uid = uniqid()) . " : {$this->gd}\n";
    }

}
