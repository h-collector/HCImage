<?php

namespace HC\Helper;

use HC\Image;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use SplFileObject;
use SplObjectStorage;

/**
 * Description of Cache
 *
 * Helper class, simple cache
 *
 * @package    HC
 * @subpackage Helper
 * 
 * @author  h-collector <githcoll@gmail.com>
 * @link    http://hcoll.onuse.pl/projects/view/HCImage
 * @license GNU LGPL (http://www.gnu.org/copyleft/lesser.html)
 * 
 */
class Cache {

    protected static
    /** @var SplObjectStorage */ $hashes    = null;
    private
    /** @var array            */ $accessors = array();

    /**
     * 
     * @param Closure $cacheGet string function(string $hash){..}
     * @param Closure $cacheSet void   function(string $hash, string $serialized){..}
     */
    public function __construct(Closure $cacheGet, Closure $cacheSet) {
        $this->accessors = array(
            'cacheGet' => $cacheGet,
            'cacheSet' => $cacheSet
        );
    }

    /**
     * 
     * @param Closure $closure Image function(){..}
     * @return Image
     */
    public function run(Closure $closure) {
        return self::cache($closure, $this->accessors['cacheGet'], $this->accessors['cacheSet']);
    }

    /**
     * 
     * @param int $num indexof closure to run or last if null
     * @return Image
     */
    public function rerun($num = null) {
        if (self::$hashes && self::$hashes->count() >= $num) {
            foreach (self::$hashes as $id => $closure)
                if ($id === $num)
                    return $this->run($closure);
            return $this->run($closure);
        }
        throw new InvalidArgumentException('invalid argument');
    }

    public static function compressCode($code) {
        return preg_replace(array(
            '@//.*?\n@', '@/\*[\s\S]*?\*/@', '@[ \f\r\t\v\xa0]+@', '@\s*\n+@', '@^\s+@m', '@\s*$@m', '@ ->@'
                ), array(
            '', '', ' ', "\n", '', '', '->'
                ), $code);
    }

    /**
     * Get psedo closure hash
     * 
     * @param Closure $closure
     * @return type
     */
    public static function closureHash(Closure $closure) {
        if (self::$hashes === null)
            self::$hashes = new SplObjectStorage();

        if (!isset(self::$hashes[$closure])) {
            $ref     = new ReflectionFunction($closure);
            $file    = new SplFileObject($ref->getFileName());
            $file->seek($ref->getStartLine() - 1);
            $content = '';
            while ($file->key() < $ref->getEndLine()) {
                $content .= $file->current();
                $file->next();
            }
            $begin = strpos($content, 'function(');
            $code  = substr($content, $begin, strrpos($content, '}') - $begin + 1);
            self::$hashes[$closure] = md5(json_encode(array(
                self::compressCode($code),
                $ref->getStaticVariables()
            )));
        }
        return self::$hashes[$closure];
    }

    /**
     * 
     * @param Closure $closure  Image  function(){..}
     * @param Closure $cacheGet string function(string $hash){..}
     * @param Closure $cacheSet void   function(string $hash, string $serialized){..}
     * @return Image|mixed
     */
    public static function cache(Closure $closure, Closure $cacheGet, Closure $cacheSet) {
        $hash   = self::closureHash($closure);
        $cached = $cacheGet($hash);
        $image  = is_string($cached) ? unserialize($cached) : $cached;
        if (false == $image) {
            $image = $closure();
            $cacheSet($hash, serialize($image));
        }
        return $image;
    }

}
