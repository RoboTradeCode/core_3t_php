<?php

use Src\Aeron;
use Src\Api;
use Src\Cross3T;

require dirname(__DIR__) . '/vendor/autoload.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$cross_3t = new Cross3T();

$publisher = new AeronPublisher("aeron:ipc");

$api = new Api('binance', 'cross_3t_php', 'core', '1');

while (true) {

    usleep(1000000);

    // пример использования command to gate
    $code = $publisher->offer($api->createOrder('BTC/USDT', 'limiit', 'buy', 0.001, 40000));

    sleep(1);

    $memcached_data = $memcached->getMulti($memcached->getAllKeys());

    print_r(array_keys($memcached->getMulti($memcached->getAllKeys())));

    //$cross_3t->run($balances, $orderbooks, $rates, $server, $data['symbol']);

}
