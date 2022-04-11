<?php

use Src\Cross3T;

require dirname(__DIR__) . '/vendor/autoload.php';

// подключение к memcached
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// получить конфиг из memcached. Пока не получит конфиг, алгоритм выполняться не будет
while (!isset($config)) {

    sleep(1);

    // берет конфиг из memcached
    $memcached_data = $memcached->get('config');

    // если нашел запись в memcached
    if ($memcached_data) {

        // присвоить конфиг
        $config = $memcached_data;

        // удалить из memcached
        $memcached->delete('config');

        echo '[Ok] Config is set' . PHP_EOL;

    } else
        echo '[WARNING] Config is not set' . PHP_EOL;

}

// создаем класс cross 3t
$cross_3t = new Cross3T($config);

while (true) {

    sleep(1);

    // берем все данные из memcached
    $memcached_data = $memcached->getMulti($memcached->getAllKeys());

    print_r(array_keys($memcached_data));

    // проверяем конфиг на обновление, если появился новый конфиг, обновить его, удалить данные конфига из memcached
    if ($cross_3t->proofConfigOnUpdate($config, $memcached_data))
        $memcached->delete('config');

    // отформировать и отделить все данные, полученные из memcached
    $all_data = $cross_3t->reformatAndSeparateData($memcached_data);

    // балансы, ордербуки и неизвестные данные
    $balances = $all_data['balances'];
    $orderbooks = $all_data['orderbooks'];
    $undefined = $all_data['undefined'];

    if (isset($balances))
        print_r($balances) . PHP_EOL;

    if (isset($orderbooks))
        print_r($orderbooks) . PHP_EOL;

    if (isset($config))
        print_r($config) . PHP_EOL;

    if (!empty($undefined)) {

        echo '[WARNING] $undefined is not empty' . PHP_EOL;

        print_r($undefined) . PHP_EOL;

    }

    //$cross_3t->run($balances, $orderbooks, $rates, $server, $data['symbol']);

}
