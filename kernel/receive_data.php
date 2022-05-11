<?php

use Src\Aeron;
use Src\Configurator;

require dirname(__DIR__) . '/index.php';
require dirname(__DIR__) . '/config/aeron_config.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// получаем конфиг от конфигуратора
$config = (new Configurator())->getConfig(EXCHANGE, INSTANCE);

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

// subscribers подключение
$subscriber_orderbooks = new AeronSubscriber('handler_orderbooks', $config['aeron']['subscribers']['orderbooks']['channel'], $config['aeron']['subscribers']['orderbooks']['stream_id']);

$subscriber_balances = new AeronSubscriber('handler_balances', $config['aeron']['subscribers']['balance']['channel'], $config['aeron']['subscribers']['balance']['stream_id']);

while (true) {

    usleep(SLEEP);

    $subscriber_orderbooks->poll();

    $subscriber_balances->poll();

}
