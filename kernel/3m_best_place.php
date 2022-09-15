<?php

use Src\ApiV2;
use Src\Configurator;
use Src\M3BestPlace\Filter;
use Aeron\Publisher;
use Src\M3BestPlace\M3BestPlace;
use Src\MemcachedData;

require dirname(__DIR__) . '/index.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// очистить все, что есть в memcached
$memcached->flush();

// получаем конфиг от конфигуратора
$config = Configurator::getConfigApiByFile('3m_best_place');

// all settings
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
$expired_open_order = $core_config['expired_open_order'];
$fees = $core_config['fees'];
$publishers = $core_config['aeron']['publishers'];
$markets[$exchange] = $config['markets'];

$api = new ApiV2($exchange, $algorithm, $node, $instance, $publishers);

$multi_core = new MemcachedData([$exchange], $markets, $expired_orderbook_time);

$m3_best_place = new M3BestPlace($max_depth, $rates, $max_deal_amounts, $fees, $markets);

while (true) {

    usleep($sleep);

    $all_data = $multi_core->reformatAndSeparateData($memcached->getMulti($multi_core->keys));

    [$balances, $orderbooks, $real_orders] = [$all_data['balances'], $all_data['orderbooks'], $all_data['orders']];

    if (isset($balances[$exchange])) {

        $results = $m3_best_place->run($routes, $balances, $orderbooks, true);

        foreach ($results as $result) {

            if ($full_info = $m3_best_place->getFullInfoByResult($result, 0)) {

                $positions = $m3_best_place->getPositions($full_info);

                if (!$m3_best_place->hasSimilarOrder($exchange, $real_orders, $positions))
                    $m3_best_place->create3MBestPlaceOrders($api, $positions, $full_info);

                $m3_best_place->cancelExpiredOpenOrders($api, $exchange, $real_orders, $expired_open_order);

            }

        }

    } else {

        // Выводит в консоль сообщения, что нет $balances[$exchange]
        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($balances[$exchange])' . PHP_EOL;

        sleep(1);

    }

    // каждую секунду отправить пинг на лог сервер
    $api->sendPingToLogServer(1, 1,false);

}
