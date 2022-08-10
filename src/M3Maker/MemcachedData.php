<?php

namespace Src\M3Maker;

class MemcachedData
{

    private array $exchanges;
    private array $markets;
    private int $expired_orderbook_time;
    public array $keys;

    public function __construct(array $exchanges, array $markets, int $expired_orderbook_time)
    {

        $this->exchanges = $exchanges;
        $this->markets = $markets;
        $this->expired_orderbook_time = $expired_orderbook_time;
        $this->keys = $this->getAllMemcachedKeys();

    }

    public function reformatAndSeparateData(array $memcached_data): array
    {

        $microtime = microtime(true);

        foreach ($memcached_data as $key => $data) {

            if (isset($data)) {

                $parts = explode('_', $key);

                $exchange = $parts[0];
                $action = $parts[1];
                $value = $parts[2] ?? null;

                if ($action == 'balances') {

                    $balances[$exchange] = $data;

                } elseif ($action == 'orderbook' && $value) {

                    if (
                        ($microtime - $data['core_timestamp']) <= $this->expired_orderbook_time / 1000000
                    ) {

                        $orderbooks[$value][$exchange] = $data;

                    }

                } elseif ($action == 'orders') {

                    $orders[$exchange] = $data;

                } else {

                    $undefined[$key] = $data;

                }

            }

        }

        return [
            'balances' => $balances ?? [],
            'orderbooks' => $orderbooks ?? [],
            'orders' => $orders ?? [],
            'undefined' => $undefined ?? [],
        ];

    }

    private function getAllMemcachedKeys(): array
    {

        $keys = [];

        foreach ($this->exchanges as  $exchange)
            $keys = array_merge(
                $keys,
                preg_filter(
                    '/^/',
                    $exchange . '_orderbook_',
                    array_column($this->markets[$exchange], 'common_symbol')
                ),
                [$exchange . '_balances'], // добавить еще к массиву ключ баланса
                [$exchange . '_orders']
            );

        return $keys;

    }

}