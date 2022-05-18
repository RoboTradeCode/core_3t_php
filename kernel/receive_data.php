<?php

use Src\Aeron;
use Src\Configurator;
use Src\DiscreteTime;
use Src\Log;

require dirname(__DIR__) . '/index.php';
require dirname(__DIR__) . '/config/aeron_config.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$balances = [];

// получаем конфиг от конфигуратора
$config = DEBUG_HTML_VISION ? CONFIG : (new Configurator())->getConfig(EXCHANGE, INSTANCE);

$discrete_time = new DiscreteTime();

$log = new Log(EXCHANGE, ALGORITHM, NODE, INSTANCE);

$i = 0;

// нужен publisher, отправлять логи на сервер логов
$publisher = new AeronPublisher($config['aeron']['publishers']['log']['channel'], $config['aeron']['publishers']['log']['stream_id']);

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

// subscribers подключение
$subscriber_orderbooks = new AeronSubscriber('handler_orderbooks', $config['aeron']['subscribers']['orderbooks']['channel'], $config['aeron']['subscribers']['orderbooks']['stream_id']);

$subscriber_balances = new AeronSubscriber('handler_balances', $config['aeron']['subscribers']['balance']['channel'], $config['aeron']['subscribers']['balance']['stream_id']);

while (true) {

    usleep(SLEEP);

    $subscriber_orderbooks->poll();

    $subscriber_balances->poll();

    if ($discrete_time->proof()) {

        $publisher->offer($log->sendWorkCore($i));

        $i++;

    }


}