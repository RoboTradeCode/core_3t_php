<?php

use Src\Multi\MultiConfigurator;
use Src\Multi\MultiCore;

require dirname(__DIR__) . '/index.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$config = MultiConfigurator::getConfig(dirname(__DIR__) . '/config/multi_3t.json');

$multi_core = new MultiCore($config['exchanges'], $config['markets'], $config['expired_orderbook_time']);

// отформировать и отделить все данные, полученные из memcached
$all_data = $multi_core->getFormatData($memcached);

print_r($all_data);
echo PHP_EOL;
