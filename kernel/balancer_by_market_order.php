<?php

use robotrade\Api;
use Src\Aeron;
use Src\Configurator;
use Src\Core;
use Src\Gate;
use Aeron\Publisher;
use Src\Log;
use Src\Storage;

require dirname(__DIR__) . '/index.php';
require dirname(__DIR__) . '/config/common_config.php';

// подключение к memcached
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// очистить все, что есть в memcached
$memcached->flush();

$common_config = CORES['balancer_by_market_order'];

$config = (SOURCE == 'file') ? $common_config['config'] : Configurator::getConfig($common_config['exchange'], $common_config['instance']);

$config_api = Configurator::getConfig($common_config['exchange'], $common_config['instance']);

$config['assets_labels'] = $config_api['assets_labels'];

$config['markets'] = $config_api['markets'];

// API для формирования сообщения для отправки по aeron
$robotrade_api = new Api($common_config['exchange'], $common_config['algorithm'], $common_config['node'], $common_config['instance']);

Aeron::checkConnection(
    $publisher = new Publisher($config['aeron']['publishers']['gate']['channel'], $config['aeron']['publishers']['gate']['stream_id'])
);

// класс для работы с гейтом
$gate = new Gate($publisher, $robotrade_api, $common_config['gate_sleep'] ?? 0);

// При запуске ядра отправляет запрос к гейту на отмену всех ордеров и получение баланса
$gate->cancelAllOrders()->getBalances(array_column($config['assets_labels'], 'common'))->send();

// создаем класс для работы с ядром
$core = new Core($config);

// Класс формата логов
$log = new Log($common_config['exchange'], $common_config['algorithm'], $common_config['node'], $common_config['instance']);

// если есть все необходимые данные
do {

    sleep(1);

    $do = true;

    // отформировать и отделить все данные, полученные из memcached
    $all_data = $core->getFormatData($memcached);

    // балансы, ордербуки и неизвестные данные
    $balances = $all_data['balances'];

    $orderbooks = $all_data['orderbooks'];

    if (!empty($balances[$common_config['exchange']])) {

        foreach ($config['assets_labels'] as $assets_label) {

            if (!isset($rates[$assets_label['common'] . '/USDT'][$common_config['exchange']]) && isset($orderbooks[$assets_label['common'] . '/USDT'][$common_config['exchange']]) && $assets_label['common'] != 'USDT') {

                $rates[$assets_label['common'] . '/USDT'][$common_config['exchange']] = $orderbooks[$assets_label['common'] . '/USDT'][$common_config['exchange']];

            }

        }

        foreach ($config['assets_labels'] as $assets_label) {

            if (!isset($rates[$assets_label['common'] . '/USDT'][$common_config['exchange']]) && $assets_label['common'] != 'USDT') {

                $do = true;

                echo '[' . date('Y-m-d H:i:s') . '] No pair in memcached: ' . $assets_label['common'] . '/USDT' . PHP_EOL;

                break;

            }

            $do = false;

        }

        echo '[' . date('Y-m-d H:i:s') . '] Try get rates from memcached orderbooks' . PHP_EOL;

    } else {

        echo '[' . date('Y-m-d H:i:s') . '] Try get data from memcached' . PHP_EOL;

    }

} while($do);

