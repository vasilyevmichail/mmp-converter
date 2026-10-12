<?php

declare(strict_types=1);

namespace Vasilyevmichail\MmpConverter\Util;

final class TimeUtil
{
    public const MICROSECONDS_PER_SECOND = 1_000_000;

    public static function usToSec(int $us): float
    {
        return $us / self::MICROSECONDS_PER_SECOND;
    }

    public static function secToHms(float $sec): string
    {
        $neg = $sec < 0;
        $sec = abs($sec);

        $h = (int) floor($sec / 3600);
        $m = (int) floor(($sec - $h * 3600) / 60);
        $s = $sec - $h * 3600 - $m * 60;

        return sprintf('%s%d:%02d:%06.3f', $neg ? '-' : '', $h, $m, $s);
    }

    public static function utf16leToUtf8(string $s): string
    {
        if (str_starts_with($s, "\xFF\xFE")) {
            $s = substr($s, 2);
        }

        $pos = strpos($s, "\x00\x00");
        if ($pos !== false) {
            if ($pos % 2 !== 0) {
                $pos++;
            }
            $s = substr($s, 0, $pos);
        }

        $out = @mb_convert_encoding($s, 'UTF-8', 'UTF-16LE');

        return $out === false ? '' : $out;
    }
}