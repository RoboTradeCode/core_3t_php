<?php

require dirname(__DIR__) . '/vendor/autoload.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

print_r($memcached->getAllKeys());
