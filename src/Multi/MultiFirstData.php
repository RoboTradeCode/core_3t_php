<?php

namespace Src\Multi;

use robotrade\Api;
use Src\Aeron;
use Src\Gate;
use Src\Log;
use Aeron\Publisher;

class MultiFirstData
{

    public static function get($config)
    {

        foreach ($config['exchanges'] as $exchange) {

            // API для формирования сообщения для отправки по aeron
            $robotrade_apis[$exchange] = new Api($exchange, $config['algorithm'], $config['node'], $config['instances'][$exchange]);

            echo '[' . date('Y-m-d H:i:s') . '] Try ' . $exchange . ' ' . $config['aeron']['publishers']['gates'][$exchange]['channel'] . ' ' . $config['aeron']['publishers']['gates'][$exchange]['stream_id'] . PHP_EOL;

            // нужены publisher, отправлять команды на сервер гейта
            Aeron::checkConnection(
                $gate_publishers[$exchange] = new Publisher(
                    $config['aeron']['publishers']['gates'][$exchange]['channel'],
                    $config['aeron']['publishers']['gates'][$exchange]['stream_id']
                )
            );

            // класс для работы с гейтом
            $gates[$exchange] = new Gate($gate_publishers[$exchange], $robotrade_apis[$exchange]);

            echo '[' . date('Y-m-d H:i:s') . '] With ' . $exchange . ' gates okay' . PHP_EOL;

        }

        if (
            !isset($robotrade_apis) ||
            !isset($gate_publishers) ||
            !isset($gates)
        ) {

            echo '[' . date('Y-m-d H:i:s') . '] [ERROR] $robotrade_apis, $log, $gate_publishers, $gates Empty!!!' . PHP_EOL;

            die();

        }

        // Класс формата логов
        $log = new Log($config['exchange'], $config['algorithm'], $config['node'], $config['instance']);

        // нужен publisher, отправлять логи на сервер логов
        Aeron::checkConnection(
            $log_publisher = new Publisher(
                $config['aeron']['publishers']['log']['channel'],
                $config['aeron']['publishers']['log']['stream_id']
            )
        );

        echo '[' . date('Y-m-d H:i:s') . '] With log gate okay' . PHP_EOL;

        // отправляем на каждый гейт, закрыть все ордера и прислать балансы
        foreach ($gates ?? [] as $gate) {

            // При запуске ядра отправляет запрос к гейту на отмену всех ордеров и получение баланса
            $gate->cancelAllOrders()->getBalances(array_column($config['assets_labels'], 'common'))->send();

        }

        $multi_core = new MultiCore($config['exchanges'], $config['markets'], $config['expired_orderbook_time']);

        return [
            $robotrade_apis ?? [],
            $log,
            $gate_publishers ?? [],
            $gates ?? [],
            $log_publisher,
            $multi_core,
        ];

    }

}