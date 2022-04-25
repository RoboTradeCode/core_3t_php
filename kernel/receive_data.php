<?php

use Src\Aeron;
use Src\Api;
use Src\Test\TestAgentFormatData;

require dirname(__DIR__) . '/index.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$publisher = new AeronPublisher('aeron:ipc');

$robotrade_api = new Api('binance', 'cross_3t_php', 'core', '1');

function handler_get_config(string $message)
{

    global $memcached, $core_config, $publisher, $robotrade_api;

    if ($data = Aeron::messageDecode($message)) {

        if ($data['event'] == 'config' && $data['node'] == 'configurator') {

            $core_config = $data['data']['core_config'];

            $memcached->set(
                'config',
                $data['data']
            );

        } else {

            $message = '[ERROR] data broken. Node: ' . ($data['node'] ?? 'null');

            echo $message . PHP_EOL;

            $publisher->offer($robotrade_api->error('get_aeron_data', null, $message));

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

    global $memcached, $publisher, $robotrade_api;

    if ($data = Aeron::messageDecode($message)) {

        if ($data['event'] == 'data' && $data['node'] == 'gate' && isset($data['data'])) {

            if ($data['action'] == 'orderbook') {

                $key = $data['exchange'] . '_' . $data['action'] . '_' . $data['data']['symbol'];

            } elseif ($data['action'] == 'order' || $data['action'] == 'order_created') {

                $key = $data['exchange'] . '_order';

                $orders = $memcached->get($key);

                $orders[$data['data']['id']] = $data['data'];

                $data['data'] = $orders;

            } else {

                $key = $data['exchange'] . '_' . $data['action'];

            }

            $memcached->set(
                $key,
                $data['data']
            );

        } elseif (
            $data['event'] == 'config' && $data['node'] == 'agent' &&
            isset($data['data']['markets']) && isset($data['data']['assets_labels']) && isset($data['data']['routes'])
        ) {

            $memcached->set(
                'config',
                $data['data']
            );

        } else {

            $message = '[ERROR] data broken. Node: ' . ($data['node'] ?? 'null');

            echo $message . PHP_EOL;

            $publisher->offer($robotrade_api->error('get_aeron_data', null, $message));

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

    usleep(100000);

}