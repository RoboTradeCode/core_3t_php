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

            list(, $action) = explode('_', $key);

            if ($action == 'orders') {

                $publisher->offer(
                    $robotrade_api->cancelOrders(
                        $memcached_data,
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