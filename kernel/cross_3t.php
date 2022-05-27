<?php

use robotrade\Api;
use Src\Aeron;
use Src\Configurator;
use Src\Core;
use Src\Gate;
use Src\Cross3T;
use Aeron\Publisher;
use Src\Log;

require dirname(__DIR__) . '/index.php';
require dirname(__DIR__) . '/config/common_config.php';

// подключение к memcached
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// очистить все, что есть в memcached
$memcached->flush();

$common_config = CORES['cross_3t'];

// получаем конфиг от конфигуратора
$config = (SOURCE == 'file') ? $common_config['config'] : Configurator::getConfig($common_config['exchange'], $common_config['instance']);

// API для формирования сообщения для отправки по aeron
$robotrade_api = new Api($common_config['exchange'], $common_config['algorithm'], $common_config['node'], $common_config['instance']);

// Класс формата логов
$log = new Log($common_config['exchange'], $common_config['algorithm'], $common_config['node'], $common_config['instance']);

// нужен publisher, отправлять команды по aeron в гейт
Aeron::checkConnection(
    $gate_publisher = new Publisher($config['aeron']['publishers']['gate']['channel'], $config['aeron']['publishers']['gate']['stream_id'])
);

// нужен publisher, отправлять логи на сервер логов
Aeron::checkConnection(
    $log_publisher = new Publisher($config['aeron']['publishers']['log']['channel'], $config['aeron']['publishers']['log']['stream_id'])
);

// создаем класс cross 3t
$cross_3t = new Cross3T($config, $common_config);

// создаем класс для работы с ядром
$core = new Core($config);

// класс для работы с гейтом
$gate = new Gate($gate_publisher, $robotrade_api, $common_config['gate_sleep'] ?? 0);

// При запуске ядра отправляет запрос к гейту на отмену всех ордеров и получение баланса
$gate->cancelAllOrders()->getBalances(array_column($config['assets_labels'], 'common'))->send();

while (true) {

    usleep($common_config['sleep']);

    // отформировать и отделить все данные, полученные из memcached
    $all_data = $core->getFormatData($memcached);

    // балансы, ордербуки и неизвестные данные
    $balances = $all_data['balances'];
    $orderbooks = $all_data['orderbooks'];

    // если есть все необходимые данные
    if (!empty($balances) && !empty($orderbooks) && !empty($config)) {

        // запускаем алгоритм и получаем лучший результат
        if ($best_result = $cross_3t->run($balances, $orderbooks)) {

            // для каждого шага, если результат выпал на текущую биржу, отправить сообщение на создание ордера
            foreach (['step_one', 'step_two', 'step_three'] as $step) {

                if ($best_result[$step]['exchange'] == $common_config['exchange']) {

                    $gate_publisher->offer(
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

            foreach (['step_one', 'step_two', 'step_three'] as $step) {

                // удаляем из memcached данные о балансе
                $memcached->delete($best_result[$step]['exchange'] . '_balances');

            }

            $log_publisher->offer($log->sendExpectedTriangle($best_result));

            // Запрос на получение баланса
            $gate->getBalances(array_column($config['assets_labels'], 'common'))->send();

        }

    } else {

        if (empty($balances)) {

            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Empty $balances' . PHP_EOL;

            // Получение баланса
            $gate->getBalances(array_column($config['assets_labels'], 'common'))->send();

        }

        if (empty($orderbooks))
            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Empty $orderbooks' . PHP_EOL;

        if (empty($config))
            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Empty $config' . PHP_EOL;

        sleep(1);

    }

}
