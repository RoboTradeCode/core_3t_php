<?php

use robotrade\Api;
use Src\Aeron;
use Aeron\Publisher;
use Src\Gate;

require dirname(__DIR__, 2) . '/index.php';
require dirname(__DIR__, 2) . '/config/common_config.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$common_config = CORES['test_order_create_cancel'];

// получаем конфиг от конфигуратора
$config = $common_config['config'];

// API для формирования сообщения для отправки по aeron
$robotrade_api = new Api($common_config['exchange'], $common_config['algorithm'], $common_config['node'], $common_config['instance']);

// нужен publisher, отправлять команды по aeron в гейт
Aeron::checkConnection(
    $publisher = new Publisher($config['aeron']['publishers']['gate']['channel'], $config['aeron']['publishers']['gate']['stream_id'])
);

// класс для работы с гейтом
$gate = new Gate($publisher, $robotrade_api, $common_config['gate_sleep'] ?? 0);

// При запуске ядра отправляет запрос к гейту на отмену всех ордеров и получение баланса
$gate->cancelAllOrders()->getBalances(array_column($config['assets_labels'], 'common'))->send();

while (true) {

    sleep($common_config['sleep']);

    $keys = [
        $common_config['exchange'] . '_orderbook_' . $common_config['symbol'],
        $common_config['exchange'] . '_balances',
        $common_config['exchange'] . '_orders',
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

                if ($value == $common_config['symbol']) {

                    $price = $memcached_datum['bids'][0][0] * 0.9;

                    $publisher->offer(
                        $robotrade_api->createOrder(
                            $memcached_datum['symbol'],
                            'limit',
                            'buy',
                            $common_config['amount'],
                            $price,
                            'test gate for created order'
                        )
                    );

                    echo '[OK] Send Gate to create order. Price: ' . $price .
                        ' Amount: ' . $common_config['amount'] .
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

                print_r($memcached_datum);

                echo '[OK] Balances get from memcached: ' .  PHP_EOL;

            } else {

                echo '[WARNING] data undefined. Key: ' . $key .
                    ' Action: ' . $action .
                    PHP_EOL;

            }

        }

    }

    print_r(array_keys($memcached_data));

}
