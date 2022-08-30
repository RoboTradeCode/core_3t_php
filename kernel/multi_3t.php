<?php

use Src\Cross3T;
use Src\Multi\MultiConfigurator;
use Src\Multi\MultiFirstData;
use Aeron\Publisher;
use Src\OrderBookCorrect;
use Src\Storage;
use Src\Time;

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
$cross_3t = new Cross3T($config, ['debug' => $config['debug'], 'made_html_vision_file' => $config['made_html_vision_file'], 'made_html_vision_for_best_result_file' => $config['made_html_vision_for_best_result_file']]);

while (true) {

    usleep($config['sleep']);

    // отформировать и отделить все данные, полученные из memcached
    $all_data = $multi_core->getFormatData($memcached);

    // балансы, ордербуки и неизвестные данные
    $balances = $all_data['balances'];
    $orderbooks = $all_data['orderbooks'];

    // если есть все необходимые данные
    if (!empty($balances) && !empty($orderbooks) && !empty($config)) {

        // запускаем алгоритм и получаем лучший результат
        if ($best_result = $cross_3t->run($balances, $orderbooks, true)) {

            // для каждого шага, если результат выпал на текущую биржу, отправить сообщение на создание ордера
            foreach (['step_one', 'step_two', 'step_three'] as $step) {

                $client_order_id = $robotrade_apis[$best_result[$step]['exchange']]->generateUUID() . '|Multi3t';

                $best_result[$step]['client_order_id'] = $client_order_id;

                $message = $robotrade_apis[$best_result[$step]['exchange']]->createOrder(
                    $client_order_id,
                    $best_result[$step]['amountAsset'] . '/' . $best_result[$step]['priceAsset'],
                    'market',
                    $best_result[$step]['orderType'],
                    $best_result[$step]['amount'],
                    $best_result[$step]['price'],
                    'Create order ' . $step
                );

                try {

                    // отправить гейту на постановку ордера
                    $code = $gate_publishers[$best_result[$step]['exchange']]->offer($message);

                    if ($code <= 0) {

                        Storage::recordLog('Aeron to gate server code is: '. $code, ['$message' => $message, '$e->getMessage()' => $e->getMessage()]);

                        $mes_array = json_decode($message, true);

                        $log->sendErrorToLogServer($mes_array['action'] ?? 'error', $message, 'Can not send gate to create order in multi_3t.php');

                    }

                } catch (Exception $e) {

                    Storage::recordLog('Aeron made a fatal error', ['$message' => $message]);

                }

                try {

                    // отправить в лог сервер, что ордер постановился
                    $code = $log_publisher->offer($message);

                    if ($code <= 0) {

                        Storage::recordLog('Aeron to log server code is: '. $code, ['$message' => $message]);

                        $mes_array = json_decode($message, true);

                        $log->sendErrorToLogServer($mes_array['action'] ?? 'error', $message, 'Can not send message to log server in multi_3t.php');

                    }

                } catch (Exception $e) {

                    Storage::recordLog('Aeron made a fatal error', ['$message' => $message, '$e->getMessage()' => $e->getMessage()]);

                }

                echo '[' . date('Y-m-d H:i:s') . '] Send to gate create order. Pair: ' .
                    $best_result[$step]['amountAsset'] . '/' . $best_result[$step]['priceAsset'] .
                    ' Type: ' . $best_result[$step]['orderType'] .
                    ' Amount: ' . $best_result[$step]['amount'] .
                    ' Price: ' . $best_result[$step]['price'] .
                    ' Exchange: ' . $best_result[$step]['exchange'] .
                    PHP_EOL;

            }

            foreach (['step_one', 'step_two', 'step_three'] as $step) {

                // удаляем из memcached данные о балансе
                $memcached->delete($best_result[$step]['exchange'] . '_balances');

                // делаем корректировку ордербука с перерасчетом и сохраняем в memcached измененные данные об ордербуке
                OrderBookCorrect::beforeRealCreateOrder(
                    $orderbooks[$best_result[$step]['amountAsset'] . '/' . $best_result[$step]['priceAsset']][$best_result[$step]['exchange']],
                    'market',
                    $best_result[$step]['orderType'],
                    $best_result[$step]['amount'],
                    $best_result[$step]['price']
                );
                $memcached->set(
                    $best_result[$step]['exchange'] . '_orderbook_' . $best_result[$step]['amountAsset'] . '/' . $best_result[$step]['priceAsset'],
                    $orderbooks[$best_result[$step]['amountAsset'] . '/' . $best_result[$step]['priceAsset']][$best_result[$step]['exchange']]
                );

                // Запрос на получение баланса
                $gates[$best_result[$step]['exchange']]->getBalances(array_column($config['assets_labels'], 'common'))->send();

            }

            try {

                // отправить на лог сервер теоретические расчеты
                $code = $log_publisher->offer($log->sendExpectedTriangle($best_result));

                if ($code <= 0) {

                    Storage::recordLog('Aeron to log server code is: '. $code, ['$message' => $message]);

                    $mes_array = json_decode($message, true);

                    $log->sendErrorToLogServer($mes_array['action'] ?? 'error', $message, 'Can not send message about expected triangles to log server in multi_3t.php');

                }

            } catch (Exception $e) {

                Storage::recordLog('Aeron made a fatal error', ['$message' => $message, '$e->getMessage()' => $e->getMessage()]);

            }

            try {

                // отправляет полный баланс на лог сервер
                $code = $log_publisher->offer($log->sendFullBalances($balances));

                if ($code <= 0) {

                    Storage::recordLog('Aeron to log server code is: '. $code, ['$message' => $message]);

                    $mes_array = json_decode($message, true);

                    $log->sendErrorToLogServer($mes_array['action'] ?? 'error', $message, 'Can not send full balance to log server in multi_3t.php');

                }

            } catch (Exception $e) {

                Storage::recordLog('Aeron made a fatal error', ['$message' => $message, '$e->getMessage()' => $e->getMessage()]);

            }

            sleep(3);

        }

        if (Time::timeUp(1)) {

            $message = $log->sendWorkCore($cross_3t->getInteration());

            try {

                $code = $log_publisher->offer($message);

                if ($code <= 0) {

                    Storage::recordLog('Aeron to log server code is: '. $code, ['$message' => $message]);

                    $mes_array = json_decode($message, true);

                    $log->sendErrorToLogServer($mes_array['action'] ?? 'error', $message, 'Can not send ping to log server in multi_3t.php');

                }

            } catch (Exception $e) {

                Storage::recordLog('Aeron made a fatal error', ['$message' => $message, '$e->getMessage()' => $e->getMessage()]);

            }

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
