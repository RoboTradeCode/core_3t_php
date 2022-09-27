<?php

namespace Src;

class TimeV2
{
    private static array $start;
    private static float $lifetime = 10;

    public static function fixTimeUntil(float $seconds, string $prefix): void
    {
        self::$start[$prefix] = self::$start[$prefix] ?? (microtime(true) + $seconds);
    }

    public static function up(float $seconds, string $prefix, bool $first = false): bool
    {
        if (!isset(self::$start[$prefix])) {
            self::fixTimeUntil($seconds, $prefix);

            if ($first) return true;
        }

        $now = microtime(true);

        if ($now >= self::$start[$prefix]) {
            foreach (self::$start as $pr => $item)
                if ($now >= $item + self::$lifetime)
                    unset(self::$start[$pr]);

            if ($first) return false;

            return true;
        }

        return false;
    }
}