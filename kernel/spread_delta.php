<?php

use Src\ApiV2;
use Src\Configurator;
use Src\Filter;
use Src\Signals\Delta;
use Src\SpreadDelta\MemcachedData;
use Src\SpreadDelta\SpreadDelta;

require dirname(__DIR__) . '/index.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);
$memcached->flush();

$config = Configurator::getConfigApiByFile('spread_delta');

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
$delta_exchange = $core_config['delta_exchange'];
$delta_hypersensitivity = $core_config['delta_hypersensitivity'];
$deal_amount = $core_config['deal_amount'];
$amount_limitation = $core_config['amount_limitation'];
$rates = $core_config['rates'];
$min_profit = $core_config['min_profit'];
$max_depth = $core_config['max_depth'];
$fees = $core_config['fees'];
$publishers = $core_config['aeron']['publishers'];
$markets[$exchange] = $config['markets'];
$common_symbols = array_column($markets[$exchange], 'common_symbol');

$max_deal_amounts = Filter::getMaxDealAmountByRate($rates, $deal_amount);

//var_export($amount_limitation); echo PHP_EOL; die();

$api = new ApiV2($exchange, $algorithm, $node, $instance, $publishers);

$multi_core = new MemcachedData($exchange, $delta_exchange, $markets, $expired_orderbook_time);

$spread_delta = new SpreadDelta();

do {
    if (
        $api->cancelAllOrdersAndGetBalance(
            $multi_core->reformatAndSeparateData($memcached->getMulti($multi_core->keys)),
            $exchange
        )
    ) break;

    sleep(5);
} while(true);

$signal_delta = new Delta(5);

$iteration = 0;

while (true) {
    usleep($sleep);

    $all_data = $multi_core->reformatAndSeparateData($memcached->getMulti($multi_core->keys));

    [$balances, $orderbooks, $real_orders] = [$all_data['balances'], $all_data['orderbooks'], $all_data['orders']];

    $signal_delta->calc($delta_exchange, $orderbooks);

    if (isset($balances[$exchange])) {

        // Расчет ордеров по балансу, сколько должно стоять ордеров на рынках
        foreach ($max_deal_amounts as $asset => $deal_amount)
            $sell_assets[$asset] = intval(min($balances[$exchange][$asset]['total'], $amount_limitation[$asset]) / $deal_amount);

        $count_orders = [];
        foreach ($common_symbols as $common_symbol) {
            $count_orders[$common_symbol]['sell'] = 0;

            $count_orders[$common_symbol]['buy'] = 0;
        }

        foreach ($sell_assets as $asset => $count) {
            while (true)
                foreach ($common_symbols as $common_symbol) {
                    list($base_asset, $quote_asset) = explode('/', $common_symbol);

                    if ($base_asset == $asset) {
                        $count_orders[$common_symbol]['sell']++;

                        $count--;
                    } elseif ($quote_asset == $asset) {
                        $count_orders[$common_symbol]['buy']++;

                        $count--;
                    }

                    if ($count == 0) break 2;
                }
        }

        // Расчет цены по рынку с учетом дельты

        // Сравнение похожих ордеров и постановка ордеров

        // Удалить ненужные ордера если использован весь баланс и удалить невыгодные ордера по дельте

        $iteration++;


    } else {
        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($balances[$exchange])' . PHP_EOL;

        sleep(1);
    }

    $api->sendPingToLogServer($iteration, 1,false);
}
