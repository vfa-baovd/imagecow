<?php
namespace Imagecow\Utils;

/**
 * Trait to manage generic color conversions
 */
trait ColorTrait
{
    /**
     * Returns a YUV weighted greyscale value
     *
     * @param  int $r
     * @param  int $g
     * @param  int $b
     * @return int
     * @see http://en.wikipedia.org/wiki/YUV
     */
    public static function rgb2bw($r, $g, $b)
    {
        return ($r * 0.299) + ($g * 0.587) + ($b * 0.114);
    }
}
