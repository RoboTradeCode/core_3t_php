<?php

use Src\Aeron;
use Src\Test\TestAgentFormatData;

require dirname(__DIR__) . '/index.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

function handler_get_config(string $message)
{

    global $memcached, $core_config;

    if ($data = Aeron::messageDecode($message)) {

        if (
            $data['event'] == 'config' && $data['node'] == 'gate' &&
            $data['data']['markets'] && $data['data']['assets_labels'] && $data['data']['routes'] && $data['data']['core_config']
        ) {

            $core_config = $data['data']['core_config'];

            $memcached->set(
                'config',
                $data['data']
            );

        } else {

            echo '[ERROR] data broken. Node: ' . ($data['node'] ?? 'no') . PHP_EOL;

        }

    }

}

$subscriber = new AeronSubscriber('handler_get_config', 'aeron:ipc');

while (true) {

    usleep(100000);

    $subscriber->poll();

    if (!isset($core_config)) {

        $publisher = new AeronPublisher("aeron:ipc");

        do {

            usleep(1000000);

            $code = $publisher->offer(
                Aeron::messageEncode(
                    (new TestAgentFormatData('binance'))->sendAgentGetFullConfig()
                )
            );

            echo 'Try to send command get_full_config to Agent. Code: ' . $code . PHP_EOL;

        } while($code > 0);

        unset($publisher);

    } else {

        echo '[OK] Can config get' . PHP_EOL;

        unset($subscriber);

        break;

    }

}

function handler(string $message)
{

    global $memcached;

    if ($data = Aeron::messageDecode($message)) {

        if ($data['event'] == 'data' && $data['node'] == 'gate') {

            $memcached->set(
                $data['exchange'] . '_' . $data['action'] . (($data['action'] == 'orderbook') ? '_' . $data['data']['symbol'] : ''),
                $data['data']
            );

        } elseif (
            $data['event'] == 'config' && $data['node'] == 'agent' &&
            $data['data']['markets'] && $data['data']['assets_labels'] && $data['data']['routes']
        ) {

            $memcached->set(
                'config',
                $data['data']
            );

        } else {

            echo '[ERROR] data broken. Node: ' . ($data['node'] ?? 'no') . PHP_EOL;

        }

    }

}

$subscriber = new AeronSubscriber('handler', 'aeron:udp?control-mode=manual');

$subscriber->addDestination('aeron:ipc');

foreach ($core_config['aeron']['subscribers'] as $core_subscribers) {

    foreach ($core_subscribers['destinations'] as $destination) {

        $subscriber->addDestination($destination);

    }

}

while (true) {

    $subscriber->poll();

    usleep(10);

}