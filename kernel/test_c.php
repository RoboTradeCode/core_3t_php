<?php

use Src\Api;

require dirname(__DIR__) . '/index.php';
require dirname(__DIR__) . '/config/test_aeron_config_c.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// API для формирования сообщения для отправки по aeron
$robotrade_api = new Api(EXCHANGE, ALGORITHM, 'core', '1');

// нужен publisher, отправлять команды по aeron в гейт
$publisher = new AeronPublisher(GATE_PUBLISHER['channel'], GATE_PUBLISHER['stream_id']);

while (true) {

    sleep(10);

    $keys = [
        EXCHANGE . '_orderbook_' . SYMBOL,
        EXCHANGE . '_balances',
        EXCHANGE . '_orders',
    ];

    // берет все данные из memcached
    $memcached_data = $memcached->getMulti($keys);

    foreach ($memcached_data as $key => $memcached_datum) {

        if ($memcached_datum) {

            $parts = explode('_', $key);

            $exchange = $parts[0];
            $action = $parts[1];
            $value = $parts[2] ?? null;

            if  ($action == 'orderbook' && $value) {

                if ($value == 'BTC/USDT') {

                    $price = $memcached_datum['bids'][0][0] * 0.9;

                    $publisher->offer(
                        $robotrade_api->createOrder(
                            $memcached_datum['symbol'],
                            'limit',
                            'buy',
                            AMOUNT,
                            $price,
                            'test gate for created order'
                        )
                    );

                    echo '[OK] Send Gate to create order. Price: ' . $price .
                        ' Amount: ' . AMOUNT .
                        ' Symbol: ' . $memcached_datum['symbol'] .
                        ' Side: ' . 'buy' .
                        ' Type: ' . 'limit' .
                        PHP_EOL;

                }

            } elseif ($action == 'orders') {

                foreach ($memcached_datum as $order) {

                    $publisher->offer(
                        $robotrade_api->cancelOrder(
                            $order['id'],
                            $order['symbol'],
                            'test gate for cancel order'
                        )
                    );

                    echo '[OK] Send Gate to cancel order. Id: ' . $order['id'] .
                        ' Symbol: ' . $order['symbol'] .
                        PHP_EOL;

                }

                $memcached->delete($key);

            } elseif ($action == 'balances') {

                echo '[OK] Balances get from memcached: ' .  PHP_EOL;

            } else {

                echo '[WARNING] data undefined. Key: ' . $key .
                    ' Action: ' . $action .
                    PHP_EOL;

            }

        }

    }

    print_r(array_keys($memcached_data)) . PHP_EOL;

}
