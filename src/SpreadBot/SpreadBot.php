<?php

namespace Src\SpreadBot;

class SpreadBot
{

    public function incrementNumber(float $number, float $increment): float
    {
        return $increment * floor($number / $increment);
    }

    public function getMarket(array $markets, string $symbol)
    {
        return $markets[array_search($symbol, array_column($markets, 'common_symbol'))] ?? [];
    }

}