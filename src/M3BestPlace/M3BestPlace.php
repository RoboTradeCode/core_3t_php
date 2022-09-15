<?php

namespace Src\M3BestPlace;

use Src\ApiV2;
use Src\FloatRound;
use Src\Main;

class M3BestPlace extends Main
{

    private int $max_depth;
    private array $rates;
    private array $max_deal_amounts;
    private array $fees;
    private array $markets;

    public function __construct(int $max_depth, array $rates, array $max_deal_amounts, array $fees, array $markets)
    {

        $this->max_depth = $max_depth;
        $this->rates = $rates;
        $this->max_deal_amounts = $max_deal_amounts;
        $this->fees = $fees;
        $this->markets = $markets;

    }

    public function run(array $routes, array $balances, array $orderbooks, bool $multi = false): array
    {

        foreach ($routes as $route) {

            $combinations = $this->getCombinations($route);

            if ($best_orderbooks = $this->findBestOrderbooks($route, $balances, $orderbooks)) {

                if ($orderbook = $this->getOrderbook($combinations, $best_orderbooks, $multi)) {

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

    private function getOrderbook(array $combinations, array $best_orderbooks, bool $multi): array
    {

        foreach (
            ['step_one' => 'step_one_symbol', 'step_two' => 'step_two_symbol', 'step_three' => 'step_three_symbol'] as $step => $step_symbol
        ) {

            if (isset($best_orderbooks[$combinations[$step_symbol]]['exchange'])) {

                $markets = $multi
                    ? $this->markets[$best_orderbooks[$combinations[$step_symbol]]['exchange']]
                    : $this->markets;

                foreach ($markets as $market) {

                    if ($market['common_symbol'] == $combinations[$step_symbol]) {

                        $market_config = $market;

                        break;

                    }

                }

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

            } else {

                return [];

            }

        }

        return $orderbook ?? [];

    }

    private function findBestOrderbooks(array $route, array $balances, array $orderbooks): array
    {

        $best_orderbooks = [];

        foreach ($route as $source) {

            $deal_amount_potential = $this->max_deal_amounts[$source['source_asset']];

            $operation = ($source['operation'] == 'sell') ? 'bids' : 'asks';

            $potential_amounts = [];

            // если не существует такого ордербука, возвращай пустой массив
            if (!isset($orderbooks[$source['common_symbol']]))
                return [];

            foreach ($orderbooks[$source['common_symbol']] as $exchange => $orderbook) {

                if (isset($balances[$exchange][$source['source_asset']])) {

                    $amount = 0;

                    if ($operation == 'bids') {

                        $base_asset_amount = 0;

                        foreach ($orderbook[$operation] as $price_and_amount) {

                            if (($base_asset_amount + $price_and_amount[1]) < $deal_amount_potential) {

                                $amount += $price_and_amount[0] * $price_and_amount[1];

                                $base_asset_amount += $price_and_amount[1];

                            } else {

                                $amount += $price_and_amount[0] * ($deal_amount_potential - $base_asset_amount);

                                break;

                            }

                        }

                    } else {

                        $quote_asset_amount = 0;

                        foreach ($orderbook[$operation] as $price_and_amount) {

                            if (($quote_asset_amount + $price_and_amount[0] * $price_and_amount[1]) < $deal_amount_potential) {

                                $amount += $price_and_amount[1];

                                $quote_asset_amount += $price_and_amount[0] * $price_and_amount[1];

                            } else {

                                $amount += ($deal_amount_potential - $quote_asset_amount) / $price_and_amount[0];

                                break;

                            }

                        }

                    }

                    $potential_amounts[$exchange] = $amount * (1 - $this->fees[$exchange] / 100);

                }

            }

            if ($potential_amounts) {

                $best_exchange = array_keys($potential_amounts, max($potential_amounts))[0];

                $best_orderbooks[$source['common_symbol']] = [
                    $operation => $orderbooks[$source['common_symbol']][$best_exchange][$operation],
                    'exchange' => $best_exchange
                ];

                list($base_asset) = explode('/', $source['common_symbol']);

                array_unshift($best_orderbooks[$source['common_symbol']][$operation] , [$orderbooks[$source['common_symbol']][$best_exchange][($operation == 'bids') ? 'asks' : 'bids'][0][0], $this->max_deal_amounts[$base_asset] * 1.05]);

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