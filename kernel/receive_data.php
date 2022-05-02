<?php

use Src\Aeron;

require dirname(__DIR__) . '/index.php';
require dirname(__DIR__) . '/config/aeron_config.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// публишер для агента, чтобы получить конфиг
$publisher = new AeronPublisher(AGENT_PUBLISHER['channel'], AGENT_PUBLISHER['stream_id']);

function handler_get_config(string $message): void
{

    global $memcached, $core_config;

    if ($data = Aeron::messageDecode($message)) {

        if ($data['event'] == 'config' && $data['node'] == 'configurator') {

            $core_config = $data['data']['configs']['core_config'];

            $memcached->set(
                'config',
                $core_config
            );

        } else {

            echo '[ERROR] data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

        }

    }

}

$subscriber = new AeronSubscriber('handler_get_config', AGENT_SUBSCRIBERS_BALANCES['channel'], AGENT_SUBSCRIBERS_BALANCES['stream_id']);

while (true) {

    usleep(100000);

    $subscriber->poll();

    if (!isset($core_config)) {

        do {

            usleep(1000000);

            $code = $publisher->offer(
                Aeron::messageEncode([
                    'event' => 'config',
                    'exchange' => EXCHANGE,
                    'instance' => NODE,
                    'action' => 'get_config',
                    'algo' => ALGORITHM,
                    'data' => [],
                    'timestamp' => intval(microtime(true) * 1000000)
                ])
            );

            echo '[ERROR] Try to send command get_config to Agent. Code: ' . $code . PHP_EOL;

        } while($code > 0);

    } else {

        echo '[OK] Can config get' . PHP_EOL;

        unset($subscriber);

        break;

    }

}

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

            echo '[OK] Data saved. Node: ' . $data['node'] . ' Action: ' . $data['action'] . ' Symbol: ' . $data['data']['symbol'] . PHP_EOL;

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

            echo '[OK] Data saved. Node: ' . $data['node'] . ' Action: ' . $data['action'] . PHP_EOL;

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

            echo '[OK] Data Order saved. Node: ' . $data['node'] . ' Action: ' . $data['action'] . PHP_EOL;

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

    $subscriber_orderbooks->poll();

    $subscriber_balances->poll();

    $subscriber_orders->poll();

}