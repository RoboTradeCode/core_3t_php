<?php

namespace Src\M3BestPlace;

class MemcachedData
{

    private string $main_exchange;
    private array $exchanges;
    private array $markets;
    private int $expired_orderbook_time;
    public array $keys;

    /**
     * @param array $exchanges
     * @param array $markets
     * @param int $expired_orderbook_time
     */
    public function __construct(string $main_exchange, array $exchanges, array $markets, int $expired_orderbook_time)
    {

        $this->main_exchange = $main_exchange;
        $this->exchanges = $exchanges;
        $this->markets = $markets;
        $this->expired_orderbook_time = $expired_orderbook_time;

        $this->keys = $this->getAllMemcachedKeys();

    }

    /**
     * Возвращает данные из memcached в определенном формате и отделенные по ордербукам, балансам и т. д.
     *
     * @param array $memcached_data Сырые данные, взятые напрямую из memcached
     * @return array[]
     */
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

    /**
     * Формирует массив всех ключей для memcached
     *
     * @return array Возвращает все ключи для memcached
     */
    private function getAllMemcachedKeys(): array
    {

        $keys = [];

        $common_symbols = array_column($this->markets[$this->main_exchange], 'common_symbol');

        foreach ($this->exchanges as  $exchange)
            $keys = array_merge(
                $keys,
                preg_filter(
                    '/^/',
                    $exchange . '_orderbook_',
                    $common_symbols
                ),
                [$exchange . '_balances'], // добавить еще к массиву ключ баланса
                [$exchange . '_orders']
            );

        return $keys;

    }

}