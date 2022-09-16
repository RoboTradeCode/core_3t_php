<?php

namespace Src\Signals;

class Delta
{

    public array $deltas = [];
    private int $length;

    public function __construct(int $length)
    {

        $this->length = $length;

    }

    public function calc(string $exchange, array $orderbooks): static
    {

        foreach ($orderbooks as $symbol => $orderbook) {

            if (isset($orderbook[$exchange])) {

                if (!isset($this->deltas[$symbol][$exchange]))
                    $this->deltas[$symbol][$exchange] = [];

                $first_delta = $this->first($this->deltas[$symbol][$exchange]);

                $cur_price = ($orderbook[$exchange]['asks'][0][0] + $orderbook[$exchange]['bids'][0][0]) / 2;

                array_unshift(
                    $this->deltas[$symbol][$exchange],
                    [
                        'price' => $cur_price,
                        'delta' => isset($first_delta['price']) ? $this->round(($cur_price - $first_delta['price']) / $first_delta['price'] * 100) : 0
                    ]
                );

                $this->deltas[$symbol][$exchange] = array_slice($this->deltas[$symbol][$exchange], 0, $this->length);

            }

        }

        return $this;

    }

    public function getDelta(string $symbol, string $exchange): float
    {

        return $this->deltas[$symbol][$exchange][0]['delta'] ?? 0;

    }

    public function get(string $symbol, string $exchange): array
    {

        return $this->deltas[$symbol][$exchange];

    }

    private function first(array $array)
    {

        return array_shift($array);

    }

    private function round(float $number): float
    {

        return round($number, 4);

    }

}