<?php

use Src\Aeron;

require dirname(__DIR__) . '/index.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

function handler(string $message)
{

    global $memcached;

    // если данные пришли
    if ($data = Aeron::messageDecode($message)) {

        // если event как data, а node как gate
        if ($data['event'] == 'data' && $data['node'] == 'gate' && isset($data['data'])) {

            if ($data['action'] == 'orderbook') {

                $key = $data['exchange'] . '_' . $data['action'] . '_' . $data['data']['symbol'];

            } elseif ($data['action'] == 'order_created') {

                $key = $data['exchange'] . '_' . $data['action'] . '_' . $data['data']['id'];

            } else {

                $key = $data['exchange'] . '_' . $data['action'];

            }

            // записать в memcached
            $memcached->set(
                $key,
                $data['data']
            );

        } else {

            echo '[ERROR] data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

        }

    }

}

// subscriber подключение
$subscriber = new AeronSubscriber('handler', GATE_SUBSCRIBER['channel'], GATE_SUBSCRIBER['stream_id']);

while (true) {

    $subscriber->poll();

    sleep(1);

}