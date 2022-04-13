<?php

use Src\Api;

require dirname(__DIR__) . '/vendor/autoload.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// API для формирования сообщения для отправки по aeron
$robotrade_api = new Api('binance', 'test_php', 'core', '1');

// нужен publisher, отправлять команды по aeron в гейт
$publisher = new AeronPublisher('aeron:ipc');

while (true) {

    sleep(10);

    // берет все данные из memcached
    $memcached_data = $memcached->getMulti($memcached->getAllKeys());

    $memcached_data = [
        'binance_orderbook_BTC/USDT' => [
            'bids' => [
                [45793.94, 0.007352],
                [45793.93, 0.972659],
                [45793.91, 2.140652],
                [45793.77, 0.000436],
                [45793.76, 0.0261],
            ],
            'asks' => [
                [46286.36, 0.26865],
                [46286.37, 0.102385],
                [46286.38, 0.018932],
                [46354.34, 0.060668],
                [46399.99, 1.503934],
            ],
            'symbol' => 'BTC/USDT',
            'timestamp' => 1649070863000,
        ],
        'binance_order_1212412' => [
            'id' => 1212412,
            'timestamp' => 1644489501321977,
            'status' => 'open',
            'symbol' => 'BTC/USDT',
            'type' => 'limit',
            'side' => 'sell',
            'price' => 40400.34,
            'amount' => 0.00124,
            'filled' => 0,
        ],
    ];

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
