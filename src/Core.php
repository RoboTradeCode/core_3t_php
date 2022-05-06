<?php

namespace Src;

use Memcached;
use AeronPublisher;
use robotrade\Api;

class Core
{

    /**
     * Отменяет все ордера при запуске ядра
     *
     * @param string $key ключ EXCHANEG . '_orders'
     * @param Memcached $memcached Memcached object
     * @param AeronPublisher $publisher Gate AeronPublisher
     * @param Api $robotrade_api Api create message to send command to Gate
     * @return void
     */
    public function cancelAllOrders(string $key, Memcached $memcached, AeronPublisher $publisher, Api $robotrade_api): void
    {

        if ($memcached_data = $memcached->get($key)) {

            $parts = explode('_', $key);

            $action = $parts[1];

            if ($action == 'orders') {

                $order_ids = array_column($memcached_data, 'id');

                $publisher->offer(
                    $robotrade_api->cancelOrders(
                        $order_ids,
                        'test gate for cancel order'
                    )
                );

                echo '[OK] Send Gate to cancel all orders' . PHP_EOL;

                $memcached->delete($key);

            } else {

                echo '[OK] No open orders' . PHP_EOL;

            }

        }

    }

}