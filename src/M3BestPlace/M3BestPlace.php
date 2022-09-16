<?php

namespace Src\M3BestPlace;

use Src\ApiV2;
use Src\FloatRound;
use Src\Main;
use Src\Signals\Delta;

class M3BestPlace extends Main
{

    private int $max_depth;
    private array $rates;
    private array $max_deal_amounts;
    private array $fees;
    private array $markets;
    private string $main_exchange;
    private string $delta_exchange;

    public function __construct(int $max_depth, array $rates, array $max_deal_amounts, array $fees, array $markets, string $main_exchange, string $delta_exchange = '')
    {

        $this->max_depth = $max_depth;
        $this->rates = $rates;
        $this->max_deal_amounts = $max_deal_amounts;
        $this->fees = $fees;
        $this->markets = $markets;
        $this->main_exchange = $main_exchange;
        $this->delta_exchange = $delta_exchange;

    }

    public function run(array $routes, array $balances, array $orderbooks, Delta $delta_signal): array
    {

        foreach ($routes as $route) {

            $combinations = $this->getCombinations($route);

            if ($best_orderbooks = $this->findBestOrderbooks($route, $orderbooks, $delta_signal)) {

                if ($orderbook = $this->getOrderbook($combinations, $best_orderbooks)) {

                    if (
                        ($balances[$orderbook['step_one']['exchange']][$combinations['main_asset_name']]['free'] > $this->max_deal_amounts[$combinations['main_asset_name']]) ||
                        ($balances[$orderbook['step_two']['exchange']][$combinations['asset_one_name']]['free'] > $this->max_deal_amounts[$combinations['asset_one_name']]) ||
                        ($balances[$orderbook['step_three']['exchange']][$combinations['asset_two_name']]['free'] > $this->max_deal_amounts[$combinations['asset_two_name']])
                    ) {

                        $results[] = $this->getResults(
                            $this->max_depth,
                            $this->rates,
                            $this->max_deal_amounts,
                            $combinations,
                            $orderbook,
                            [
                                $combinations['main_asset_name'] => $balances[$orderbook['step_one']['exchange']][$combinations['main_asset_name']],
                                $combinations['asset_one_name'] => $balances[$orderbook['step_two']['exchange']][$combinations['asset_one_name']],
                                $combinations['asset_two_name'] => $balances[$orderbook['step_three']['exchange']][$combinations['asset_two_name']],
                            ]
                        );

                    }

                }

            }

        }

        return $results ?? [];

    }

    public function getFullInfoByResult(array $result, float $profit): array
    {

        if (isset($result['results'][0])) {

            $full_info = $result['results'][0];

            if ($full_info['result_in_main_asset'] >= $profit)
                return $full_info;

        }

        return [];

    }

    public function getPositions(array $full_info): array
    {

        foreach (['step_one', 'step_two', 'step_three'] as $item) {

            $positions[$item] = [
                'symbol' => $full_info[$item]['amountAsset'] . '/' . $full_info[$item]['priceAsset'],
                'type' => 'limit',
                'side' => $full_info[$item]['orderType'],
                'amount' => $full_info[$item]['amount'],
                'price' => $full_info[$item]['price']
            ];

        }

        return $positions ?? [];

    }

    public function hasSimilarOrder(string $exchange, array $real_orders, array $positions): bool
    {

        if (isset($real_orders[$exchange])) {

            foreach ($positions as $position) {

                foreach ($real_orders[$exchange] as $real_order) {

                    if (
                        $position['symbol'] == $real_order['symbol'] &&
                        $position['side'] == $real_order['side'] &&
                        FloatRound::compare($position['price'], $real_order['price'])
                    )
                        return true;

                }

            }

        }

        return false;

    }

    public function create3MBestPlaceOrders(ApiV2 $api, array $positions, array $full_info)
    {

        foreach ($positions as $position) {

            echo '[' . date('Y-m-d H:i:s') . '] ' . $position['symbol'] . ' ' . $position['side'] . ' ' . $position['amount'] . ' ' . $position['price'] . PHP_EOL;

            $api->createOrder($position['symbol'], $position['type'], $position['side'], $position['amount'], $position['price'], false);

        }

        $api->sendExpectedTriangleToLogServer($full_info);

    }

