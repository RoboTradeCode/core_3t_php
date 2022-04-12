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
        if ($data['event'] == 'data' && $data['node'] == 'gate') {

            // записать в memcached
            $memcached->set(
                $data['exchange'] . '_' . $data['action'] . (($data['action'] == 'orderbook') ? '_' . $data['data']['symbol'] : ''),
                $data['data']
            );

        } else {

            echo '[ERROR] data broken. Node: ' . ($data['node'] ?? 'null') . PHP_EOL;

        }

    }

}

// subscriber подключение
$subscriber = new AeronSubscriber('handler', 'aeron:udp?control-mode=manual');

$subscriber->addDestination('aeron:ipc');

while (true) {

    $subscriber->poll();

    sleep(1);

}