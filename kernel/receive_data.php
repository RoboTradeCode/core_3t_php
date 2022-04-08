<?php

use Src\Aeron;
use Src\Test\TestAgentFormatData;
use Src\Test\TestLogFormat;

require dirname(__DIR__) . '/index.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$publisher = new AeronPublisher("aeron:ipc");

function handler_get_config(string $message)
{

    global $memcached, $core_config, $publisher;

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

            $message = '[ERROR] data broken. Node: ' . ($data['node'] ?? 'null');

            echo $message . PHP_EOL;

            $publisher->offer(
                Aeron::messageEncode(
                    (new TestLogFormat('binance'))->sendLog($message)
                )
            );

        }

    }

}

$subscriber = new AeronSubscriber('handler_get_config', 'aeron:ipc');

while (true) {

    usleep(100000);

    $subscriber->poll();

    if (!isset($core_config)) {

        do {

            usleep(1000000);

            $code = $publisher->offer(
                Aeron::messageEncode(
                    (new TestAgentFormatData('binance'))->sendAgentGetFullConfig()
                )
            );

            echo 'Try to send command get_full_config to Agent. Code: ' . $code . PHP_EOL;

        } while($code > 0);

    } else {

        echo '[OK] Can config get' . PHP_EOL;

        unset($subscriber);

        break;

    }

}

function handler(string $message)
{

    global $memcached, $publisher;

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

            $message = '[ERROR] data broken. Node: ' . ($data['node'] ?? 'null');

            echo $message . PHP_EOL;

            $publisher->offer(
                Aeron::messageEncode(
                    (new TestLogFormat('binance'))->sendLog($message)
                )
            );

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