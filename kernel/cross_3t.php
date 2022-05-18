<?php

use robotrade\Api;
use Src\Configurator;
use Src\Core;
use Src\Cross3T;

require dirname(__DIR__) . '/index.php';
require dirname(__DIR__) . '/config/aeron_config.php';

// подключение к memcached
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// очистить все, что есть в memcached
$memcached->flush();

// получаем конфиг от конфигуратора
$config = DEBUG_HTML_VISION ? CONFIG : (new Configurator())->getConfig(EXCHANGE, INSTANCE);

// API для формирования сообщения для отправки по aeron
$robotrade_api = new Api(EXCHANGE, ALGORITHM, NODE, INSTANCE);

// нужен publisher, отправлять команды по aeron в гейт
$publisher = new AeronPublisher($config['aeron']['publishers']['gate']['channel'], $config['aeron']['publishers']['gate']['stream_id']);

// создаем класс cross 3t
$cross_3t = new Cross3T($config);

// При запуске ядра отправляет запрос к гейту на отмену всех ордеров и получение баланса
(new Core($publisher, $robotrade_api))->cancelAllOrders()->getBalances(array_column($config['assets_labels'], 'common'))->send();

while (true) {

    usleep(SLEEP);

    // берем все данные из memcached
    $all_keys = $cross_3t->getAllMemcachedKeys();

    // взять все данные из memcached
    $memcached_data = $memcached->getMulti($all_keys) ?? [];

    // отформировать и отделить все данные, полученные из memcached
    $all_data = $cross_3t->reformatAndSeparateData($memcached_data);

    // балансы, ордербуки и неизвестные данные
    $balances = $all_data['balances'];
    $orderbooks = $all_data['orderbooks'];

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
                            $best_result[$step]['orderType'],
                            $best_result[$step]['amount'],
                            $best_result[$step]['price'],
                            'Create order ' . $step
                        )
                    );

                    echo '[' . date('Y-m-d H:i:s') . '] Send to gate create order. Pair: ' . $best_result[$step]['amountAsset'] . '/' . $best_result[$step]['priceAsset'] . ' Type: ' . $best_result[$step]['orderType'] . ' Amount: ' . $best_result[$step]['amount'] . ' Price: ' . $best_result[$step]['price'] . PHP_EOL;

                }

            }

        }

    } else {

        echo '[WARNING] $balances or $orderbooks or $configis is empty' . PHP_EOL;

        sleep(1);

    }

}
