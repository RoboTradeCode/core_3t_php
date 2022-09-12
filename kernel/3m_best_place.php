<?php

use Src\ApiV2;
use Src\Configurator;
use Src\FloatRound;
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

                    $positions = [];

                    foreach (['step_one', 'step_two', 'step_three'] as $item) {

                        $positions[$item] = [
                            'symbol' => $full_info[$item]['amountAsset'] . '/' . $full_info[$item]['priceAsset'],
                            'type' => 'limit',
                            'side' => $full_info[$item]['orderType'],
                            'amount' => $full_info[$item]['amount'],
                            'price' => $full_info[$item]['price']
                        ];

                    }

                    $similar_orders = false;

                    if (isset($real_orders[$exchange])) {

                        foreach ($positions as $position) {

                            foreach ($real_orders[$exchange] as $real_order) {

                                if (
                                    $position['symbol'] == $real_order['symbol'] &&
                                    $position['side'] == $real_order['side'] &&
                                    FloatRound::compare($position['price'], $real_order['price'])
                                ) {

                                    $similar_orders = true;

                                    break 2;

                                }

                            }

                        }

                    }

                    if (!$similar_orders) {

                        foreach ($positions as $position) {

                            $api->createOrder($position['symbol'], $position['type'], $position['side'], $position['amount'], $position['price'], false);

                        }

                        $api->sendExpectedTriangleToLogServer($full_info);

                    }

                    if (isset($real_orders[$exchange])) {

                        foreach ($real_orders[$exchange] as $real_order) {

                            if ((microtime(true) - $real_order['timestamp'] / 1000) >= 300) {

                                echo '[' . date('Y-m-d H:i:s') . '] Cancel Order: ' . $real_order['client_order_id'] . PHP_EOL;

                                $api->cancelOrder($real_order['client_order_id'], $real_order['symbol'], false);

                            }

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
