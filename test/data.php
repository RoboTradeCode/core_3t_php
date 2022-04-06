<?php

require dirname(__DIR__) . '/index.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$memcached->set('foo', 100);
var_dump($memcached->fetchAll());

$memcached->flush();
