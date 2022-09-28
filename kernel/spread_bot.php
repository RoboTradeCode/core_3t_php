<?php

use Src\ApiV2;
use Src\CapitalRule\LimitationBalance;
use Src\Configurator;
use Src\Debug;
use Src\Filter;
use Src\SpreadBot\MemcachedData;
use Src\SpreadBot\SpreadBot;
use Src\TimeV2;

require dirname(__DIR__) . '/index.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);
$memcached->flush();

$config = Configurator::getConfigApiByFile('spread_bot');

$markets = $config['markets'];
$assets = array_column($config['assets_labels'], 'common');
$core_config = $config['configs']['core_config'];
$node = $core_config['node'];
$exchange = $core_config['exchange'];
$algorithm = $core_config['algorithm'];
$instance = $core_config['instance'];
$expired_orderbook_time = $core_config['expired_orderbook_time'];
$debug = $core_config['debug'];
$sleep = $core_config['sleep'];
$market_discovery_exchange = $core_config['market_discovery_exchange'];
$min_profit = $core_config['min_profit'];
$min_deal_amount = $core_config['min_deal_amount'];
$max_deal_amount = $core_config['max_deal_amount'];
$amount_limitations = $core_config['amount_limitations'];
$fees = $core_config['fees'];
$rates = $core_config['rates'];
$publishers = $core_config['aeron']['publishers'];
$markets[$exchange] = $config['markets'];
$common_symbols = array_column($markets[$exchange], 'common_symbol');

$min_deal_amounts = Filter::getDealAmountByRate($rates, $min_deal_amount);
$max_deal_amounts = Filter::getDealAmountByRate($rates, $max_deal_amount);

Debug::switchOn($debug);

$api = new ApiV2($exchange, $algorithm, $node, $instance, $publishers);

$multi_core = new MemcachedData($exchange, $market_discovery_exchange, $markets, $expired_orderbook_time);

do {
    if (
        $api->cancelAllOrdersAndGetBalance(
            $multi_core->reformatAndSeparateData($memcached->getMulti($multi_core->keys)),
            $exchange
        )
    ) break;
    sleep(5);
} while (true);

$spread_bot = new SpreadBot($exchange, $market_discovery_exchange);

$iteration = 0;

