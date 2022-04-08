<?php

use Src\Cross3T;

require dirname(__DIR__) . '/vendor/autoload.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

while (!isset($config)) {

    sleep(1);

    $memcached_data = $memcached->getMulti($memcached->getAllKeys());

    if (isset($memcached_data['config'])) {

        $config = $memcached_data['config'];

        $memcached->delete('config');

        echo '[Ok] Config is set' . PHP_EOL;

    } else
        echo '[WARNING] Config is not set' . PHP_EOL;

}

//$cross_3t = new Cross3T([]);

while (true) {

    sleep(1);

    $memcached_data = $memcached->getMulti($memcached->getAllKeys());

    if (isset($memcached_data['config'])) {

        $config = $memcached_data['config'];

        $memcached->delete('config');

        unset($memcached_data['config']);

        echo '[Ok] Config is update' . PHP_EOL;

    }

    print_r(array_keys($memcached->getMulti($memcached->getAllKeys())));

    //$cross_3t->run($balances, $orderbooks, $rates, $server, $data['symbol']);

}
