<?php

use Src\Api;

require dirname(__DIR__) . '/vendor/autoload.php';

// memcached подключение
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// API для формирования сообщения для отправки по aeron
$robotrade_api = new Api('binance', 'test_php', 'core', '1');

// нужен publisher, отправлять команды по aeron в гейт
$publisher = new AeronPublisher('aeron:ipc');

while (true) {

    sleep(2);

    // берет все данные из memcached
    $memcached_data = $memcached->getMulti($memcached->getAllKeys());

    // отправка publisher
    $publisher->offer($robotrade_api->error('get_aeron_data', null, 'Here Message'));

    print_r(array_keys($memcached_data));

}
