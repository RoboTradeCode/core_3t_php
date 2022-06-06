<?php

use Src\Cross3T;
use Src\Debug;
use Src\DiscreteTime;
use Src\Multi\MultiConfigurator;
use Src\Multi\MultiFirstData;
use Aeron\Publisher;

require dirname(__DIR__) . '/index.php';

// подключение к memcached
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// очистить все, что есть в memcached
$memcached->flush();

// Получает конфиг от каждой биржи конфигуратора, а также конфиг из файла multi_3t.json
$config = MultiConfigurator::getConfig(dirname(__DIR__) . '/config/multi_3t.json');

// Формируем все неободимые соеденения, классы и т. п.
[$robotrade_apis, $log, $gate_publishers, $gates, $log_publisher, $multi_core] = MultiFirstData::get($config);

// создаем класс cross 3t
$cross_3t = new Cross3T($config, ['debug' => $config['debug'], 'made_html_vision_file' => $config['made_html_vision_file']]);

$discrete_time = new DiscreteTime();

Debug::initPath($config['made_html_vision_file']);

while (true) {

    usleep($config['sleep']);
    sleep(10);

    // отформировать и отделить все данные, полученные из memcached
    $all_data = $multi_core->getFormatData($memcached);

    // балансы, ордербуки и неизвестные данные
    $balances = $all_data['balances'];
    $orderbooks = $all_data['orderbooks'];

    // если есть все необходимые данные
    if (!empty($balances) && !empty($orderbooks) && !empty($config)) {

        Debug::rec($balances, 'Balances');
        Debug::rec($orderbooks, 'OrderBooks');

        // запускаем алгоритм и получаем лучший результат
        if ($best_result = $cross_3t->run($balances, $orderbooks, true)) {

            // для каждого шага, если результат выпал на текущую биржу, отправить сообщение на создание ордера
            foreach (['step_one', 'step_two', 'step_three'] as $step) {

                $message = $robotrade_apis[$best_result[$step]['exchange']]->createOrder(
                    $best_result[$step]['amountAsset'] . '/' . $best_result[$step]['priceAsset'],
                    'market',
                    $best_result[$step]['orderType'],
                    $best_result[$step]['amount'],
                    $best_result[$step]['price'],
                    'Create order ' . $step
                );

                // отправить гейту на постановку ордера
                $gate_publishers[$best_result[$step]['exchange']]->offer($message);

                // отправить в лог сервер, что ордер постановился
                $log_publisher->offer($message);

                echo '[' . date('Y-m-d H:i:s') . '] Send to gate create order. Pair: ' .
                    $best_result[$step]['amountAsset'] . '/' . $best_result[$step]['priceAsset'] .
                    ' Type: ' . $best_result[$step]['orderType'] .
                    ' Amount: ' . $best_result[$step]['amount'] .
                    ' Price: ' . $best_result[$step]['price'] .
                    ' Exchange: ' . $best_result[$step]['exchange'] .
                    PHP_EOL;

            }

            foreach (['step_one', 'step_two', 'step_three'] as $step) {

                if (isset($old_balances) && $old_balances[$best_result[$step]['exchange']] == $balances[$best_result[$step]['exchange']]) {
                    echo '[' . date('Y-m-d H:i:s') . '] Balance is old. Exchange: ' . $best_result[$step]['exchange'] . ' . ' . json_encode($balances[$best_result[$step]['exchange']]) . PHP_EOL;
                }

                // удаляем из memcached данные о балансе
                $memcached->delete($best_result[$step]['exchange'] . '_balances');

                // удаляем из memcached данные об ордербуке
                $memcached->delete($best_result[$step]['exchange'] . '_orderbook_' . $best_result[$step]['amountAsset'] . '/' . $best_result[$step]['priceAsset']);

                // Запрос на получение баланса
                $gates[$best_result[$step]['exchange']]->getBalances(array_column($config['assets_labels'], 'common'))->send();

            }

            // отправить на лог сервер теоретические расчеты
            $log_publisher->offer($log->sendExpectedTriangle($best_result));

            // отправляет полный баланс на лог сервер
            $log_publisher->offer($log->sendFullBalances($balances));

            $old_balances = $balances;

        }

        if ($discrete_time->proof()) {

            $log_publisher->offer($log->sendWorkCore($cross_3t->getInteration()));

        }

    } else {

        if (empty($balances))
            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Empty $balances' . PHP_EOL;

        if (empty($orderbooks))
            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Empty $orderbooks' . PHP_EOL;

        if (empty($config))
            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Empty $config' . PHP_EOL;

        sleep(1);

    }

}