foreach ($config['assets_labels'] as $assets_label) {

    if ($balances[$common_config['exchange']][$assets_label['common']]['free'] > 0 && $assets_label['common'] != 'USDT') {

        $precisions = '';

        foreach ($config['markets'] as $market) {
            if ($market['common_symbol'] == $assets_label['common'] . '/USDT') {
                $precisions = $market;
                break;
            }
        }

        if (!empty($precisions)) {

            $message = $robotrade_api->createOrder(
                $robotrade_api->generateUUID() . '|Balancer',
                $assets_label['common'] . '/USDT',
                'market',
                'sell',
                $precisions['amount_increment'] * floor(($balances[$common_config['exchange']][$assets_label['common']]['free']) * 0.98 / $precisions['amount_increment']),
                $rates[$assets_label['common'] . '/USDT'][EXCHANGE]['bids'][0][0],
                'Create Balancer order'
            );

            $code = $publisher->offer($message);

            if ($code <= 0) {

                Storage::recordLog('Aeron to gate server code is: '. $code, ['$message' => $message]);

                $mes_array = json_decode($message, true);

                $log->sendErrorToLogServer($mes_array['action'] ?? 'error', $message, 'Can not create order in balancer_by_market_order.php');

            }

            print_r($message); echo PHP_EOL;

            usleep(500000);

        } else {
            echo '[' . date('Y-m-d H:i:s') . '] Empty precisions!!! ' . EXCHANGE . PHP_EOL;
        }

        echo '[' . date('Y-m-d H:i:s') . '] Create Balancer order Pair: ' . $assets_label['common'] . '/USDT' . PHP_EOL;

    }

}

print_r($balances[$common_config['exchange']]); echo PHP_EOL;

// очистить все, что есть в memcached
$memcached->flush();

unset($balances);

// При запуске ядра отправляет запрос к гейту на отмену всех ордеров и получение баланса
$gate->getBalances(array_column($config['assets_labels'], 'common'))->send();

// если есть все необходимые данные
do {

    sleep(1);

    $do = true;

    // отформировать и отделить все данные, полученные из memcached
    $all_data = $core->getFormatData($memcached);

    // балансы
    $balances = $all_data['balances'];

    echo '[' . date('Y-m-d H:i:s') . '] Try get balances from memcached' . PHP_EOL;

} while(empty($balances[$common_config['exchange']]));

$sum_usdt = $balances[$common_config['exchange']]['USDT']['free'] / count($config['assets_labels']) * 0.98;

foreach ($config['assets_labels'] as $assets_label) {

    $precisions = '';

    foreach ($config['markets'] as $market) {
        if ($market['common_symbol'] == $assets_label['common'] . '/USDT' && $assets_label['common'] != 'USDT') {
            $precisions = $market;
            break;
        }
    }

    if (!empty($precisions)) {

        $message = $robotrade_api->createOrder(
            $robotrade_api->generateUUID() . '|Balancer',
            $assets_label['common'] . '/USDT',
            'market',
            'buy',
            $precisions['amount_increment'] * floor(($sum_usdt / $rates[$assets_label['common'] . '/USDT'][$common_config['exchange']]['bids'][0][0]) / $precisions['amount_increment']),
            $rates[$assets_label['common'] . '/USDT'][$common_config['exchange']]['bids'][0][0],
            'Create Balancer order'
        );

        $code = $publisher->offer($message);

        if ($code <= 0) {

            Storage::recordLog('Aeron to gate server code is: '. $code, ['$message' => $message]);

            $mes_array = json_decode($message, true);

            $log->sendErrorToLogServer($mes_array['action'] ?? 'error', $message, 'Can not create order in balancer_by_market_order.php');

        }

        print_r($message); echo PHP_EOL;

        usleep(500000);

        echo '[' . date('Y-m-d H:i:s') . '] Create Balancer order Pair: ' . $assets_label['common'] . '/USDT' . PHP_EOL;

    } else {

        echo '[' . date('Y-m-d H:i:s') . '] Empty precision Pair: ' . $assets_label['common'] . '/USDT' . PHP_EOL;

    }

}

print_r($balances[$common_config['exchange']]); echo PHP_EOL;

// очистить все, что есть в memcached
$memcached->flush();

unset($balances);

// При запуске ядра отправляет запрос к гейту на отмену всех ордеров и получение баланса
$gate->getBalances(array_column($config['assets_labels'], 'common'))->send();

// если есть все необходимые данные
do {

    sleep(1);

    $do = true;

    // отформировать и отделить все данные, полученные из memcached
    $all_data = $core->getFormatData($memcached);

    // балансы
    $balances = $all_data['balances'];

    echo '[' . date('Y-m-d H:i:s') . '] Try get balances from memcached' . PHP_EOL;

} while(empty($balances[$common_config['exchange']]));

print_r($balances[$common_config['exchange']]);

$memcached->flush();
