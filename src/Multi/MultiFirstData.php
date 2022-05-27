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

            // Класс формата логов
            $logs[$exchange] = new Log($exchange, $config['algorithm'], $config['node'], $config['instances'][$exchange]);

            // нужены publisher, отправлять команды на сервер гейта
            Aeron::checkConnection(
                $gate_publishers[$exchange] = new Publisher(
                    $config['aeron']['publishers']['gate'][$exchange]['channel'],
                    $config['aeron']['publishers']['gate'][$exchange]['stream_id']
                )
            );

            // класс для работы с гейтом
            $gates[$exchange] = new Gate($gate_publishers[$exchange], $robotrade_apis[$exchange]);

        }

        if (
            !isset($robotrade_apis) ||
            !isset($logs) ||
            !isset($gate_publishers) ||
            !isset($gates)
        ) {

            echo '[' . date('Y-m-d H:i:s') . '] [ERROR] $robotrade_apis, $logs, $gate_publishers, $gates Empty!!!' . PHP_EOL;

            die();

        }

        // нужен publisher, отправлять логи на сервер логов
        Aeron::checkConnection(
            $log_publisher = new Publisher(
                $config['aeron']['publishers']['log']['channel'],
                $config['aeron']['publishers']['log']['stream_id']
            )
        );

        // отправляем на каждый гейт, закрыть все ордера и прислать балансы
        foreach ($gates ?? [] as $gate) {

            // При запуске ядра отправляет запрос к гейту на отмену всех ордеров и получение баланса
            $gate->cancelAllOrders()->getBalances(array_column($config['assets_labels'], 'common'))->send();

        }

        $multi_core = new MultiCore($config['exchanges'], $config['markets'], $config['expired_orderbook_time']);

        return [
            $robotrade_apis ?? [],
            $logs ?? [],
            $gate_publishers ?? [],
            $gates ?? [],
            $log_publisher,
            $multi_core,
        ];

    }

}