<?php

namespace Src;

class Time
{

    private static float $start;

    public static function fixTime(): void
    {

        self::$start = self::$start ?? microtime(true);

    }

    public static function timeUp(float $seconds): bool
    {

        if (!isset(self::$start))
            self::fixTime();

        if (microtime(true) >= self::$start + $seconds)
            return true;

        return false;

    }

}