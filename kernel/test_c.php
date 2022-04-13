<?php

use Src\Api;

require dirname(__DIR__) . '/index.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// API для формирования сообщения для отправки по aeron
$robotrade_api = new Api('binance', 'test_php', 'core', '1');

// нужен publisher, отправлять команды по aeron в гейт
$publisher = new AeronPublisher(GATE_PUBLISHER['channel'], GATE_PUBLISHER['stream_id']);

while (true) {

    sleep(10);

    // берет все данные из memcached
    $memcached_data = $memcached->getMulti($memcached->getAllKeys());

    foreach ($memcached_data as $key => $memcached_datum) {

        if ($memcached_datum) {

            $parts = explode('_', $key);

            $exchange = $parts[0];
            $action = $parts[1];
            $value = $parts[2] ?? null;

            if  ($action == 'orderbook' && $value) {

                $price = $memcached_datum['bids'][0][0] * 0.9;

                $amount = 0.00055;

                $publisher->offer(
                    $robotrade_api->createOrder(
                        $memcached_datum['symbol'],
                        'limit',
                        'buy',
                        $amount,
                        $price,
                        'test gate for created order'
                    )
                );

                echo '[OK] Send Gate to create order. Price: ' . $price .
                    ' Amount: ' . $amount .
                    ' Symbol: ' . $memcached_datum['symbol'] .
                    ' Side: ' . 'buy' .
                    ' Type: ' . 'limit' .
                    PHP_EOL;

            } elseif ($action == 'order' && $value) {

                $publisher->offer(
                    $robotrade_api->cancelOrder(
                        $memcached_datum['id'],
                        $memcached_datum['symbol'],
                        'test gate for cancel order'
                    )
                );

                echo '[OK] Send Gate to cancel order. Id: ' . $memcached_datum['id'] .
                    ' Symbol: ' . $memcached_datum['symbol'] .
                    PHP_EOL;

            } else {

                echo '[WARNING] data undefined. Key: ' . $key .
                    ' Action: ' . $action .
                    PHP_EOL;

            }

        }

    }

    print_r(array_keys($memcached_data)) . PHP_EOL;

}
