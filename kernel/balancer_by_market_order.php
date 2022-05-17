<?php

use robotrade\Api;
use Src\Configurator;
use Src\Core;
use Src\Cross3T;

require dirname(__DIR__) . '/index.php';
require dirname(__DIR__) . '/config/aeron_config.php';

// подключение к memcached
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// очистить все, что есть в memcached
$memcached->flush();

$config = DEBUG_HTML_VISION ? CONFIG : (new Configurator())->getConfig(EXCHANGE, INSTANCE);

// API для формирования сообщения для отправки по aeron
$robotrade_api = new Api(EXCHANGE, ALGORITHM, NODE, INSTANCE);

// нужен publisher, отправлять команды по aeron в гейт
$publisher = new AeronPublisher($config['aeron']['publishers']['gate']['channel'], $config['aeron']['publishers']['gate']['stream_id']);

// При запуске ядра отправляет запрос к гейту на отмену всех ордеров и получение баланса
(new Core($publisher, $robotrade_api))->cancelAllOrders()->getBalances(array_column($config['assets_labels'], 'common'))->send();

// создаем класс cross 3t
$cross_3t = new Cross3T($config);

// если есть все необходимые данные
do {
    
    sleep(1);

    $do = true;

    // отформировать и отделить все данные, полученные из memcached
    $all_data = $cross_3t->reformatAndSeparateData($memcached->getMulti($cross_3t->getAllMemcachedKeys()) ?? []);

    // балансы, ордербуки и неизвестные данные
    $balances = $all_data['balances'];

    $orderbooks = $all_data['orderbooks'];

    if (!empty($balances[EXCHANGE])) {

        foreach ($config['assets_labels'] as $assets_label) {

            if (!isset($orderbooks[$assets_label['common'] . '/USDT'][EXCHANGE]) && $assets_label['common'] != 'USDT') {

                $do = true;

                echo 'No pair in memcached: ' . $assets_label['common'] . '/USDT' . PHP_EOL;

                break;

            }

            $do = false;

        }

        echo 'Try get rates from memcached orderbooks' . PHP_EOL;

    } else {

        echo 'Try get data from memcached' . PHP_EOL;

    }
    
} while($do);

foreach ($config['assets_labels'] as $assets_label) {

    if ($balances[EXCHANGE][$assets_label['common']]['free'] > 0 && $assets_label['common'] != 'USDT') {

        $publisher->offer(
            $robotrade_api->createOrder(
                $assets_label['common'] . '/USDT',
                'market',
                'sell',
                $balances[EXCHANGE][$assets_label['common']]['free'],
                0,
                'Create Balancer order'
            )
        );

        echo 'Create Balancer order Pair: ' . $assets_label['common'] . '/USDT' . PHP_EOL;

    }

}

// очистить все, что есть в memcached
$memcached->flush();

unset($balances);

// При запуске ядра отправляет запрос к гейту на отмену всех ордеров и получение баланса
(new Core($publisher, $robotrade_api))->getBalances(array_column($config['assets_labels'], 'common'))->send();

// если есть все необходимые данные
do {

    sleep(1);

    $do = true;

    // отформировать и отделить все данные, полученные из memcached
    $all_data = $cross_3t->reformatAndSeparateData($memcached->getMulti($cross_3t->getAllMemcachedKeys()) ?? []);

    // балансы
    $balances = $all_data['balances'];

    echo 'Try get balances from memcached' . PHP_EOL;

} while(empty($balances[EXCHANGE]));

$sum_usdt = $balances[EXCHANGE]['USDT']['free'] / count($config['assets_labels']);

foreach ($config['assets_labels'] as $assets_label) {

    $precisions = '';

    foreach ($config['assets_labels']['markets'] as $market) {
        if ($market['common_symbol'] == $assets_label['common'] . '/USDT' && $assets_label['common'] != 'USDT') {
            $precisions = $market;
            break;
        }
    }

    if (!empty($precisions)) {

        $publisher->offer(
            $robotrade_api->createOrder(
                $assets_label['common'] . '/USDT',
                'market',
                'buy',
                $config['assets_labels']['markets']['amount_increment'] * floor(($sum_usdt / $orderbooks[$assets_label['common'] . '/USDT'][EXCHANGE]['bids'][0][0]) / $config['assets_labels']['markets']['amount_increment']),
                0,
                'Create Balancer order'
            )
        );

        echo 'Create Balancer order Pair: ' . $assets_label['common'] . '/USDT' . PHP_EOL;

    } else {

        echo 'Empty precision Pair: ' . $assets_label['common'] . '/USDT' . PHP_EOL;

    }

}
