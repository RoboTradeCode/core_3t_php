<?php

use Src\Aeron;
use Src\Configurator;
use Aeron\Publisher;
use Aeron\Subscriber;
use Src\Storage;

require dirname(__DIR__, 3) . '/index.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$balances = [];

// получаем конфиг от конфигуратора
$config = Configurator::getCoreConfigApi(dirname(__DIR__, 3) . '/config/receive_data.json');

function handler_orderbooks(string $message): void
{

    global $memcached;

    // если данные пришли
    if ($data = Aeron::messageDecode($message)) {

        // если event как data, а node как gate
        if ($data['event'] == 'data' && $data['node'] == 'gate' && isset($data['data'])) {

            $data['data']['core_timestamp'] = microtime(true);

            // записать в memcached
            $memcached->set(
                $data['exchange'] . '_orderbook' . '_' . $data['data']['symbol'],
                $data['data']
            );

        } else {

            echo '[ERROR] Orderbook data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

        }

    } else {

        Storage::recordLog('Can not decode message orderbooks', ['$message' => $message]);

    }

}

function handler_balances(string $message): void
{

    global $memcached, $balances;

    // если данные пришли
    if ($data = Aeron::messageDecode($message)) {

        // если event как data, а node как gate
        if ($data['event'] == 'data' && $data['node'] == 'gate' && isset($data['data']['assets'])) {

            if (empty($balances)) {

                $balances[$data['exchange']] = $data['data']['assets'];

            } else {

                foreach ($data['data']['assets'] as $asset => $datum) {

                    $balances[$data['exchange']][$asset] = $datum;

                }

            }

            // записать в memcached
            $memcached->set(
                $data['exchange'] . '_balances',
                $balances[$data['exchange']]
            );

        } else {

            echo '[ERROR] Balances data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

        }

    } else {

        Storage::recordLog('Can not decode message balances', ['$message' => $message]);

    }

}

function handler_orders(string $message): void
{

    global $memcached;

    // если данные пришли
    if ($data = Aeron::messageDecode($message)) {

        // если event как data, а node как gate
        if ($data['event'] == 'data' && $data['node'] == 'gate' && isset($data['data'])) {

            $key = $data['exchange'] . '_orders';

            $orders = $memcached->get($key);

            foreach ($data['data'] as $datum) {

                $orders[$datum['client_order_id']] = $datum;

            }

            foreach ($orders as $k => $order) {
                if (in_array($order['status'], ['closed', 'canceled', 'expired', 'rejected'])) {
                    unset($orders[$k]);
                }
            }

            echo '[' . date('Y-m-d H:i:s') . '] Real Orders Count: ' . count($orders) . PHP_EOL;

            $memcached->set(
                $key,
                $orders
            );

        } else {

            if ($data['event'] == 'error' && $data['node'] == 'gate' && $data['action'] == 'cancel_orders' && isset($data['data'])) {

                $key = $data['exchange'] . '_orders';

                $orders = $memcached->get($key);

                foreach ($data['data'] as $datum) {

                    unset($orders[$datum['client_order_id']]);

                }

                $memcached->set(
                    $key,
                    $orders
                );

                echo PHP_EOL . 'Error Orders----------------------------------------------------------------------------------' . PHP_EOL;
                foreach ($data['data'] as $order)
                    echo '[' . date('Y-m-d H:i:s') . '] client_order_id: ' . $order['client_order_id'] . ' Symbol ' . $order['symbol'] . PHP_EOL;
                echo 'Error Orders----------------------------------------------------------------------------------' . PHP_EOL . PHP_EOL;

            } else {

                if ($data['event'] == 'error' && $data['node'] == 'gate' && $data['action'] == 'create_orders') {

                    echo '[' . date('Y-m-d H:i:s') . '] Error Create order: ' . $data['message'] . PHP_EOL;

                } else {

                    print_r($message); echo PHP_EOL;

                    echo '[ERROR] handler_orders Data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

                }

            }

        }

    } else {

        Storage::recordLog('Can not decode message orders', ['$message' => $message]);

    }

}

// subscribers подключения
$subscriber_orderbooks = new Subscriber('handler_orderbooks', $config['aeron']['subscribers']['orderbooks']['channel'], $config['aeron']['subscribers']['orderbooks']['stream_id']);

foreach ($config['aeron']['subscribers']['orderbooks']['destinations'] as $destination) {
    $subscriber_orderbooks->addDestination($destination);
}

$subscriber_balances = new Subscriber('handler_balances', $config['aeron']['subscribers']['balance']['channel'], $config['aeron']['subscribers']['balance']['stream_id']);

foreach ($config['aeron']['subscribers']['balance']['destinations'] as $destination) {
    $subscriber_balances->addDestination($destination);
}

$subscriber_orders = new Subscriber('handler_orders', $config['aeron']['subscribers']['orders']['channel'], $config['aeron']['subscribers']['orders']['stream_id']);

foreach ($config['aeron']['subscribers']['orders']['destinations'] as $destination) {
    $subscriber_orders->addDestination($destination);
}

while (true) {

    usleep($config['sleep']);

    $subscriber_orderbooks->poll();

    $subscriber_balances->poll();

    $subscriber_orders->poll();

}