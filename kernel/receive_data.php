<?php

use Src\Aeron;
use Src\Test\TestAgentFormatData;

require dirname(__DIR__) . '/index.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

/* Посылаем команду агенту, чтобы прислал конфиг */
$publisher = new AeronPublisher("aeron:ipc");

do {

    $code = $publisher->offer(
        Aeron::messageEncode(
            (new TestAgentFormatData('binance'))->sendAgentGetFullConfig()
        )
    );

    echo 'Try to send command get_full_config to Agent. Code: ' . $code . PHP_EOL;

} while($code > 0);
/* End */

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
            $data['event'] == 'get' && $data['node'] == 'agent' &&
            $data['data']['markets'] && $data['data']['assets_labels'] && $data['data']['routes']
        ) {

            $memcached->set(
                'config',
                $data['data']
            );

        } else {

            echo '[ERROR] data broken' . PHP_EOL;

        }

    }

}

$aeron_configs = (new TestAgentFormatData('binance'))->aeron_configs_destinations();

$subscriber = new AeronSubscriber('handler', 'aeron:udp?control-mode=manual');

foreach ($aeron_configs as $aeron_config) {

    $subscriber->addDestination($aeron_config);

}

while (true) {

    $subscriber->poll();

    usleep(10);

}