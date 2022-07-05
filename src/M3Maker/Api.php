<?php

namespace Src\M3Maker;

use Src\Aeron;
use Src\Gate;
use Src\Log;
use Aeron\Publisher;
use Src\Storage;

class Api
{

    private array $config;
    private array $robotrade_apis;
    private array $gate_publishers;
    private array $gates;
    private Log $log;
    private Publisher $log_publisher;

    public function __construct(array $config)
    {

        $this->config = $config;

        // сделать классы для работы с robotrade api и гейтами
        $this->madeRobotradesApiAndGateClasses();

        // сделать паблишер для лог сервера
        $this->madeLogPublisher();

        // отправить первоначальные команды всем гейтам
        $this->sendFirstCommandToAllGates();

    }

    private function sendCommandToGate(string $exchange, string $message): void
    {

        $code = $this->gate_publishers[$exchange]->offer($message);

        if ($code <= 0)
            Storage::recordLog('Aeron to gate server code is: '. $code, ['$message' => $message]);

        echo '[' . date('Y-m-d H:i:s') . '] Send to gate message. Code: ' . $code . PHP_EOL;

    }

    private function sendToLog(string $message): void
    {

        $code = $this->log_publisher->offer($message);

        if ($code <= 0)
            Storage::recordLog('Aeron to log server code is: '. $code, ['$message' => $message]);

        echo '[' . date('Y-m-d H:i:s') . '] Send to log server message. Code: ' . $code . PHP_EOL;

    }

    public function sendPingToLogServer(int $interation): void
    {

        $this->sendToLog($this->log->sendWorkCore($interation));

    }

    public function sendErrorToLogServer(string $exchange, string $message): void
    {

        $message = $this->robotrade_apis[$exchange]->error('core_error', $message);

        // отправить в лог сервер
        $this->sendToLog($message);

    }

    public function getOrderStatus(string $exchange, string $client_order_id, string $symbol): void
    {

        $message = $this->robotrade_apis[$exchange]->getOrderStatus($client_order_id, $symbol, 'Get status order ' . $client_order_id);

        // отправить гейту сообщение
        $this->sendCommandToGate($exchange, $message);

        echo '[' . date('Y-m-d H:i:s') . '] Send to gate get status order. Id: ' .
            $client_order_id .
            ' Symbol: ' . $symbol .
            ' Exchange: ' . $exchange .
            PHP_EOL;

    }

    public function cancelOrder(string $exchange, string $client_order_id, string $symbol): void
    {

        $message = $this->robotrade_apis[$exchange]->cancelOrder($client_order_id, $symbol, 'Cancel order ' . $client_order_id);

        // отправить гейту сообщение
        $this->sendCommandToGate($exchange, $message);

        echo '[' . date('Y-m-d H:i:s') . '] Send to gate cancel order. Id: ' .
            $client_order_id .
            ' Symbol: ' . $symbol .
            ' Exchange: ' . $exchange .
            PHP_EOL;

    }

    public function createOrder(string $exchange, string $symbol, string $type, string $side, float $amount, float $price): void
    {

        if (in_array($type, ['limit', 'market']) && in_array($side, ['buy', 'sell'])) {

            $message = $this->robotrade_apis[$exchange]->createOrder(
                $this->robotrade_apis[$exchange]->generateUUID() . '|M3Maker',
                $symbol,
                $type,
                $side,
                $amount,
                $price,
                'Create order'
            );

            // отправить гейту сообщение
            $this->sendCommandToGate($exchange, $message);

            // отправить в лог сервер
            $this->sendToLog($message);

            echo '[' . date('Y-m-d H:i:s') . '] Send to gate create order. Pair: ' .
                $symbol .
                ' Side: ' . $side .
                ' Amount: ' . $amount .
                ' Price: ' . $price .
                ' Exchange: ' . $exchange .
                PHP_EOL;

        } else {

            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Type or side not correct' . PHP_EOL;

        }

    }

    private function madeRobotradesApiAndGateClasses(): void
    {

        foreach ($this->config['exchanges'] as $exchange) {

            // API для формирования сообщения для отправки по aeron
            $this->robotrade_apis[$exchange] = new \robotrade\Api($exchange, $this->config['algorithm'], $this->config['node'], $this->config['instances'][$exchange]);

            echo '[' . date('Y-m-d H:i:s') . '] Try ' . $exchange . ' ' . $this->config['aeron']['publishers']['gates'][$exchange]['channel'] . ' ' . $this->config['aeron']['publishers']['gates'][$exchange]['stream_id'] . PHP_EOL;

            // нужены publisher, отправлять команды на сервер гейта
            Aeron::checkConnection(
                $this->gate_publishers[$exchange] = new Publisher(
                    $this->config['aeron']['publishers']['gates'][$exchange]['channel'],
                    $this->config['aeron']['publishers']['gates'][$exchange]['stream_id']
                )
            );

            // класс для работы с гейтом
            $this->gates[$exchange] = new Gate($this->gate_publishers[$exchange], $this->robotrade_apis[$exchange]);

            echo '[' . date('Y-m-d H:i:s') . '] With ' . $exchange . ' gates okay' . PHP_EOL;

        }

        if (
            !isset($this->robotrade_apis) ||
            !isset($this->gate_publishers) ||
            !isset($this->gates)
        ) {

            echo '[' . date('Y-m-d H:i:s') . '] [ERROR] $robotrade_apis, $log, $gate_publishers, $gates Empty!!!' . PHP_EOL;

            die();

        }


    }

    private function madeLogPublisher(): void
    {

        // Класс формата логов
        $this->log = new Log($this->config['exchange'], $this->config['algorithm'], $this->config['node'], $this->config['instance']);

        // нужен publisher, отправлять логи на сервер логов
        Aeron::checkConnection(
            $this->log_publisher = new Publisher(
                $this->config['aeron']['publishers']['log']['channel'],
                $this->config['aeron']['publishers']['log']['stream_id']
            )
        );

        echo '[' . date('Y-m-d H:i:s') . '] With log gate okay' . PHP_EOL;

    }

    private function sendFirstCommandToAllGates(): void
    {

        // отправляем на каждый гейт, закрыть все ордера и прислать балансы
        foreach ($this->gates as $exchange => $gate) {

            // При запуске ядра отправляет запрос к гейту на отмену всех ордеров и получение баланса
            $gate->cancelAllOrders()->getBalances(array_column($this->config['assets_labels'][$exchange], 'common'))->send();

        }

    }

}