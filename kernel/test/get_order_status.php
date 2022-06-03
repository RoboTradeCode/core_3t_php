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

// очистить все, что есть в memcached
$memcached->flush();

$common_config = CORES['test_get_order_status'];

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

// получить статус ордера
$gate->getOrderStatus($common_config['order_id'], $common_config['symbol']);

// если есть все необходимые данные
do {

    sleep(1);

    $do = true;

    $memcached_data = $memcached->getMulti([$common_config['exchange'] . '_orders']);

    foreach ($memcached_data as $key => $memcached_datum) {

        if ($memcached_datum) {

            $parts = explode('_', $key);

            $exchange = $parts[0];
            $action = $parts[1];
            $value = $parts[2] ?? null;

            if ($action == 'orders') {

                foreach ($memcached_datum as $order) {

                    if ($order['id'] == $common_config['order_id']) {

                        print_r($order);
                        echo PHP_EOL;

                        $do = false;

                        break;

                    }

                }

            } else {

                echo '[' . date('Y-m-d H:i:s') . '] [WARNING] data undefined. Key: ' . $key . ' Action: ' . $action . PHP_EOL;

            }

        }

    }

    if ($memcached_data) {

        echo '[' . date('Y-m-d H:i:s') . '] Try get rates from memcached orderbooks' . PHP_EOL;

    } else {

        echo '[' . date('Y-m-d H:i:s') . '] Try get data from memcached' . PHP_EOL;

    }

} while($do);
