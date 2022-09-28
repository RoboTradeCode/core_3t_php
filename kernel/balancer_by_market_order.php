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

// подключение к memcached
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// очистить все, что есть в memcached
$memcached->flush();

$common_config = Configurator::getConfigApi(dirname(__DIR__) . '/config/balancer_by_market_order.json');

$config = $common_config['configs']['core_config']['config'];

$config['assets_labels'] = $common_config['assets_labels'];

$config['markets'] = $common_config['markets'];

$common_config = $common_config['configs']['core_config'];

$base_symbol = $common_config['main_asset'] ?? 'USDT';

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

            $symbol_pair = $assets_label['common'] . '/' . $base_symbol;
            $reverse_symbol_pair = $base_symbol . '/' . $assets_label['common'];

            if (!isset($rates[$symbol_pair][$common_config['exchange']]) && isset($orderbooks[$symbol_pair][$common_config['exchange']]) && $assets_label['common'] != $base_symbol) {

                $rates[$symbol_pair][$common_config['exchange']] = $orderbooks[$symbol_pair][$common_config['exchange']];

            } elseif (!isset($rates[$reverse_symbol_pair][$common_config['exchange']]) && isset($orderbooks[$reverse_symbol_pair][$common_config['exchange']]) && $assets_label['common'] != $base_symbol) {

                $rates[$reverse_symbol_pair][$common_config['exchange']] = $orderbooks[$reverse_symbol_pair][$common_config['exchange']];

            }

        }

        foreach ($config['assets_labels'] as $assets_label) {

            $symbol_pair = $assets_label['common'] . '/' . $base_symbol;
            $reverse_symbol_pair = $base_symbol . '/' . $assets_label['common'];

            if (
                !isset($rates[$symbol_pair][$common_config['exchange']]) &&
                !isset($rates[$reverse_symbol_pair][$common_config['exchange']]) &&
                $assets_label['common'] != $base_symbol
            ) {

                $do = true;

                echo '[' . date('Y-m-d H:i:s') . '] No pair in memcached: ' . $symbol_pair . ' or: ' . $reverse_symbol_pair . PHP_EOL;

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

    if ($balances[$common_config['exchange']][$assets_label['common']]['free'] > 0 && $assets_label['common'] != $base_symbol) {

        $precisions = '';

        $reverse = !isset($rates[$assets_label['common'] . '/' . $base_symbol][$common_config['exchange']]);

        $symbol_pair = $reverse ? $base_symbol . '/' . $assets_label['common'] : $assets_label['common'] . '/' . $base_symbol ;

        foreach ($config['markets'] as $market) {
            if ($market['common_symbol'] == $symbol_pair) {
                $precisions = $market;
                break;
            }
        }

        if (!empty($precisions)) {

            $message = $robotrade_api->createOrder(
                $robotrade_api->generateUUID() . '|Balancer',
                $symbol_pair,
                'market',
                $reverse ? 'buy' : 'sell',
                $reverse
                    ? $precisions['amount_increment'] * floor(($balances[$common_config['exchange']][$assets_label['common']]['free'] / $rates[$symbol_pair][$common_config['exchange']]['bids'][0][0]) / $precisions['amount_increment'])
                    : $precisions['amount_increment'] * floor(($balances[$common_config['exchange']][$assets_label['common']]['free']) * 0.98 / $precisions['amount_increment']),
                $rates[$symbol_pair][$common_config['exchange']]['bids'][0][0],
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

            echo '[' . date('Y-m-d H:i:s') . '][ERROR] Empty precisions!!! ' . $common_config['exchange'] . PHP_EOL;

        }

        echo '[' . date('Y-m-d H:i:s') . '] Create Balancer order Pair: ' . $assets_label['common'] . '/' . $base_symbol . PHP_EOL;

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

$sum_usdt = $balances[$common_config['exchange']][$base_symbol]['free'] / count($config['assets_labels']) * 0.98;

foreach ($config['assets_labels'] as $assets_label) {

    $precisions = '';

    $reverse = !isset($rates[$assets_label['common'] . '/' . $base_symbol][$common_config['exchange']]);

    $symbol_pair = $reverse ? $base_symbol . '/' . $assets_label['common'] : $assets_label['common'] . '/' . $base_symbol ;

    foreach ($config['markets'] as $market) {
        if ($market['common_symbol'] == $symbol_pair && $assets_label['common'] != $base_symbol) {
            $precisions = $market;
            break;
        }
    }

    if (!empty($precisions)) {

        $message = $robotrade_api->createOrder(
            $robotrade_api->generateUUID() . '|Balancer',
            $symbol_pair,
            'market',
            $reverse ? 'sell' : 'buy',
            $reverse
                ? $precisions['amount_increment'] * floor($sum_usdt / $precisions['amount_increment'])
                : $precisions['amount_increment'] * floor(($sum_usdt / $rates[$symbol_pair][$common_config['exchange']]['bids'][0][0]) / $precisions['amount_increment']),
            $rates[$symbol_pair][$common_config['exchange']]['bids'][0][0],
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

        echo '[' . date('Y-m-d H:i:s') . '] Create Balancer order Pair: ' . $assets_label['common'] . '/' . $base_symbol . PHP_EOL;

    } else {

        echo '[' . date('Y-m-d H:i:s') . '] Empty precision Pair: ' . $assets_label['common'] . '/' . $base_symbol . PHP_EOL;

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
