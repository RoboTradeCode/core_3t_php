<?php

use robotrade\Api;
use Src\Cross3T;

require dirname(__DIR__) . '/index.php';
require dirname(__DIR__) . '/config/aeron_config.php';

// подключение к memcached
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// конфиг прописанный вручную
$config = CONFIG;

// API для формирования сообщения для отправки по aeron
$robotrade_api = new Api(EXCHANGE, ALGORITHM, NODE, INSTANCE);

// нужен publisher, отправлять команды по aeron в гейт
$publisher = new AeronPublisher(GATE_PUBLISHER['channel'], GATE_PUBLISHER['stream_id']);

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
    $all_keys = $cross_3t->getAllMemcachedKeys();

    // взять все данные из memcached
    $memcached_data = $memcached->getMulti($all_keys) ?? [];

    print_r($all_keys);

    // проверяем конфиг на обновление, если появился новый конфиг, обновить его, удалить данные конфига из memcached
    if ($cross_3t->proofConfigOnUpdate($config, $memcached_data))
        $memcached->delete('config');

    // отформировать и отделить все данные, полученные из memcached
    $all_data = $cross_3t->reformatAndSeparateData($memcached_data);

    // балансы, ордербуки и неизвестные данные
    $balances = $all_data['balances'];
    $orderbooks = $all_data['orderbooks'];
    $undefined = $all_data['undefined'];

    // если есть все необходимые данные
    if (!empty($balances) && !empty($orderbooks) && !empty($config)) {

        // фильтрация баланса в диапазоне минимальном и максимальном
        //$cross_3t->filterBalanceByMinAndMAxDealAmount($balances);

        // запускаем алгоритм и получаем лучший результат
        if ($best_result = $cross_3t->run($balances, $orderbooks)) {

            // для каждого шага, если результат выпал на текущую биржу, отправить сообщение на создание ордера
            foreach (['step_one', 'step_two', 'step_three'] as $step) {

                if ($best_result[$step]['exchange'] == EXCHANGE) {

                    $publisher->offer(
                        $robotrade_api->createOrder(
                            $best_result[$step]['amountAsset'] . '/' . $best_result[$step]['priceAsset'],
                            'market',
                            'buy',
                            $best_result[$step]['amount'],
                            $best_result[$step]['price'],
                            'Create order ' . $step
                        )
                    );

                    echo '[' . date('Y-m-d H:i:s') . '] Send to gate create order. Pair: ' . $best_result[$step]['amountAsset'] . '/' . $best_result[$step]['priceAsset'] . 'Amount: ' . $best_result[$step]['amount'] . 'Price: ' . $best_result[$step]['price'] . PHP_EOL;

                }

            }

        }

    } else {

        echo '[WARNING] $balances or $orderbooks or $configis is empty' . PHP_EOL;

    }

    if (!empty($undefined)) {

        echo '[WARNING] $undefined is not empty' . PHP_EOL;

        print_r($undefined) . PHP_EOL;

    }

}
