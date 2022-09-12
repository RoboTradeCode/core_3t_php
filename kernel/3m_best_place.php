<?php

use Src\ApiV2;
use Src\Configurator;
use Src\M3BestPlace\Filter;
use Aeron\Publisher;
use Src\M3BestPlace\M3BestPlace;
use Src\MemcachedData;
use Src\Time;

require dirname(__DIR__) . '/index.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// очистить все, что есть в memcached
$memcached->flush();

// получаем конфиг от конфигуратора
$config = Configurator::getConfigApiByFile('3m_best_place');

$markets = $config['markets'];
$assets = array_column($config['assets_labels'], 'common');
$routes_new_format = Filter::routeOnlyDirectAndReverse($config['routes']);
$routes = $config['routes'];
$core_config = $config['configs']['core_config'];
$node = $core_config['node'];
$exchange = $core_config['exchange'];
$algorithm = $core_config['algorithm'];
$instance = $core_config['instance'];
$expired_orderbook_time = $core_config['expired_orderbook_time'];
$sleep = $core_config['sleep'];
$max_deal_amounts = $core_config['max_deal_amounts'];
$rates = $core_config['rates'];
$max_depth = $core_config['max_depth'];
$fees = $core_config['fees'];
$publishers = $core_config['aeron']['publishers'];
$markets[$exchange] = $config['markets'];

$api = new ApiV2($exchange, $algorithm, $node, $instance, $publishers);

$multi_core = new MemcachedData([$exchange], $markets, $expired_orderbook_time);

$m3_best_place = new M3BestPlace($max_depth, $rates, $max_deal_amounts, $fees, $markets);

while (true) {

    usleep($sleep);

    $all_data = $multi_core->reformatAndSeparateData($memcached->getMulti($multi_core->keys));

    $balances = $all_data['balances'];

    $orderbooks = $all_data['orderbooks'];

    $real_orders = $all_data['orders'];

    if (isset($balances[$exchange])) {

        $results = $m3_best_place->run($routes, $balances, $orderbooks, true);

        foreach ($results as $result) {

            if (isset($result['results'][0])) {

                $full_info = $result['results'][0];

                if ($full_info['result_in_main_asset'] >= 0) {

                    $id_triangle_assets = [
                        $full_info['step_one']['amountAsset'] . '/' . $full_info['step_one']['priceAsset'] . '-' . $full_info['step_one']['orderType'],
                        $full_info['step_two']['amountAsset'] . '/' . $full_info['step_two']['priceAsset'] . '-' . $full_info['step_two']['orderType'],
                        $full_info['step_three']['amountAsset'] . '/' . $full_info['step_three']['priceAsset'] . '-' . $full_info['step_three']['orderType'],
                    ];

                    asort($id_triangle_assets);

                    $id_triangle = implode('-', $id_triangle_assets);

                    if (!isset($positions[$id_triangle]['time'])) {

                        foreach (['step_one', 'step_two', 'step_three'] as $item) {

                            $positions[$id_triangle] = [
                                $item => [
                                    'symbol' => $full_info[$item]['amountAsset'] . '/' . $full_info[$item]['priceAsset'],
                                    'type' => 'limit',
                                    'side' => $full_info[$item]['orderType'],
                                    'amount' => $full_info[$item]['amount'],
                                    'price' => $full_info[$item]['price']
                                ]
                            ];

                            // send all to create order
                            foreach ($positions[$id_triangle] as $position) {

                                echo '[' . date('Y-m-d H:i:s') . '] ' . $position['symbol'] . ' ' . $position['type'] . ' ' . $position['side'] . ' ' . $position['amount'] . ' ' . $position['price'] . PHP_EOL;

                                $api->createOrder($position['symbol'], $position['type'], $position['side'], $position['amount'], $position['price'], false);

                            }

                        }

                        $api->sendExpectedTriangleToLogServer($full_info);

                        $positions[$id_triangle]['time'] = time();

                    }

                    if ((time() - $positions[$id_triangle]['time']) >= 300) {

                        echo '[' . date('Y-m-d H:i:s') . '] Cancel All Orders Expired Time' . PHP_EOL;

                        $api->cancelAllOrders();

                        unset($positions[$id_triangle]['time']);

                    } elseif (isset($real_orders[$exchange])) {

                        foreach ($real_orders[$exchange] as $real_order) {

                            if (
                                $real_order['symbol'] == $full_info['step_one']['amountAsset'] . '/' . $full_info['step_one']['priceAsset'] && $real_order['side'] == $full_info['step_one']['orderType'] ||
                                $real_order['symbol'] == $full_info['step_two']['amountAsset'] . '/' . $full_info['step_two']['priceAsset'] && $real_order['side'] == $full_info['step_two']['orderType'] ||
                                $real_order['symbol'] == $full_info['step_three']['amountAsset'] . '/' . $full_info['step_three']['priceAsset'] && $real_order['side'] == $full_info['step_three']['orderType']
                            ) {

                                $isset_open_order = true;

                                break;

                            }

                        }

                        if (isset($isset_open_order)) {

                            unset($isset_open_order);

                        } elseif ((time() - $positions[$id_triangle]['time']) >= 5) {

                            echo '[' . date('Y-m-d H:i:s') . '] No orders for triangle: ' . $id_triangle . ', create a new' . PHP_EOL;

                            unset($positions[$id_triangle]['time']);

                        }

                    }

                }

            }

        }

    } else {

        // Выводит в консоль сообщения, что нет $balances[$exchange]
        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($balances[$exchange])' . PHP_EOL;

        sleep(1);

    }

    // каждую секунду выполняется условие
    if (Time::timeUp(1)) {

        // отправить пинг на лог сервер
        $api->sendPingToLogServer(1, false);

    }

}
