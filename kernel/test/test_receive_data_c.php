<?php

use Src\Aeron;
use Aeron\Subscriber;
use Src\DiscreteTime;

require dirname(__DIR__, 2) . '/index.php';
require dirname(__DIR__, 2) . '/config/common_config.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$balances = [];

$common_config = CORES['test_receive_data_c'];

// получаем конфиг от конфигуратора
$config = $common_config['config'];

$discrete_time = new DiscreteTime();

function handler_orderbooks(string $message): void
{

    global $memcached, $discrete_time;

    // если данные пришли
    if ($data = Aeron::messageDecode($message)) {

        // если event как data, а node как gate
        if ($data['event'] == 'data' && $data['node'] == 'gate' && $data['action'] == 'orderbook' && isset($data['data'])) {

            $data['data']['core_timestamp'] = microtime(true);

            // записать в memcached
            $memcached->set(
                $data['exchange'] . '_' . $data['action'] . '_' . $data['data']['symbol'],
                $data['data']
            );

            if ($discrete_time->proof()) {

                echo '[' . date('Y-m-d H:i:s') . ']  OrderBook Ok: ' . $data['exchange'] . '_' . $data['action'] . '_' . $data['data']['symbol']  . PHP_EOL;

            }

        } else {

            echo '[ERROR] handler_orderbooks Data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

        }

    }

}

function handler_balances(string $message): void
{

    global $memcached, $balances;

    // если данные пришли
    if ($data = Aeron::messageDecode($message)) {

        // если event как data, а node как gate
        if ($data['event'] == 'data' && $data['node'] == 'gate' && isset($data['data'])) {

            if (empty($balances)) {

                $balances[$data['exchange']] = $data['data'];

            } else {

                foreach ($data['data'] as $asset => $datum) {

                    $balances[$data['exchange']][$asset] = $datum;

                }

            }

            // записать в memcached
            $memcached->set(
                $data['exchange'] . '_' . $data['action'],
                $balances[$data['exchange']]
            );

            echo '[OK] Data saved. Node: ' . $data['node'] . ' Action: ' . $data['action'] . PHP_EOL;

        } else {

            echo '[ERROR] handler_balances Data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

        }

    }

}

function handler_orders(string $message): void
{

    global $memcached;

    // если данные пришли
    if ($data = Aeron::messageDecode($message)) {

        // если event как data, а node как gate
        if ($data['event'] == 'data' && $data['node'] == 'gate' && $data['action'] == 'order_created' && isset($data['data'])) {

            // записать в memcached
            $key = $data['exchange'] . '_orders';

            $orders = $memcached->get($key);

            $orders[$data['data']['id']] = $data['data'];

            $memcached->set(
                $key,
                $orders
            );

            echo '[OK] Data Order saved. Node: ' . $data['node'] . ' Action: ' . $data['action'] . PHP_EOL;

        } else {

            print_r($message); echo PHP_EOL;

            echo '[ERROR] handler_orders Data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

        }

    }

}

// subscribers подключение
$subscriber_orderbooks = new Subscriber('handler_orderbooks', $config['aeron']['subscribers']['orderbooks']['channel'], $config['aeron']['subscribers']['orderbooks']['stream_id']);

foreach ($config['aeron']['subscribers']['orderbooks']['destinations'] as $destination) {
    $subscriber_orderbooks->addDestination($destination);
}

$subscriber_balances = new Subscriber('handler_balances', $config['aeron']['subscribers']['balance']['channel'], $config['aeron']['subscribers']['balance']['stream_id']);

foreach ($config['aeron']['subscribers']['balance']['destinations'] as $destination) {
    $subscriber_orderbooks->addDestination($destination);
}

$subscriber_orders = new Subscriber('handler_orders', $config['aeron']['subscribers']['orders']['channel'], $config['aeron']['subscribers']['orders']['stream_id']);

foreach ($config['aeron']['subscribers']['orders']['destinations'] as $destination) {
    $subscriber_orderbooks->addDestination($destination);
}

while (true) {

    usleep($common_config['sleep']);

    $subscriber_orderbooks->poll();

    $subscriber_balances->poll();

    $subscriber_orders->poll();

}
