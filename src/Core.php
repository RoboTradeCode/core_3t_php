<?php

namespace Src;

class Core
{

    private array $config;
    private array $keys;

    /**
     * @param array $config Вся конфигурация приходящяя от агента
     */
    public function __construct(array $config)
    {

        $this->config = $config;

        $this->keys = $this->getAllMemcachedKeys();

    }

    public function getFormatData($memcached): array
    {

        return $this->reformatAndSeparateData($memcached->getMulti($this->keys) ?? []);

    }

    /**
     * Формирует массив всех ключей для memcached
     *
     * @return array Возвращает все ключи для memcached
     */
    private function getAllMemcachedKeys(): array
    {

        $keys = ['config'];

        foreach ($this->config['exchanges'] as  $exchange)
            $keys = array_merge(
                $keys,
                preg_filter(
                    '/^/',
                    $exchange . '_orderbook_',
                    array_column($this->config['markets'], 'common_symbol')
                ),
                [$exchange . '_balances'], // добавить еще к массиву ключ баланса
                [$exchange . '_orders']
            );

        return $keys;

    }

    /**
     * Возвращает данные из memcached в определенном формате и отделенные по ордербукам, балансам и т. д.
     *
     * @param array $memcached_data Сырые данные, взятые напрямую из memcached
     * @return array[]
     */
    private function reformatAndSeparateData(array $memcached_data): array
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
                        ($microtime - $data['core_timestamp']) <= $this->config['expired_orderbook_time'] / 1000000
                    ) {

                        $orderbooks[$value][$exchange] = $data;

                    }

                } else {

                    $undefined[$key] = $data;

                }

            }

        }

        return [
            'balances' => $balances ?? [],
            'orderbooks' => $orderbooks ?? [],
            'undefined' => $undefined ?? [],
        ];

    }

}