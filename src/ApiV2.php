<?php

namespace Src;

use Aeron\Publisher;
use Exception;

class ApiV2
{

    private string $exchange;
    private string $algorithm;
    private string $node;
    private string $instance;
    private array $publishers;

    private \robotrade\Api $robotrade_api;
    private Publisher $gate_publisher;
    private Gate $gate;
    private Log $log;
    private Publisher $log_publisher;

    public function __construct(string $exchange, string $algorithm, string $node, string $instance, array $publishers)
    {

        $this->exchange = $exchange;
        $this->algorithm = $algorithm;
        $this->node = $node;
        $this->instance = $instance;
        $this->publishers = $publishers;

        // сделать классы для работы с robotrade api и гейтами
        $this->madeRobotradesApiAndGateClasses();

        // сделать паблишер для лог сервера
        $this->madeLogPublisher();

    }

    public function sendPingToLogServer(int $interation, float $period, bool $echo = true): void
    {

        if (Time::timeUp($period))
            $this->sendToLog($this->log->sendWorkCore($interation), $echo);

    }

    public function sendExpectedTriangleToLogServer(array $result): void
    {

        $this->sendToLog($this->log->sendExpectedTriangle($result), false);

    }

    public function getOrderStatus(string $client_order_id, string $symbol, bool $echo = true): void
    {

        $message = $this->robotrade_api->getOrderStatus($client_order_id, $symbol, 'Get status order ' . $client_order_id);

        // отправить гейту сообщение
        $this->sendCommandToGate($message, $echo);

        if ($echo)
            echo '[' . date('Y-m-d H:i:s') . '] Send to gate get status order. Id: ' .
                $client_order_id .
                ' Symbol: ' . $symbol .
                PHP_EOL;

    }

    public function cancelOrder(array $real_order, bool $echo = true): void
    {

        $message = $this->robotrade_api->cancelOrder($real_order['client_order_id'], $real_order['symbol'], 'Cancel order ' . $real_order['client_order_id']);

        // отправить гейту сообщение
        $this->sendCommandToGate($message, $echo);

        if ($echo)
            Debug::echo('[INFO] Cancel: ' . $real_order['client_order_id'] . ', ' . $real_order['symbol'] . ', ' . $real_order['price'] . ', ' . $real_order['side']);

    }

    public function cancelAllOrders(bool $echo = false): void
    {

        $this->gate->cancelAllOrders()->send($echo);

    }

    public function getBalances(bool $echo = false): void
    {

        $this->gate->getBalances()->send($echo);

    }

    public function createOrder(string $symbol, string $type, string $side, float $amount, float $price, bool $echo = true): void
    {

        if (in_array($type, ['limit', 'market']) && in_array($side, ['buy', 'sell'])) {

            $message = $this->robotrade_api->createOrder(
                $this->robotrade_api->generateUUID() . '|M3Maker',
                $symbol,
                $type,
                $side,
                $amount,
                $price,
                'Create order'
            );

            // отправить гейту сообщение
            $this->sendCommandToGate($message, $echo);

            // отправить в лог сервер
            $this->sendToLog($message, $echo);

            if ($echo)
                Debug::echo('[INFO] Create: ' . $symbol . ', ' . $side . ', ' . $amount . ', ' . $price);

        } else {

            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Type or side not correct' . PHP_EOL;

        }

    }

    public function cancelAllOrdersAndGetBalance(array $all_data, string $exchange): bool
    {
        [$balances, $real_orders] = [$all_data['balances'], $all_data['orders']];

        if (isset($balances[$exchange])) {
            $is_balance_used = false;

            foreach ($balances[$exchange] as $balance)
                if (!FloatRound::compare($balance['used'], 0)) {
                    $is_balance_used = true;
                    break;
                }

            if (!$is_balance_used && empty($real_orders[$exchange])) {
                echo '[' . date('Y-m-d H:i:s') . '] [OK] All orders canceled' . PHP_EOL;

                return true;
            }

            $this->cancelAllOrders();
        } else
            $this->getBalances();

        echo '[' . date('Y-m-d H:i:s') . '] [WAIT] Try to close all orders' . PHP_EOL;

        return false;
    }

    private function madeRobotradesApiAndGateClasses(): void
    {

        // API для формирования сообщения для отправки по aeron
        $this->robotrade_api = new \robotrade\Api($this->exchange, $this->algorithm, $this->node, $this->instance);

        echo '[' . date('Y-m-d H:i:s') . '] Try ' . $this->exchange . ' ' . $this->publishers['gates'][$this->exchange]['channel'] . ' ' . $this->publishers['gates'][$this->exchange]['stream_id'] . PHP_EOL;

        // нужены publisher, отправлять команды на сервер гейта
        Aeron::checkConnection(
            $this->gate_publisher = new Publisher(
                $this->publishers['gates'][$this->exchange]['channel'],
                $this->publishers['gates'][$this->exchange]['stream_id']
            )
        );

        // класс для работы с гейтом
        $this->gate = new Gate($this->gate_publisher, $this->robotrade_api);

        echo '[' . date('Y-m-d H:i:s') . '] With ' . $this->exchange . ' gates okay' . PHP_EOL;

    }

    private function madeLogPublisher(): void
    {

        // Класс формата логов
        $this->log = new Log($this->exchange, $this->algorithm, $this->node, $this->instance);

        // нужен publisher, отправлять логи на сервер логов
        Aeron::checkConnection(
            $this->log_publisher = new Publisher(
                $this->publishers['log']['channel'],
                $this->publishers['log']['stream_id']
            )
        );

        echo '[' . date('Y-m-d H:i:s') . '] With log gate okay' . PHP_EOL;

    }

    private function sendCommandToGate(string $message, bool $echo = true): void
    {

        try {

            $code = $this->gate_publisher->offer($message);

            if ($code <= 0) {

                Storage::recordLog('Aeron to gate server code is: '. $code, ['$message' => $message]);

                $mes_array = json_decode($message, true);

                $this->log->sendErrorToLogServer($mes_array['action'] ?? 'error', $message, 'Can not sendCommandToGate in Api class');

            }

            if ($echo)
                echo '[' . date('Y-m-d H:i:s') . '] Send to gate message. Code: ' . $code . PHP_EOL;

        } catch (Exception $e) {

            Storage::recordLog('Src\M3Maker\Api.php sendCommandToGate() Aeron made a fatal error', ['$message' => $message, '$e->getMessage()' => $e->getMessage()]);

        }

    }

    private function sendToLog(string $message, bool $echo = true): void
    {

        try {

            $code = $this->log_publisher->offer($message);

            if ($code <= 0) {

                Storage::recordLog('Aeron to log server code is: '. $code, ['$message' => $message]);

                $mes_array = json_decode($message, true);

                $this->log->sendErrorToLogServer($mes_array['action'] ?? 'error', $message, 'Can not sendCommandToGate in Api class');

            }

            if ($echo)
                echo '[' . date('Y-m-d H:i:s') . '] Send to log server message. Code: ' . $code . PHP_EOL;

        } catch (Exception $e) {

            Storage::recordLog('Src\M3Maker\Api.php sendToLog() Aeron made a fatal error', ['$message' => $message, '$e->getMessage()' => $e->getMessage()]);

        }

    }

}