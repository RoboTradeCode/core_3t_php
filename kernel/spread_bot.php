<?php

use Src\ApiV2;
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
$fees = $core_config['fees'];
$rates = $core_config['rates'];
$publishers = $core_config['aeron']['publishers'];
$markets[$exchange] = $config['markets'];

$min_deal_amounts = Filter::getMaxDealAmountByRate($rates, $min_deal_amount);

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

    if (
        !empty($balances[$exchange]) &&
        !empty($orderbooks[$symbol][$exchange]) &&
        !empty($orderbooks[$symbol][$market_discovery_exchange])
    ) {
        $market = getMarket($markets[$exchange], $symbol);

        $market_discovery['bid'] = $orderbooks[$symbol][$market_discovery_exchange]['bids'][0][0];
        $market_discovery['ask'] = $orderbooks[$symbol][$market_discovery_exchange]['asks'][0][0];

        $profit['bid'] = $market_discovery['bid'] - ($market_discovery['bid'] * $min_profit['bid'] / 100);
        $profit['ask'] = $market_discovery['ask'] - ($market_discovery['ask'] * $min_profit['ask'] / 100);

        $exchange_orderbook['bid'] = $orderbooks[$symbol][$market_discovery_exchange]['bids'][0][0];
        $exchange_orderbook['ask'] = $orderbooks[$symbol][$market_discovery_exchange]['asks'][0][0];

        list($base_asset, $quote_asset) = explode('/', $symbol);

        if ($exchange_orderbook['bid'] <= $profit['bid']) {
            if ($balances[$exchange][$quote_asset]['free'] >= $min_deal_amounts[$quote_asset]) {
                if (TimeV2::up(2, 'create_order_buy', true)) {
                    Debug::printAll(
                        [
                            'symbol' => $symbol,
                            'exchange_bid' => $exchange_orderbook['bid'],
                            'exchange_ask' => $exchange_orderbook['ask'],
                            'market_discovery_bid' => $market_discovery['bid'],
                            'market_discovery_ask' => $market_discovery['ask'],
                            'profit_bid' => $profit['bid'],
                            'profit_ask' => $profit['ask'],
                            'min_deal_amount_base_asset' => $min_deal_amounts[$base_asset],
                            'min_deal_amount_quote_asset' => $min_deal_amounts[$quote_asset],
                            'is_exchange_bid_less_profit_bid' => $exchange_orderbook['bid'] <= $profit['bid'],
                            'has_enough_balance_quote_asset' => $balances[$exchange][$quote_asset]['free'] >= $min_deal_amounts[$quote_asset],
                            'is_exchange_ask_less_profit_ask' => $exchange_orderbook['ask'] >= $profit['ask'],
                            'has_enough_balance_base_asset' => $balances[$exchange][$base_asset]['free'] >= $min_deal_amounts[$base_asset],
                            'is_empty_real_orders' => !empty($real_orders[$exchange]),
                        ],
                        $balances[$exchange],
                        $real_orders[$exchange],
                        $exchange
                    );

                    $type = 'limit';
                    $side = 'buy';
                    $price = incrementNumber($exchange_orderbook['bid'] + $market['price_increment'], $market['price_increment']);
                    $amount = incrementNumber($balances[$exchange][$quote_asset]['free'] / $price, $market['amount_increment']);

                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Create: ' . $symbol . ': ' . $side . ': ' . $price . ': ' . $amount . PHP_EOL;

                    $api->createOrder($symbol, $type, $side, $price, $amount);
                }
            }
        }

        if ($exchange_orderbook['ask'] >= $profit['ask']) {
            if ($balances[$exchange][$base_asset]['free'] >= $min_deal_amounts[$base_asset]) {
                if (TimeV2::up(2, 'create_order_sell', true)) {
                    Debug::printAll(
                        [
                            'symbol' => $symbol,
                            'exchange_bid' => $exchange_orderbook['bid'],
                            'exchange_ask' => $exchange_orderbook['ask'],
                            'market_discovery_bid' => $market_discovery['bid'],
                            'market_discovery_ask' => $market_discovery['ask'],
                            'profit_bid' => $profit['bid'],
                            'profit_ask' => $profit['ask'],
                            'min_deal_amount_base_asset' => $min_deal_amounts[$base_asset],
                            'min_deal_amount_quote_asset' => $min_deal_amounts[$quote_asset],
                            'is_exchange_bid_less_profit_bid' => $exchange_orderbook['bid'] <= $profit['bid'],
                            'has_enough_balance_quote_asset' => $balances[$exchange][$quote_asset]['free'] >= $min_deal_amounts[$quote_asset],
                            'is_exchange_ask_less_profit_ask' => $exchange_orderbook['ask'] >= $profit['ask'],
                            'has_enough_balance_base_asset' => $balances[$exchange][$base_asset]['free'] >= $min_deal_amounts[$base_asset],
                            'is_empty_real_orders' => !empty($real_orders[$exchange]),
                        ],
                        $balances[$exchange],
                        $real_orders[$exchange] ?? [],
                        $exchange
                    );

                    $type = 'limit';
                    $side = 'sell';
                    $price = incrementNumber($exchange_orderbook['ask'] - $market['price_increment'], $market['price_increment']);
                    $amount = incrementNumber($balances[$exchange][$base_asset]['free'] / $price, $market['amount_increment']);

                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Create: ' . $symbol . ': ' . $side . ': ' . $price . ': ' . $amount . PHP_EOL;

                    $api->createOrder($symbol, $type, $side, $price, $amount);
                }
            }
        }

        if (!empty($real_orders[$exchange])) {
            foreach ($real_orders[$exchange] as $real_order) {
                if (
                    $real_order['side'] == 'sell' &&
                    (!FloatRound::compare($real_order['price'], $exchange_orderbook['ask']) || ($real_order['price'] < $profit['ask'])) &&
                    TimeV2::up(3, $real_order['client_order_id'], true)
                ) {
                    Debug::printAll(
                        [
                            'symbol' => $symbol,
                            'exchange_bid' => $exchange_orderbook['bid'],
                            'exchange_ask' => $exchange_orderbook['ask'],
                            'market_discovery_bid' => $market_discovery['bid'],
                            'market_discovery_ask' => $market_discovery['ask'],
                            'profit_bid' => $profit['bid'],
                            'profit_ask' => $profit['ask'],
                            'min_deal_amount_base_asset' => $min_deal_amounts[$base_asset],
                            'min_deal_amount_quote_asset' => $min_deal_amounts[$quote_asset],
                            'is_exchange_bid_less_profit_bid' => $exchange_orderbook['bid'] <= $profit['bid'],
                            'has_enough_balance_quote_asset' => $balances[$exchange][$quote_asset]['free'] >= $min_deal_amounts[$quote_asset],
                            'is_exchange_ask_less_profit_ask' => $exchange_orderbook['ask'] >= $profit['ask'],
                            'has_enough_balance_base_asset' => $balances[$exchange][$base_asset]['free'] >= $min_deal_amounts[$base_asset],
                            'is_empty_real_orders' => !empty($real_orders[$exchange]),
                        ],
                        $balances[$exchange],
                        $real_orders[$exchange] ?? [],
                        $exchange
                    );

                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Cancel: ' . $real_order['client_order_id'] . ': ' . $symbol . ': ' . $real_order['price'] . ': ' . $real_order['side'] . PHP_EOL;

                    $api->cancelOrder($real_order['client_order_id'], $real_order['symbol'], false);
                }

                if (
                    $real_order['side'] == 'buy' &&
                    (!FloatRound::compare($real_order['price'], $exchange_orderbook['bid']) || ($real_order['price'] > $profit['bid'])) &&
                    TimeV2::up(3, $real_order['client_order_id'], true)
                ) {
                    Debug::printAll(
                        [
                            'symbol' => $symbol,
                            'exchange_bid' => $exchange_orderbook['bid'],
                            'exchange_ask' => $exchange_orderbook['ask'],
                            'market_discovery_bid' => $market_discovery['bid'],
                            'market_discovery_ask' => $market_discovery['ask'],
                            'profit_bid' => $profit['bid'],
                            'profit_ask' => $profit['ask'],
                            'min_deal_amount_base_asset' => $min_deal_amounts[$base_asset],
                            'min_deal_amount_quote_asset' => $min_deal_amounts[$quote_asset],
                            'is_exchange_bid_less_profit_bid' => $exchange_orderbook['bid'] <= $profit['bid'],
                            'has_enough_balance_quote_asset' => $balances[$exchange][$quote_asset]['free'] >= $min_deal_amounts[$quote_asset],
                            'is_exchange_ask_less_profit_ask' => $exchange_orderbook['ask'] >= $profit['ask'],
                            'has_enough_balance_base_asset' => $balances[$exchange][$base_asset]['free'] >= $min_deal_amounts[$base_asset],
                            'is_empty_real_orders' => !empty($real_orders[$exchange]),
                        ],
                        $balances[$exchange],
                        $real_orders[$exchange] ?? [],
                        $exchange
                    );

                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Cancel: ' . $real_order['client_order_id'] . ': ' . $symbol . ': ' . $real_order['price'] . ': ' . $real_order['side'] . PHP_EOL;

                    $api->cancelOrder($real_order['client_order_id'], $real_order['symbol'], false);
                }
            }
        }

        $market_discovery['bid'] = $orderbooks[$symbol][$market_discovery_exchange]['bids'][0][0];
        $market_discovery['ask'] = $orderbooks[$symbol][$market_discovery_exchange]['asks'][0][0];

        $profit['bid'] = $market_discovery['bid'] - ($market_discovery['bid'] * $min_profit['bid'] / 100);
        $profit['ask'] = $market_discovery['ask'] - ($market_discovery['ask'] * $min_profit['ask'] / 100);

        $exchange_orderbook['bid'] = $orderbooks[$symbol][$market_discovery_exchange]['bids'][0][0];
        $exchange_orderbook['ask'] = $orderbooks[$symbol][$market_discovery_exchange]['asks'][0][0];

        if (TimeV2::up(5, 'algo_info'))
            Debug::printAll(
                [
                    'symbol' => $symbol,
                    'exchange_bid' => $exchange_orderbook['bid'],
                    'exchange_ask' => $exchange_orderbook['ask'],
                    'market_discovery_bid' => $market_discovery['bid'],
                    'market_discovery_ask' => $market_discovery['ask'],
                    'profit_bid' => $profit['bid'],
                    'profit_ask' => $profit['ask'],
                    'min_deal_amount_base_asset' => $min_deal_amounts[$base_asset],
                    'min_deal_amount_quote_asset' => $min_deal_amounts[$quote_asset],
                    'is_exchange_bid_less_profit_bid' => $exchange_orderbook['bid'] <= $profit['bid'],
                    'has_enough_balance_quote_asset' => $balances[$exchange][$quote_asset]['free'] >= $min_deal_amounts[$quote_asset],
                    'is_exchange_ask_less_profit_ask' => $exchange_orderbook['ask'] >= $profit['ask'],
                    'has_enough_balance_base_asset' => $balances[$exchange][$base_asset]['free'] >= $min_deal_amounts[$base_asset],
                    'is_empty_real_orders' => !empty($real_orders[$exchange]),
                ],
                $balances,
                $real_orders[$exchange] ?? [],
                $exchange
            );

        $api->sendPingToLogServer($iteration++, 1, false);
    } else {
        if (empty($balances[$exchange]))
            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Empty $balances[$exchange]' . PHP_EOL;

        if (empty($orderbooks[$symbol][$exchange]))
            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Empty $orderbooks[$symbol][$exchange]' . PHP_EOL;

        if (empty($orderbooks[$symbol][$market_discovery_exchange]))
            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Empty $orderbooks[$symbol][$market_discovery_exchange]' . PHP_EOL;
    }
}

function incrementNumber(float $number, float $increment): float
{

    return $increment * floor($number / $increment);

}

function getMarket(array $markets, string $symbol)
{

    return $markets[array_search($symbol, array_column($markets, 'common_symbol'))] ?? [];

}
