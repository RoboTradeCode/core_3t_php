<?php

use Src\Api;
use Src\Configurator;
use Src\MemcachedData;
use Src\Time;

require dirname(__DIR__, 2) . '/index.php';

// подключение к memcached
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// очистить все, что есть в memcached
$memcached->flush();

// получить конфигурацию
$config = Configurator::getConfigApiByFile('research');

$config['configs']['core_config']['assets_labels'][$config['configs']['core_config']['exchange']] = $config['assets_labels'];

$core_config = $config['configs']['core_config'];

$core_config['markets'][$config['configs']['core_config']['exchange']] = $config['markets'];

$core_config['assets_labels'][$config['configs']['core_config']['exchange']] = $config['assets_labels'];

$exchange = $core_config['exchange'];

$symbol = $core_config['symbol'];

$side = $core_config['side'];

$amount = $core_config['amount'];

// берем precisions для нашего рынка
foreach ($core_config['markets'][$exchange] as $key => $market) {

    if ($market['common_symbol'] == $symbol) {

        $precisions = [
            'price_increment' => $market['price_increment'],
            'amount_increment' => $market['amount_increment'],
        ];

        break;

    }

}

if (isset($precisions[$symbol])) {

    // класс Api для работы с гейтами и лог сервером
    $api = new Api($config['configs']['core_config']);

    // создаем ядро, для работы с данными из memcached
    $core = new MemcachedData([$exchange], $core_config['markets'], $core_config['expired_orderbook_time']);

    do {

        echo '[' . date('Y-m-d H:i:s') . '] [WAIT] 1 seconds to get balances and orderbook for symbol: ' . $symbol . PHP_EOL;

        sleep(1);

        if ($memcached_data = $memcached->getMulti($core->keys)) {

            // получаем все данные из memcached
            $all_data = $core->reformatAndSeparateData($memcached_data);

        } else {

            echo '[' . date('Y-m-d H:i:s') . '] Try get balance and orderbook' . PHP_EOL;

        }

    } while(!isset($all_data['balances'][$exchange]) || !isset($all_data['orderbooks'][$symbol][$exchange]));

    // баланс
    $balance = $all_data['balances'][$exchange];

    // ордербук
    $orderbook = $all_data['orderbooks'][$symbol][$exchange];

    $i = 0;

    echo PHP_EOL . 'Create Orders [START]----------------------------------------------------------------------------------' . PHP_EOL;

    do {

        if ($side == 'sell') {

            $price = $precisions['price_increment'] * floor( ($orderbook['asks'][0][0] * (1 + $core_config['delta_percentage_price'] / 100)) / $precisions['price_increment']);

            $price = $precisions['price_increment'] * floor( ($price + $precisions['price_increment'] * $i) / $precisions['price_increment']);

        } else {

            $price = $precisions['price_increment'] * floor( ($orderbook['bids'][0][0] * (1 - $core_config['delta_percentage_price'] / 100)) / $precisions['price_increment']);

            $price = $precisions['price_increment'] * floor( ($price - $precisions['price_increment'] * $i) / $precisions['price_increment']);

        }

        $api->createOrder($symbol, 'limit', $side, $amount, $price);

        $i++;

        echo '[' . date('Y-m-d H:i:s') . '] Create Order Price: ' . $price . PHP_EOL;

        usleep(1000);

    } while(!Time::timeUp(1));

    echo PHP_EOL . 'Create Orders [END]----------------------------------------------------------------------------------' . PHP_EOL;

    echo '[' . date('Y-m-d H:i:s') . '] [WAIT] 10 seconds' . PHP_EOL;

    sleep(10);

    // берем реальные ордера
    do {

        if ($memcached_data = $memcached->getMulti($core->keys)) {

            // получаем все данные из memcached
            $all_data = $core->reformatAndSeparateData($memcached_data);

            // реальные ордера
            if (isset($all_data['orders'])) {

                $real_orders = $all_data['orders'];

                break;

            } else {

                echo '[' . date('Y-m-d H:i:s') . '] Can not get real orders' . PHP_EOL;

            }

        } else {

            echo '[' . date('Y-m-d H:i:s') . '] Can not get memcached data' . PHP_EOL;

        }

    } while(true);

    echo '[' . date('Y-m-d H:i:s') . '] [CONCLUSION] For 1 second core send ' . $i . ' times to create orders. And gate can create ' . count($real_orders) . ' orders' . PHP_EOL;

    // если есть баланс used, то послать команду на отмену всех ордеров, до тех пор пока не отменятся все ордера
    do {

        echo '[' . date('Y-m-d H:i:s') . '] [WAIT] 10 seconds' . PHP_EOL;

        sleep(10);

        $do = true;

        if ($memcached_data = $memcached->getMulti($core->keys)) {

            // получаем все данные из memcached
            $all_data = $core->reformatAndSeparateData($memcached_data);

            // реальные ордера
            if (isset($all_data['balances'])) {

                foreach ($all_data['balances'][$exchange] as $balance) {

                    if (bccomp($balance['used'], 0.00000000, 8) != 0) {

                        $api->cancelAllOrders();

                        $do = true;

                        break;

                    }

                    $do = false;

                }

            } else {

                $api->getBalances();

                echo '[' . date('Y-m-d H:i:s') . '] Can not gat balances' . PHP_EOL;

            }

        } else {

            echo '[' . date('Y-m-d H:i:s') . '] Can not gat memcached data' . PHP_EOL;

        }

    } while($do);

} else {

    echo '[' . date('Y-m-d H:i:s') . '] [ERROR] No such market: ' . $symbol . PHP_EOL;

}

echo '[' . date('Y-m-d H:i:s') . '] Research end.' . PHP_EOL;