    public function cancelExpiredOpenOrders(ApiV2 $api, string $exchange, array $real_orders, float $expired_open_order)
    {

        if (isset($real_orders[$exchange])) {

            foreach ($real_orders[$exchange] as $real_order) {

                if ((microtime(true) - $real_order['timestamp'] / 1000) >= $expired_open_order) {

                    echo '[' . date('Y-m-d H:i:s') . '] Cancel Order: ' . $real_order['client_order_id'] . PHP_EOL;

                    $api->cancelOrder($real_order['client_order_id'], $real_order['symbol'], false);

                }

            }

        }


    }

    private function getOrderbook(array $combinations, array $best_orderbooks): array
    {

        foreach (
            ['step_one' => 'step_one_symbol', 'step_two' => 'step_two_symbol', 'step_three' => 'step_three_symbol'] as $step => $step_symbol
        ) {

            if (isset($best_orderbooks[$combinations[$step_symbol]]['exchange'])) {

                foreach (
                    $this->markets[$best_orderbooks[$combinations[$step_symbol]]['exchange']] as $market
                ) {

                    if ($market['common_symbol'] == $combinations[$step_symbol]) {

                        $market_config = $market;

                        break;

                    }

                }

                if (isset($market_config)) {

                    if (isset($best_orderbooks[$combinations[$step_symbol]]['bids'][0][0]))
                        $best_orderbooks[$combinations[$step_symbol]]['bids'][0][0] = $market_config['price_increment'] * floor($best_orderbooks[$combinations[$step_symbol]]['bids'][0][0] / $market_config['price_increment']);

                    if (isset($best_orderbooks[$combinations[$step_symbol]]['asks'][0][0]))
                        $best_orderbooks[$combinations[$step_symbol]]['asks'][0][0] = $market_config['price_increment'] * floor($best_orderbooks[$combinations[$step_symbol]]['asks'][0][0] / $market_config['price_increment']);

                    $orderbook[$step] = [
                        'bids' => $best_orderbooks[$combinations[$step_symbol]]['bids'] ?? [],
                        'asks' => $best_orderbooks[$combinations[$step_symbol]]['asks'] ?? [],
                        'symbol' => $combinations[$step_symbol],
                        'limits' => $market_config['limits'] ?? [],
                        'price_increment' => $market_config['price_increment'] ?? 0,
                        'amount_increment' => $market_config['amount_increment'] ?? 0,
                        'amountAsset' => $market_config['base_asset'] ?? '',
                        'priceAsset' => $market_config['quote_asset'] ?? '',
                        'exchange' => $best_orderbooks[$combinations[$step_symbol]]['exchange'],
                        'fee' => $this->fees[$best_orderbooks[$combinations[$step_symbol]]['exchange']],
                    ];

                    unset($market_config);

                } else {

                    return [];

                }

            } else {

                return [];

            }

        }

        return $orderbook ?? [];

    }

    private function findBestOrderbooks(array $route, array $orderbooks, Delta $delta_signal): array
    {

        $best_orderbooks = [];

        foreach ($route as $source) {

            if (isset($orderbooks[$source['common_symbol']][$this->main_exchange])) {

                list($base_asset) = explode('/', $source['common_symbol']);

                $operation = ($source['operation'] == 'sell') ? 'bids' : 'asks';

                $k = 1 + ($delta_signal->getDelta($source['common_symbol'], $this->delta_exchange) ?? 0);

                $best_orderbooks[$source['common_symbol']] = [
                    $operation => [[$orderbooks[$source['common_symbol']][$this->main_exchange][($operation == 'bids') ? 'asks' : 'bids'][0][0] * $k, $this->max_deal_amounts[$base_asset] * 10]],
                    'exchange' => $this->main_exchange
                ];

                if ($this->delta_exchange) {



                }

            }

        }

        return (count($best_orderbooks) == 3) ? $best_orderbooks : [];

    }

    private function getCombinations(array $route): array
    {

        $step_one = array_shift($route);
        $step_two = array_shift($route);
        $step_three = array_shift($route);

        return [
            'main_asset_name' => $step_one['source_asset'],
            'main_asset_amount_precision' => 0.00000001,
            'asset_one_name' => $step_two['source_asset'],
            'asset_two_name' => $step_three['source_asset'],
            'step_one_symbol' => $step_one['common_symbol'],
            'step_two_symbol' => $step_two['common_symbol'],
            'step_three_symbol' => $step_three['common_symbol'],
        ];

    }

}