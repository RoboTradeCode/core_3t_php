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

if (isset($precisions)) {

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

    } while(empty($all_data['balances'][$exchange]) || empty($all_data['orderbooks'][$symbol][$exchange]));

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

        $api->createOrder($symbol, 'limit', $side, $amount, $price, false);

        $i++;

        echo '[' . date('Y-m-d H:i:s') . '] Create Order ' . $i . ' Price: ' . $price . PHP_EOL;

        usleep(10000);

    } while(!Time::timeUp(1));

    echo PHP_EOL . 'Create Orders [END]----------------------------------------------------------------------------------' . PHP_EOL;

    // берем реальные ордера
    do {

        echo '[' . date('Y-m-d H:i:s') . '] [WAIT] 10 seconds' . PHP_EOL;

        sleep(10);

        if ($memcached_data = $memcached->getMulti($core->keys)) {

            // получаем все данные из memcached
            $all_data = $core->reformatAndSeparateData($memcached_data);

            // реальные ордера
            if (!empty($all_data['orders'])) {

                $real_orders = $all_data['orders'][$exchange];

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
            if (!empty($all_data['balances'])) {

                echo 'Balances: ----------------------------------------------------------------------------------' . PHP_EOL;
                foreach ($all_data['balances'][$exchange] as $asset => $balance)
                    echo '[' . date('Y-m-d H:i:s') . '] ' . $asset . ' (free: ' . $balance['free'] . ' | used: ' . $balance['used'] . ' | total: ' . $balance['total'] . ') ' . PHP_EOL;
                echo 'Balances: ----------------------------------------------------------------------------------' . PHP_EOL;

                foreach ($all_data['balances'][$exchange] as $balance) {

                    if (bccomp($balance['used'], 0.00000000, 8) != 0) {

                        $api->cancelAllOrders();

                        $do = true;

                        break;

                    }

                    $do = false;

                }

                if (!$do)
                    break;

            } else {

                $api->getBalances();

                echo '[' . date('Y-m-d H:i:s') . '] Can not gat balances' . PHP_EOL;

            }

        } else {

            echo '[' . date('Y-m-d H:i:s') . '] Can not gat memcached data' . PHP_EOL;

        }

    } while(true);

    do {

        echo '[' . date('Y-m-d H:i:s') . '] [WAIT] 1 seconds' . PHP_EOL;

        sleep(1);

        if ($memcached_data = $memcached->getMulti($core->keys)) {

            // получаем все данные из memcached
            $all_data = $core->reformatAndSeparateData($memcached_data);

            // реальные ордера
            if (empty($all_data['orders'])) {

                echo 'Balances: ----------------------------------------------------------------------------------' . PHP_EOL;
                foreach ($all_data['balances'][$exchange] as $asset => $balance)
                    echo '[' . date('Y-m-d H:i:s') . '] ' . $asset . ' (free: ' . $balance['free'] . ' | used: ' . $balance['used'] . ' | total: ' . $balance['total'] . ') ' . PHP_EOL;
                echo 'Balances: ----------------------------------------------------------------------------------' . PHP_EOL;

                $real_orders = $all_data['orders'][$exchange];

                break;

            } else {

                echo '[' . date('Y-m-d H:i:s') . '] Can not get real orders or balances' . PHP_EOL;

            }

        } else {

            echo '[' . date('Y-m-d H:i:s') . '] Can not get memcached data' . PHP_EOL;

        }

    } while(true);

} else {

    echo '[' . date('Y-m-d H:i:s') . '] [ERROR] No such market: ' . $symbol . PHP_EOL;

}

echo '[' . date('Y-m-d H:i:s') . '] Research end.' . PHP_EOL;
