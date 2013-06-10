<?php

namespace HC;

use InvalidArgumentException;

/**
 *
 * GDImage resource handler, sharable, noncopyable, destroy image if not needed
 * 
 * @package HC
 * @author  h-collector <githcoll@gmail.com>
 *          
 * @link    http://hcoll.onuse.pl/projects/view/HCImage
 * @license GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 */
class GDResource {

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
        $this->gd = ($gd instanceof GDResource) ? $gd->gd : $gd;
        if (self::$debug) echo "Replace with gd {$gd}\n";
        return $this->isValid();//throw Exception?
    }

    public function isValid() {
        return self::check($this->gd);
    }

    public static function check($gd) {
        return (is_resource($gd) && get_resource_type($gd) === 'gd');
    }

}

