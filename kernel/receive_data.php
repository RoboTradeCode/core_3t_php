<?php

use Src\Aeron;
use Src\Configurator;
use Src\DiscreteTime;
use Src\Log;
use Aeron\Publisher;
use Aeron\Subscriber;
use Src\Storage;

require dirname(__DIR__) . '/index.php';
require dirname(__DIR__) . '/config/common_config.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$balances = [];

$common_config = CORES['receive_data'];

// получаем конфиг от конфигуратора
$config = (SOURCE == 'file') ? $common_config['config'] : Configurator::getConfig($common_config['exchange'], $common_config['instance']);

// Нужные классы для отправки данных на лог сервер
if ($common_config['send_ping_to_log_server']) {

    $discrete_time = new DiscreteTime();

    $log = new Log($common_config['exchange'], $common_config['algorithm'], $common_config['node'], $common_config['instance']);

    // нужен publisher, отправлять логи на сервер логов
    Aeron::checkConnection(
        $publisher = new Publisher($config['aeron']['publishers']['log']['channel'], $config['aeron']['publishers']['log']['stream_id'])
    );

}

$i = 0;

function handler_orderbooks(string $message): void
{

    global $memcached, $i;

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

            $i++;

        } else {

            echo '[ERROR] Orderbook data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

        }

    } else {

        Storage::recordLog('Can not decode message orderbooks', ['$message' => $message]);

    }

}

function handler_balances(string $message): void
{

    global $memcached, $balances, $i;

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

            $i++;

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

            $memcached->set(
                $key,
                $orders
            );

        } else {

            print_r($message); echo PHP_EOL;

            echo '[ERROR] handler_orders Data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

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

    usleep($common_config['sleep']);

    $subscriber_orderbooks->poll();

    $subscriber_balances->poll();

    $subscriber_orders->poll();

    if ($common_config['send_ping_to_log_server'] && isset($publisher) && isset($discrete_time) && isset($log) && $discrete_time->proof()) {

        $publisher->offer($log->sendWorkCore($i));

    }

}