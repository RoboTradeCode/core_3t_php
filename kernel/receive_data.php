<?php

use Src\Aeron;
use Src\Configurator;
use Src\DiscreteTime;
use Src\Log;
use Aeron\Publisher;
use Aeron\Subscriber;

require dirname(__DIR__) . '/index.php';
require dirname(__DIR__) . '/config/common_config.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$balances = [];

$common_config = CORES['receive_data'];

// получаем конфиг от конфигуратора
$config = $common_config['debug'] ? $common_config['config'] : Configurator::getConfig($common_config['exchange'], $common_config['instance']);

// Нужные классы для отправки данных на лог сервер
if ($common_config['send_ping_to_log_server']) {

    $discrete_time = new DiscreteTime();

    $log = new Log($common_config['exchange'], $common_config['algorithm'], $common_config['node'], $common_config['instance']);

}

// нужен publisher, отправлять логи на сервер логов
$publisher = new Publisher($config['aeron']['publishers']['log']['channel'], $config['aeron']['publishers']['log']['stream_id']);
sleep(1);

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

        } else {

            echo '[ERROR] Data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

        }

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

while (true) {

    usleep($common_config['sleep']);

    $subscriber_orderbooks->poll();

    $subscriber_balances->poll();

    if ($common_config['send_ping_to_log_server'] && isset($discrete_time) && isset($log) && $discrete_time->proof()) {

        if (isset($i)) {
            $i++;
        } else {
            $i = 0;
        }

        $publisher->offer($log->sendWorkCore($i));

    }

}