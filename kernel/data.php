<?php

require dirname(__DIR__) . '/vendor/autoload.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$memcached->set('foo', 100);
var_dump($memcached->get('foo'));

$memcached->flush();
