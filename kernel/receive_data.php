<?php

use Src\Aeron;

require dirname(__DIR__) . '/index.php';
require dirname(__DIR__) . '/config/aeron_config.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

function handler_orderbooks(string $message): void
{

    global $memcached;

    // если данные пришли
    if ($data = Aeron::messageDecode($message)) {

        // если event как data, а node как gate
        if ($data['event'] == 'data' && $data['node'] == 'gate' && $data['action'] == 'orderbook' && isset($data['data'])) {

            // записать в memcached
            $memcached->set(
                $data['exchange'] . '_' . $data['action'] . '_' . $data['data']['symbol'],
                $data['data']
            );

        } else {

            echo '[ERROR] Data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

        }

    }

}

function handler_balances(string $message): void
{

    global $memcached;

    // если данные пришли
    if ($data = Aeron::messageDecode($message)) {

        // если event как data, а node как gate
        if ($data['event'] == 'data' && $data['node'] == 'gate' && isset($data['data'])) {

            // записать в memcached
            $memcached->set(
                $data['exchange'] . '_' . $data['action'],
                $data['data']
            );

        } else {

            echo '[ERROR] Data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

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

        } else {

            echo '[ERROR] data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

        }

    }

}

// subscribers подключение
$subscriber_orderbooks = new AeronSubscriber('handler_orderbooks', GATE_SUBSCRIBERS_ORDERBOOKS['channel'], GATE_SUBSCRIBERS_ORDERBOOKS['stream_id']);

$subscriber_balances = new AeronSubscriber('handler_balances', GATE_SUBSCRIBERS_BALANCES['channel'], GATE_SUBSCRIBERS_BALANCES['stream_id']);

$subscriber_orders = new AeronSubscriber('handler_orders', GATE_SUBSCRIBERS_ORDERS['channel'], GATE_SUBSCRIBERS_ORDERS['stream_id']);

while (true) {

    usleep(SLEEP);

    $subscriber_orderbooks->poll();

    $subscriber_balances->poll();

    $subscriber_orders->poll();

}