while (true) {
    usleep($sleep);

    $all_data = $multi_core->reformatAndSeparateData($memcached->getMulti($multi_core->keys));

    [$balances, $orderbooks, $real_orders] = [$all_data['balances'], $all_data['orderbooks'], $all_data['orders']];

    if (!empty($balances[$exchange])) {
        $must_orders = LimitationBalance::get($balances[$exchange], $assets, $common_symbols, $max_deal_amounts, $amount_limitations);

        foreach ($common_symbols as $symbol) {
            if (
                !empty($orderbooks[$symbol][$exchange]) &&
                !empty($orderbooks[$symbol][$market_discovery_exchange])
            ) {
                $market = $spread_bot->getMarket($markets, $symbol);

                $market_discovery = $spread_bot->getBestOrderbook($orderbooks, $symbol, false);

                $profit = $spread_bot->getProfit($market_discovery, $min_profit);

                $exchange_orderbook = $spread_bot->getBestOrderbook($orderbooks, $symbol);

                list($base_asset, $quote_asset) = explode('/', $symbol);

                $real_orders_for_symbol = $spread_bot->filterOrdersBySideAndSymbol($real_orders, $symbol);

                $debug_data = [
                    'symbol' => $symbol,
                    'exchange_bid' => $exchange_orderbook['bid'],
                    'exchange_ask' => $exchange_orderbook['ask'],
                    'market_discovery_bid' => $market_discovery['bid'],
                    'market_discovery_ask' => $market_discovery['ask'],
                    'profit_bid' => $profit['bid'],
                    'profit_ask' => $profit['ask'],
                    'max_deal_amount_base_asset' => $max_deal_amounts[$base_asset] . ' ' . $base_asset,
                    'max_deal_amount_quote_asset' => $max_deal_amounts[$quote_asset] . ' ' . $quote_asset,
                    'is_exchange_bid_less_profit_bid' => $exchange_orderbook['bid'] <= $profit['bid'],
                    'has_enough_balance_quote_asset' => $balances[$exchange][$quote_asset]['free'] >= $max_deal_amounts[$quote_asset],
                    'is_exchange_ask_less_profit_ask' => $exchange_orderbook['ask'] >= $profit['ask'],
                    'has_enough_balance_base_asset' => $balances[$exchange][$base_asset]['free'] >= $max_deal_amounts[$base_asset],
                    'is_not_empty_real_orders' => !empty($real_orders[$exchange]),
                    'real_orders_for_symbol_sell' => count($real_orders_for_symbol['sell']),
                    'real_orders_for_symbol_buy' => count($real_orders_for_symbol['buy']),
                ];

                foreach ($must_orders as $as => $must_order) {
                    $debug_data['must_order_' . $as . '_sell'] = $must_order['sell'];
                    $debug_data['must_order_' . $as . '_buy'] = $must_order['buy'];
                }

                if (
                    $spread_bot->isCreateBuyOrder(
                        $exchange_orderbook, $profit, $balances, $quote_asset,
                        $max_deal_amounts, $real_orders_for_symbol, $must_orders[$symbol]
                    )
                ) {
                    $api->createOrder(
                        $symbol, 'limit', 'buy',
                        $max_deal_amounts[$base_asset],
                        $spread_bot->incrementNumber($exchange_orderbook['bid'] + 2 * $market['price_increment'], $market['price_increment'])
                    );

                    Debug::printAll($debug_data, $balances[$exchange], $real_orders_for_symbol['buy'], $exchange);
                }

                if (
                    $spread_bot->isCreateSellOrder(
                        $exchange_orderbook, $profit, $balances, $base_asset,
                        $max_deal_amounts, $real_orders_for_symbol, $must_orders[$symbol]
                    )
                ) {
                    $api->createOrder(
                        $symbol, 'limit', 'sell',
                        $max_deal_amounts[$base_asset],
                        $spread_bot->incrementNumber($exchange_orderbook['ask'] - $market['price_increment'], $market['price_increment'])
                    );

                    Debug::printAll($debug_data, $balances[$exchange], $real_orders_for_symbol['sell'], $exchange);
                }

                foreach ($real_orders_for_symbol['sell'] as $real_orders_for_symbol_sell)
                    if (
                        ((count($real_orders_for_symbol['sell']) >= $must_orders[$symbol]['sell']) || ($real_orders_for_symbol_sell['price'] < $profit['ask'])) &&
                        TimeV2::up(5, $real_orders_for_symbol_sell['client_order_id'], true)
                    ) $api->cancelOrder($real_orders_for_symbol_sell);

                foreach ($real_orders_for_symbol['buy'] as $real_orders_for_symbol_buy)
                    if (
                        ((count($real_orders_for_symbol['buy']) >= $must_orders[$symbol]['buy']) || ($real_orders_for_symbol_buy['price'] > $profit['bid'])) &&
                        TimeV2::up(5, $real_orders_for_symbol_buy['client_order_id'], true)
                    ) $api->cancelOrder($real_orders_for_symbol_buy);

                $api->sendPingToLogServer($iteration++, 1, false);
            } elseif (TimeV2::up(1, 'empty_orderbooks' . $symbol)) {
                if (empty($orderbooks[$symbol][$exchange])) Debug::echo('[WARNING] Empty $orderbooks[$symbol][$exchange]');
                if (empty($orderbooks[$symbol][$market_discovery_exchange])) Debug::echo('[WARNING] Empty $orderbooks[$symbol][$market_discovery_exchange]');
            }
        }
    } elseif (TimeV2::up(1, 'empty_data')) Debug::echo('[WARNING] Empty $balances[$exchange]');

    if (TimeV2::up(5, 'balance')) $api->getBalances();
}
