<?php

use Src\Cross3T;

require dirname(__DIR__) . '/vendor/autoload.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

//$cross_3t = new Cross3T();

while (true) {

    sleep(1);

    $memcached_data = $memcached->getMulti($memcached->getAllKeys());

    print_r(array_keys($memcached_data));

    if (isset($memcached_data['config'])) {

        $config = $memcached_data['config'];

        $memcached->delete('config');

        unset($memcached_data['config']);

        echo '[Ok] Config is update' . PHP_EOL;

    }

    foreach ($memcached_data as $key => $data)
        if (isset($data)) {

            $parts = explode('_', $key);

            $exchange = $parts[0];
            $action = $parts[1];
            $value = $parts[2] ?? null;

            if ($action == 'balances') {
                $balances[$exchange] = $data;
            } elseif ($action == 'orderbook' && $value) {
                $orderbooks[$value][$exchange] = $data;
            } else {
                $undefined[$key] = $data;
            }

        }

    if (isset($balances))
        print_r($balances) . PHP_EOL;

    if (isset($orderbooks))
        print_r($orderbooks) . PHP_EOL;

    if (isset($config))
        print_r($config) . PHP_EOL;

    if (isset($undefined)) {

        echo '[WARNING] Config is update' . PHP_EOL;

        print_r($undefined) . PHP_EOL;

    }

    //$cross_3t->run($balances, $orderbooks, $rates, $server, $data['symbol']);

}
