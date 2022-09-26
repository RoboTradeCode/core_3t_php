<?php

use Src\ApiV2;
use Src\CapitalRule\LimitationBalance;
use Src\Configurator;
use Src\Debug;
use Src\Filter;
use Src\FloatRound;
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

$spread_bot = new SpreadBot();

$iteration = 0;

while (true) {
    usleep($sleep);

    $all_data = $multi_core->reformatAndSeparateData($memcached->getMulti($multi_core->keys));

    [$balances, $orderbooks, $real_orders] = [$all_data['balances'], $all_data['orderbooks'], $all_data['orders']];

    $symbol = 'BTC/USDT';

    if (!empty($balances[$exchange])) {
        $count_orders = LimitationBalance::get($balances[$exchange], $assets, $common_symbols, $max_deal_amounts, $amount_limitations);

        if (
            !empty($orderbooks[$symbol][$exchange]) &&
            !empty($orderbooks[$symbol][$market_discovery_exchange])
        ) {

            $market = getMarket($markets[$exchange], $symbol);

            $market_discovery['bid'] = $orderbooks[$symbol][$market_discovery_exchange]['bids'][0][0];
            $market_discovery['ask'] = $orderbooks[$symbol][$market_discovery_exchange]['asks'][0][0];

            $profit['bid'] = $market_discovery['bid'] - ($market_discovery['bid'] * $min_profit['bid'] / 100);
            $profit['ask'] = $market_discovery['ask'] + ($market_discovery['ask'] * $min_profit['ask'] / 100);

            $exchange_orderbook['bid'] = $orderbooks[$symbol][$exchange]['bids'][0][0];
            $exchange_orderbook['ask'] = $orderbooks[$symbol][$exchange]['asks'][0][0];

            list($base_asset, $quote_asset) = explode('/', $symbol);

            $debug_data = [
                'symbol' => $symbol,
                'exchange_bid' => $exchange_orderbook['bid'],
                'exchange_ask' => $exchange_orderbook['ask'],
                'market_discovery_bid' => $market_discovery['bid'],
                'market_discovery_ask' => $market_discovery['ask'],
                'profit_bid' => $profit['bid'],
                'profit_ask' => $profit['ask'],
                'min_deal_amount_base_asset' => $min_deal_amounts[$base_asset] . ' ' . $base_asset,
                'min_deal_amount_quote_asset' => $min_deal_amounts[$quote_asset] . ' ' . $quote_asset,
                'is_exchange_bid_less_profit_bid' => $exchange_orderbook['bid'] <= $profit['bid'],
                'has_enough_balance_quote_asset' => $balances[$exchange][$quote_asset]['free'] >= $min_deal_amounts[$quote_asset],
                'is_exchange_ask_less_profit_ask' => $exchange_orderbook['ask'] >= $profit['ask'],
                'has_enough_balance_base_asset' => $balances[$exchange][$base_asset]['free'] >= $min_deal_amounts[$base_asset],
                'is_not_empty_real_orders' => !empty($real_orders[$exchange]),
            ];

            if (
                $exchange_orderbook['bid'] <= $profit['bid'] &&
                $balances[$exchange][$quote_asset]['free'] >= $min_deal_amounts[$quote_asset] &&
                TimeV2::up(2, 'create_order_buy_' . $symbol, true)
            ) {
                Debug::printAll($debug_data, $balances[$exchange], $real_orders[$exchange] ?? [], $exchange);

                $price = incrementNumber($exchange_orderbook['bid'] + $market['price_increment'], $market['price_increment']);

                $api->createOrder(
                    $symbol,
                    'limit',
                    'buy',
                    $price,
                    incrementNumber($balances[$exchange][$quote_asset]['free'] / $price, $market['amount_increment'])
                );
            }

            if (
                $exchange_orderbook['ask'] >= $profit['ask'] &&
                $balances[$exchange][$base_asset]['free'] >= $min_deal_amounts[$base_asset] &&
                TimeV2::up(2, 'create_order_sell_' . $symbol, true)
            ) {
                Debug::printAll($debug_data, $balances[$exchange], $real_orders[$exchange] ?? [], $exchange);

                $api->createOrder(
                    $symbol,
                    'limit',
                    'sell',
                    incrementNumber($exchange_orderbook['ask'] - $market['price_increment'], $market['price_increment']),
                    $balances[$exchange][$base_asset]['free']
                );
            }

            if (!empty($real_orders[$exchange])) {
                foreach ($real_orders[$exchange] as $real_order) {
                    $is_cancel_sell_order = $real_order['side'] == 'sell' &&
                        (!FloatRound::compare($real_order['price'], $exchange_orderbook['ask']) || ($real_order['price'] < $profit['ask'])) &&
                        TimeV2::up(5, $real_order['client_order_id'], true);

                    $is_cancel_buy_order = $real_order['side'] == 'buy' &&
                        (!FloatRound::compare($real_order['price'], $exchange_orderbook['bid']) || ($real_order['price'] > $profit['bid'])) &&
                        TimeV2::up(5, $real_order['client_order_id'], true);

                    if ($is_cancel_sell_order || $is_cancel_buy_order) {
                        Debug::printAll($debug_data, $balances[$exchange], $real_orders[$exchange], $exchange);

                        $api->cancelOrder($real_order);
                    }
                }
            }

            $api->sendPingToLogServer($iteration++, 1, false);
        } elseif (TimeV2::up(1, 'empty_orderbooks' . $symbol)) {
            if (empty($orderbooks[$symbol][$exchange])) echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Empty $orderbooks[$symbol][$exchange]' . PHP_EOL;
            if (empty($orderbooks[$symbol][$market_discovery_exchange])) echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Empty $orderbooks[$symbol][$market_discovery_exchange]' . PHP_EOL;
        }
    } elseif (TimeV2::up(1, 'empty_data')) echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Empty $balances[$exchange]' . PHP_EOL;

    if (TimeV2::up(5, 'balance')) $api->getBalances();
}

function incrementNumber(float $number, float $increment): float
{

    return $increment * floor($number / $increment);

}

function getMarket(array $markets, string $symbol)
{

    return $markets[array_search($symbol, array_column($markets, 'common_symbol'))] ?? [];

}
