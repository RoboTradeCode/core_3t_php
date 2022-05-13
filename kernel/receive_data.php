<?php

use Src\Aeron;
use Src\DiscreteTime;
use Src\Log;

require dirname(__DIR__) . '/index.php';
require dirname(__DIR__) . '/config/aeron_config.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$discrete_time = new DiscreteTime();

$log = new Log(EXCHANGE, ALGORITHM, NODE, INSTANCE);

$i = 0;

// нужен publisher, отправлять логи на сервер логов
$publisher = new AeronPublisher(LOG_PUBLISHER['channel'], LOG_PUBLISHER['stream_id']);

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
$subscriber_orderbooks = new AeronSubscriber('handler_orderbooks', GATE_SUBSCRIBERS_ORDERBOOKS['channel'], GATE_SUBSCRIBERS_ORDERBOOKS['stream_id']);

$subscriber_balances = new AeronSubscriber('handler_balances', GATE_SUBSCRIBERS_BALANCES['channel'], GATE_SUBSCRIBERS_BALANCES['stream_id']);

while (true) {

    usleep(SLEEP);

    $subscriber_orderbooks->poll();

    $subscriber_balances->poll();

    if ($discrete_time->proof()) {

        $publisher->offer($log->sendWorkCore($i));

        $i++;

    }


}