<?php

namespace Src;

class Cross3T extends Main
{

    private array $config;

    /**
     * @param array $config Вся конфигурация приходящяя от агента
     */
    public function __construct(array $config)
    {

        $this->config = $config;

        $this->combinations = json_decode(file_get_contents(dirname(__DIR__) . '/cache/triangles.json'), true);

    }

    /**
     * Метод находит самые выгодные ордербуки со всех бирж
     *
     * @param array $route Треугольник, приходящи й от конфигуратора
     * @param array $balances Балансы, с разных бирж
     * @param array $orderbooks Ордербуки, с разных бирж
     * @return array Лучшие найденные ордербуки
     */
    public function findBestOrderbooks(array $route, array $balances, array $orderbooks): array
    {

        foreach ($route as $source) {

            $deal_amount_potential = $balances[$this->config['exchange']][$source['source_asset']]['free'] ?? $this->config['max_deal_amounts'][$source['source_asset']];

            $operation = ($source['operation'] == 'sell') ? 'bids' : 'asks';

            $potential_amounts = [];

            foreach ($orderbooks[$source['common_symbol']] as $exchange => $orderbook) {

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

                $potential_amounts[$exchange] = $amount;

            }

            $bes_exchange = array_keys($potential_amounts, min($potential_amounts))[0];

            $best_orderbooks[$source['common_symbol']] = [
                $operation => $orderbooks[$source['common_symbol']][$bes_exchange][$operation],
                'exchange' => $bes_exchange
            ];

        }

        return $best_orderbooks ?? [];

    }

    /**
     * Фильтрует баланс, чтобы он был в диапазоне min_deal_amount и max_deal_amount
     *
     * @param array $balances Балансы со всех бирж, взятые из memcached
     * @return void
     */
    public function filterBalanceByMinAndMAxDealAmount(array &$balances)
    {

        foreach ($balances as $exchange => $balance) {

            foreach ($balance as $asset => $value) {

                if ($value['free'] <= $this->config['min_deal_amounts'][$asset]) {

                    unset($balances[$exchange][$asset]);

                } elseif ($value['free'] >= $this->config['max_deal_amounts'][$asset]) {

                    $balances[$exchange][$asset]['free'] = $this->config['max_deal_amounts'][$asset];

                }

            }

        }

    }

    /**
     * Возвращает данные из memcached в определенном формате и отделенные по ордербукам, балансам и т. д.
     *
     * @param array $memcached_data Сырые данные, взятые напрямую из memcached
     * @return array[]
     */
    public function reformatAndSeparateData(array $memcached_data): array
    {

        foreach ($memcached_data as $key => $data) {

            if (isset($data)) {

                $parts = explode('_', $key);

                $exchange = $parts[0];
                $action = $parts[1];
                $value = $parts[2] ?? null;

                if ($action == 'balances') {
                    $balances[$exchange] = $data;
                } elseif ($action == 'orderbook' && $value) {
                    $orderbooks[$value][$exchange] = $data;
                } else {
                    $undefined[$key] = $data;
                }

            }

        }

        return [
            'balances' => $balances ?? [],
            'orderbooks' => $orderbooks ?? [],
            'undefined' => $undefined ?? [],
        ];

    }

    /**
     * Проверяет пришел ли новый конфиг и обновляет текущий на новый
     *
     * @param array $config Текущая конфигурация
     * @param array $memcached_data Сырые данные, взятые напрямую из memcached
     * @return bool
     */
    public function proofConfigOnUpdate(array &$config, array &$memcached_data): bool
    {

        if (isset($memcached_data['config'])) {

            $config = $memcached_data['config'];

            unset($memcached_data['config']);

            echo '[Ok] Config is update' . PHP_EOL;

            return true;

        }

        return false;

    }

    public function getAllMemcachedKeys(): array
    {

        $keys = [];

        foreach ($this->config['exchanges'] as  $exchange)
            $keys = array_merge(
                $keys,
                preg_filter(
                    '/^/',
                    $exchange . '_orderbook',
                    array_column($this->config['markets'], 'common_symbol')
                ),
                [$exchange . '_balances'], // добавить еще к массиву ключ баланса
                [$exchange . '_orders'] // добавить еще к массиву ключ для получения ордеров
            );

        return $keys;

    }

    public function run($balances, $orderbooks, $rates, $current_symbol): bool
    {

        $combinations = $this->combinations[$current_symbol];

        $combinations_count = count($combinations);

        $this->runConstruct($balances, $orderbooks, $rates);

        if (!empty($rates) && !empty($balances)) {

            foreach ($rates as $key => $rate) {

                $rater[$key]['rate'] = round(1 / $rate[MAIN_ASSET], 8);
                $rater[$key]['free'] = $balances[$key]['free'];
                $rater[$key]['used'] = $balances[$key]['used'];
                $rater[$key]['total'] = $balances[$key]['total'];

            }

            /*            Array
                        (
                            [BTC] => Array
                            (
                                [exchange] => Kuna
                                [asset] => BTC
                                [free] => 0.00057544
                                [used] => 0.00000000
                                [total] => 0.00057544
                                [total_main_asset] => 0.00057544
                                [total_main_asset_free] => 0.00057544
                                [rate] => 1.00000000
                                [last_update] => 2021-10-07 18:58:39
                            )

                            [ETH] => Array
                            (
                                [exchange] => Kuna
                                [asset] => ETH
                                [free] => 0.22598019
                                [used] => 0.00000000
                                [total] => 0.22598019
                                [total_main_asset] => 0.01493334
                                [total_main_asset_free] => 0.01493334
                                [rate] => 15.13259940
                                [last_update] => 2021-10-07 18:58:39
                            )

                            [RUB] => Array
                            (
                                [exchange] => Kuna
                                [asset] => RUB
                                [free] => 74522.07735857
                                [used] => 0.00000000
                                [total] => 74522.07735857
                                [total_main_asset] => 0.01932213
                                [total_main_asset_free] => 0.01932213
                                [rate] => 3856825.60000000
                                [last_update] => 2021-10-07 18:58:39
                            )

                            [UAH] => Array
                            (
                                [exchange] => Kuna
                                [asset] => UAH
                                [free] => 17790.52703193
                                [used] => 0.00000000
                                [total] => 17790.52703193
                                [total_main_asset] => 0.01226207
                                [total_main_asset_free] => 0.01226207
                                [rate] => 1450858.00000000
                                [last_update] => 2021-10-07 18:58:39
                            )

                            [USDT] => Array
                            (
                                [exchange] => Kuna
                                [asset] => USDT
                                [free] => 1040.68642111
                                [used] => 0.00000000
                                [total] => 1040.68642111
                                [total_main_asset] => 0.01931567
                                [total_main_asset_free] => 0.01931567
                                [rate] => 53877.82000000
                                [last_update] => 2021-10-07 18:58:39
                            )

                        )*/

            if (isset($orderbooks)) {

                $markets_data = $orderbooks;

                $operations_count = 0;
                $orderbook = [];
                $plus_results = [];

                if (DEBUG_STATUS === true) $html = $this->CalcVisualizationHeader($orderbooks, $current_symbol); // dev only

                $cycle_time_start = hrtime(true);

                for ($i = 0; $i < $combinations_count; $i++) {

                    if (isset($markets_data[$combinations[$i]["step_one_symbol"]])) $orderbook["step_one"] = $markets_data[$combinations[$i]["step_one_symbol"]];
                    else continue;

                    if (isset($markets_data[$combinations[$i]["step_two_symbol"]])) $orderbook["step_two"] = $markets_data[$combinations[$i]["step_two_symbol"]];
                    else continue;

                    if (isset($markets_data[$combinations[$i]["step_three_symbol"]])) $orderbook["step_three"] = $markets_data[$combinations[$i]["step_three_symbol"]];
                    else continue;

                    // Step 1 constants
                    $stepOne_priceAsset = $orderbook["step_one"]["quote"];
                    $stepOne_amountAsset = $orderbook["step_one"]["base"];

                    $stepOne_amount_decimals = $combinations[$i]["step_one_amount_decimals"];
                    $stepOne_price_decimals = $combinations[$i]["step_one_price_decimals"];

                    // Step 2 constants
                    $stepTwo_amountAsset = $orderbook["step_two"]["base"];
                    $stepTwo_priceAsset = $orderbook["step_two"]["quote"];

                    $stepTwo_amount_decimals = $combinations[$i]["step_two_amount_decimals"];
                    $stepTwo_price_decimals = $combinations[$i]["step_two_price_decimals"];

                    // Step 3 constants
                    $stepThree_amountAsset = $orderbook["step_three"]["base"];
                    $stepThree_priceAsset = $orderbook["step_three"]["quote"];

                    $stepThree_amount_decimals = $combinations[$i]["step_three_amount_decimals"];
                    $stepThree_price_decimals = $combinations[$i]["step_three_price_decimals"];

                    $max_deal_amount = ($combinations[$i]["main_asset_name"] === MAIN_ASSET) ? DEAL_AMOUNT : round($rater[$combinations[$i]["main_asset_name"]]["rate"] * DEAL_AMOUNT, $combinations[$i]["step_one_amount_decimals"]);

                    $step_one_dom_position = $step_two_dom_position = $step_three_dom_position = 0;

                    $stepOne_buy_price = $stepOne_sell_price = $stepOne_buy_amount = $stepOne_sell_amount = 0;
                    $stepTwo_buy_price = $stepTwo_sell_price = $stepTwo_buy_amount = $stepTwo_sell_amount = 0;
                    $stepThree_buy_price = $stepThree_sell_price = $stepThree_buy_amount = $stepThree_sell_amount = 0;

                    $deal_amount = ["min" => 0, "step_one" => 0, "step_two" => 0, "step_three" => 0];

                    $depth = 0;

                    // DOM calculation
                    while (true) {

                        ###<DEAL VARIABLES>###
                        //Step 1
                        if ($this->format($deal_amount["step_one"]) < $this->format($max_deal_amount)) {

                            $stepOne_buy_price = (isset($orderbook["step_one"]["asks"][$step_one_dom_position]["0"])) ? $orderbook["step_one"]["asks"][$step_one_dom_position]["0"] : 0;
                            $stepOne_sell_price = (isset($orderbook["step_one"]["bids"][$step_one_dom_position]["0"])) ? $orderbook["step_one"]["bids"][$step_one_dom_position]["0"] : 0;

                            $stepOne_buy_amount += (isset($orderbook["step_one"]["asks"][$step_one_dom_position]["1"])) ? $orderbook["step_one"]["asks"][$step_one_dom_position]["1"] : 0;
                            $stepOne_sell_amount += (isset($orderbook["step_one"]["bids"][$step_one_dom_position]["1"])) ? $orderbook["step_one"]["bids"][$step_one_dom_position]["1"] : 0;

                            $step_one_dom_position++;
                        }

                        //Step 2
                        if ($this->format($deal_amount["step_two"]) < $this->format($max_deal_amount)) {

                            $stepTwo_buy_price = (isset($orderbook["step_two"]["asks"][$step_two_dom_position]["0"])) ? $orderbook["step_two"]["asks"][$step_two_dom_position]["0"] : 0;
                            $stepTwo_sell_price = (isset($orderbook["step_two"]["bids"][$step_two_dom_position]["0"])) ? $orderbook["step_two"]["bids"][$step_two_dom_position]["0"] : 0;

                            $stepTwo_buy_amount += (isset($orderbook["step_two"]["asks"][$step_two_dom_position]["1"])) ? $orderbook["step_two"]["asks"][$step_two_dom_position]["1"] : 0;
                            $stepTwo_sell_amount += (isset($orderbook["step_two"]["bids"][$step_two_dom_position]["1"])) ? $orderbook["step_two"]["bids"][$step_two_dom_position]["1"] : 0;

                            $step_two_dom_position++;
                        }

                        //Step 3
                        if ($this->format($deal_amount["step_three"]) < $this->format($max_deal_amount)) {

                            $stepThree_buy_price = (isset($orderbook["step_three"]["asks"][$step_three_dom_position]["0"])) ? $orderbook["step_three"]["asks"][$step_three_dom_position]["0"] : 0;
                            $stepThree_sell_price = (isset($orderbook["step_three"]["bids"][$step_three_dom_position]["0"])) ? $orderbook["step_three"]["bids"][$step_three_dom_position]["0"] : 0;

                            $stepThree_buy_amount += (isset($orderbook["step_three"]["asks"][$step_three_dom_position]["1"])) ? $orderbook["step_three"]["asks"][$step_three_dom_position]["1"] : 0;
                            $stepThree_sell_amount += (isset($orderbook["step_three"]["bids"][$step_three_dom_position]["1"])) ? $orderbook["step_three"]["bids"][$step_three_dom_position]["1"] : 0;

                            $step_three_dom_position++;
                        }
                        ###</DEAL VARIABLES>###

                        try {
                            $deal_amount = $this->DealAmount($max_deal_amount, $combinations[$i]["step_one_amount_decimals"], $combinations[$i]["main_asset_name"], $stepOne_amountAsset, $stepOne_priceAsset, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepThree_amountAsset, $stepThree_priceAsset, $stepOne_buy_price, $stepOne_sell_price, $stepOne_buy_amount, $stepOne_sell_amount, $stepTwo_buy_price, $stepTwo_sell_price, $stepTwo_buy_amount, $stepTwo_sell_amount, $stepThree_buy_price, $stepThree_sell_price, $stepThree_buy_amount, $stepThree_sell_amount);
                        } catch (DivisionByZeroError $e) {
                            echo "Failed DealAmount DivisionByZeroError: $max_deal_amount, {$combinations[$i]["step_one_amount_decimals"]}, {$combinations[$i]["main_asset_name"]}, $stepOne_amountAsset, $stepOne_priceAsset, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepThree_amountAsset, $stepThree_priceAsset, $stepOne_buy_price, $stepOne_sell_price, $stepOne_buy_amount, $stepOne_sell_amount, $stepTwo_buy_price, $stepTwo_sell_price, $stepTwo_buy_amount, $stepTwo_sell_amount, $stepThree_buy_price, $stepThree_sell_price, $stepThree_buy_amount, $stepThree_sell_amount" . PHP_EOL;
                            continue 2;
                        }

                        // dev only
//                        echo "DEAL AMOUNT: $max_deal_amount, {$combinations[$i]["step_one_amount_decimals"]}, {$combinations[$i]["main_asset_name"]}, $stepOne_amountAsset, $stepOne_priceAsset, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepThree_amountAsset, $stepThree_priceAsset, $stepOne_buy_price, $stepOne_sell_price, $stepOne_buy_amount, $stepOne_sell_amount, $stepTwo_buy_price, $stepTwo_sell_price, $stepTwo_buy_amount, $stepTwo_sell_amount, $stepThree_buy_price, $stepThree_sell_price, $stepThree_buy_amount, $stepThree_sell_amount" . PHP_EOL;

                        $result = $this->getResult($orderbook, $this->markets, $deal_amount["min"], $rater, FEE_TAKER, $combinations[$i], $stepOne_amountAsset, $stepOne_amount_decimals, $stepOne_sell_price, $stepOne_price_decimals, $stepOne_buy_price, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepTwo_amount_decimals, $stepTwo_sell_price, $stepTwo_price_decimals, $stepTwo_buy_price, $stepThree_amountAsset, $stepThree_sell_price, $stepThree_price_decimals, $stepThree_buy_price, $stepThree_amount_decimals, $step_one_dom_position, $step_two_dom_position, $step_three_dom_position, $stepOne_sell_amount, $stepOne_buy_amount, $stepTwo_sell_amount, $stepTwo_buy_amount, $stepThree_sell_amount, $stepThree_buy_amount, $max_deal_amount);

                        //dev only
//                        echo "RESULT: $orderbook, $this->markets, {$deal_amount["min"]}, $rater, FEE_TAKER, {$combinations[$i]}, $stepOne_amountAsset, $stepOne_amount_decimals, $stepOne_sell_price, $stepOne_price_decimals, $stepOne_buy_price, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepTwo_amount_decimals, $stepTwo_sell_price, $stepTwo_price_decimals, $stepTwo_buy_price, $stepThree_amountAsset, $stepThree_sell_price, $stepThree_price_decimals, $stepThree_buy_price, $stepThree_amount_decimals, $step_one_dom_position, $step_two_dom_position, $step_three_dom_position, $stepOne_sell_amount, $stepOne_buy_amount, $stepTwo_sell_amount, $stepTwo_buy_amount, $stepThree_sell_amount, $stepThree_buy_amount, $max_deal_amount" . PHP_EOL;

                        $operations_count++;

                        if ($result["status"] === true && $result["result_in_main_asset"] > MIN_PROFIT) {
                            $plus_results[$i] = $result;
                            $plus_results[$i]["info"] = $combinations[$i];
                        }

                        // dev only
                        if (DEBUG_STATUS === true && $result["status"] === true) $html .= $this->CalcVisualizationBody($operations_count, $step_one_dom_position, $step_two_dom_position, $step_three_dom_position, $result, $max_deal_amount, $deal_amount["min"], FEE_TAKER, $combinations[$i], $stepOne_sell_amount, $stepTwo_sell_amount, $stepOne_buy_amount, $stepTwo_buy_amount, $stepThree_buy_amount, $stepThree_sell_amount, $stepOne_amountAsset, $stepOne_amount_decimals, $stepOne_sell_price, $stepOne_price_decimals, $stepOne_buy_price, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepTwo_amount_decimals, $stepTwo_sell_price, $stepTwo_price_decimals, $stepTwo_buy_price, $stepThree_amountAsset, $stepThree_sell_price, $stepThree_price_decimals, $stepThree_buy_price, $stepThree_amount_decimals);

                        if ($depth++ > MAX_DEPTH) {
                            $reason = "Maximum depth";
                            break;
                        } elseif ($combinations[$i]["step_one_order_type"] === "sell" && !isset($orderbook["step_one"]["bids"][$step_one_dom_position]["1"])) {
                            $reason = "End of DOM (step 1, bids, position $step_one_dom_position). Pair: $stepOne_amountAsset/$stepOne_priceAsset";
                            break;
                        } elseif ($combinations[$i]["step_one_order_type"] === "buy" && !isset($orderbook["step_one"]["asks"][$step_one_dom_position]["1"])) {
                            $reason = "End of DOM (step 1, asks, position $step_one_dom_position). Pair: $stepOne_amountAsset/$stepOne_priceAsset";
                            break;
                        } elseif ($combinations[$i]["step_two_order_type"] === "sell" && !isset($orderbook["step_two"]["bids"][$step_two_dom_position]["1"])) {
                            $reason = "End of DOM (step 2, bids, position $step_two_dom_position). Pair: $stepTwo_amountAsset/$stepTwo_priceAsset, amount: $stepTwo_sell_amount, price: $stepTwo_sell_price.";
                            break;
                        } elseif ($combinations[$i]["step_two_order_type"] === "buy" && !isset($orderbook["step_two"]["asks"][$step_two_dom_position]["1"])) {
                            $reason = "End of DOM (step 2, asks, position $step_two_dom_position). Pair: $stepTwo_amountAsset/$stepTwo_priceAsset, amount: $stepTwo_buy_amount, price: $stepTwo_buy_price.";
                            break;
                        } elseif ($combinations[$i]["step_three_order_type"] === "sell" && !isset($orderbook["step_three"]["bids"][$step_three_dom_position]["1"])) {
                            $reason = "End of DOM (step 3, bids, position $step_three_dom_position). Pair: $stepThree_amountAsset/$stepThree_priceAsset";
                            break;
                        } elseif ($combinations[$i]["step_three_order_type"] === "buy" && !isset($orderbook["step_three"]["asks"][$step_three_dom_position]["1"])) {
                            $reason = "End of DOM (step 3, asks, position $step_three_dom_position). Pair: $stepThree_amountAsset/$stepThree_priceAsset";
                            break;
                        } elseif ($this->format($deal_amount["min"]) >= $this->format($max_deal_amount)) {
                            $reason = "Maximum reached";
                            break;
                        }

                    }
                    // dev only
                    if (DEBUG_STATUS === true) $html .= $this->CalcVisualizationDelimeter($reason);

                }

                $cycle_time = round((hrtime(true) - $cycle_time_start) / 1000);

                if (count($plus_results) > 0) {

                    /*            Array
                                (
                                    [result] => 1
                                    [result_in_main_asset] => 1
                                    [status] => 1
                                    [reason] =>
                                    [deal_amount] => 0
                                    [main_asset_name] => BTC
                                    [asset_one_name] => ETH
                                    [asset_two_name] => USDT
                                    [step_one] => Array
                                        (
                                        [orderType] => buy
                                        [dom_position] => 10
                                        [amountAsset] => ETH
                                        [priceAsset] => BTC
                                        [amountAssetName] => ETH
                                        [priceAssetName] => BTC
                                        [amount] => 0
                                        [price] => 0.0716
                                        [result] => 1
                                    )

                                    [step_two] => Array
                                        (
                                        [orderType] => sell
                                        [dom_position] => 2
                                        [amountAsset] => ETH
                                        [priceAsset] => USDT
                                        [amountAssetName] => ETH
                                        [priceAssetName] => USDT
                                        [amount] => 1
                                        [price] => 3584.41
                                        [result] => 1
                                    )

                                    [step_three] => Array
                                        (
                                        [orderType] => buy
                                        [dom_position] => 2
                                        [amountAsset] => BTC
                                        [priceAsset] => USDT
                                        [amountAssetName] => BTC
                                        [priceAssetName] => USDT
                                        [amount] => 1.8366808678831E-5
                                        [price] => 54446.04
                                        [result] => 1
                                    )

                                    [expected_data] => Array
                                        (
                                        [fee] => 0
                                        [stepOne_sell_price] => 0.062748
                                        [stepOne_sell_amount] => 5.130041
                                        [stepOne_buy_price] => 0.0716
                                        [stepOne_buy_amount] => 4.684742
                                        [stepTwo_sell_price] => 3584.41
                                        [stepTwo_sell_amount] => 8.211168
                                        [stepTwo_buy_price] => 3634.8
                                        [stepTwo_buy_amount] => 18.388217
                                        [stepThree_sell_price] => 53865.49
                                        [stepThree_sell_amount] => 2.749428
                                        [stepThree_buy_price] => 54446.04
                                        [stepThree_buy_amount] => 1.280434
                                        [max_deal_amount] => 0.001
                                    )

                                    [info] => Array
                                        (
                                        [main_asset_name] => BTC
                                        [asset_one_name] => ETH
                                        [asset_two_name] => USDT
                                        [step_one_symbol] => ETH/BTC
                                        [step_two_symbol] => ETH/USDT
                                        [step_three_symbol] => BTC/USDT
                                        [step_one_amount_decimals] =>
                                        [step_one_price_decimals] =>
                                        [step_one_order_type] => buy
                                        [step_two_amount_decimals] =>
                                        [step_two_price_decimals] =>
                                        [step_two_order_type] => sell
                                        [step_three_amount_decimals] =>
                                        [step_three_price_decimals] =>
                                        [step_three_order_type] => buy
                                    )

                                )
                    */
                    $best_result = $this->getBestResult($plus_results);

                    $array = ['step_one', 'step_two', 'step_three'];

                    foreach ($array as $arr) {
                        $data[$arr]['do'] = 'create_order';
                        $data[$arr]['symbol'] = $best_result[$arr]['amountAsset'] . '/' . $best_result[$arr]['priceAsset'];
                        $data[$arr]['type'] = 'limit';
                        $data[$arr]['side'] = $best_result[$arr]['orderType'];
                        $data[$arr]['amount'] = $best_result[$arr]['amount'];
                        $data[$arr]['price'] = $best_result[$arr]['price'];
                    }

                    $order_execution_time_start = hrtime(true);

                    SwooleSockets::send($data['step_one'], $server->create_order_one);
                    SwooleSockets::send($data['step_two'], $server->create_order_two);
                    SwooleSockets::send($data['step_three'], $server->create_order_three);

                    $order_one_result = json_decode(rtrim($server->create_order_one->recv()), true);
                    $order_two_result = json_decode(rtrim($server->create_order_two->recv()), true);
                    $order_three_result = json_decode(rtrim($server->create_order_three->recv()), true);

                    $orders_execution_time = round((hrtime(true) - $order_execution_time_start) / 1000, 0);

                    $this->RecordResultToDB($server, $best_result, $rater, $order_one_result, $order_two_result, $order_three_result, $orders_execution_time);

                    echo "\$MONEY!\$ Time: $orders_execution_time s. | {$best_result["main_asset_name"]} -> {$best_result["asset_one_name"]} -> {$best_result["asset_two_name"]} | Deal amount: {$best_result["deal_amount"]} | RESULT: +" . $this->format($best_result["result_in_main_asset"]) . " {$best_result["main_asset_name"]}" . PHP_EOL;

//                    }

                }

            }

            // dev only
            if (DEBUG_STATUS === true) $html .= $this->CalcVisualizationFooter();

            // dev only
            if (DEBUG_STATUS === true) {
                $index = fopen($_SERVER['HOME'] . "/logs/" . EXCHANGE_CLASS . ".html", "w");
                fwrite($index, $html);
                fclose($index);

                /* dev only */
                echo "Symbol: $current_symbol, combinations: " . count($this->combinations[$current_symbol]) .
                    " out of " . count($this->combinations["total"]) .
                    ", $operations_count operations in $cycle_time μs" . PHP_EOL;
            }

        }

        return false;

    }

    private function RecordResultToDB($server, $best_result, $rater, $order1, $order2, $order3, $execution_time)
    {
        $created = date("Y-m-d H:i:s", time());

        $deal_amount_main_asset = ($best_result["main_asset_name"] === MAIN_ASSET) ? $best_result["deal_amount"] : round($rater[$best_result["main_asset_name"]]['rate'] * $best_result["deal_amount"], 8);
        $result_in_usd = (isset($rater[USDT_ASSET]['rate'])) ? $best_result["result_in_main_asset"] * $rater[USDT_ASSET]['rate'] : 0;

        $sql1 = "INSERT INTO triangles_expected (
		  mainAsset_name, 
		  assetOne_name, 
		  assetTwo_name, 
		  deal_amount, 
		  deal_amount_main_asset,
		  deal_amount_btc,
		  deal_amount_usd,
		  max_deal_amount, 
          stepOne_exchange,
		  stepOne_amountAssetName, 
		  stepOne_priceAssetName, 
		  stepOne_orderType, 
		  stepOne_sell_price, 
		  stepOne_sell_amount, 
		  stepOne_buy_price, 
		  stepOne_buy_amount, 
		  stepOne_amountAsset_result, 
		  stepOne_priceAsset_result, 
          stepTwo_exchange,
		  stepTwo_amountAssetName, 
		  stepTwo_priceAssetName, 
		  stepTwo_orderType, 
		  stepTwo_sell_price, 
		  stepTwo_sell_amount, 
		  stepTwo_buy_price, 
		  stepTwo_buy_amount, 
		  stepTwo_amountAsset_result, 
		  stepTwo_priceAsset_result, 
          stepThree_exchange,
		  stepThree_amountAssetName, 
		  stepThree_priceAssetName, 
		  stepThree_orderType, 
		  stepThree_sell_price, 
		  stepThree_sell_amount, 
		  stepThree_buy_price, 
		  stepThree_buy_amount, 
		  stepThree_amountAsset_result, 
		  stepThree_priceAsset_result, 
		  result, 
		  result_in_main_asset, 
		  result_in_btc, 
		  result_in_usd, 
		  date
		  ) 
		  VALUES (
		  
		  '{$best_result["main_asset_name"]}',
		  '{$best_result["asset_one_name"]}',
		  '{$best_result["asset_two_name"]}',
		  
		  '" . $this->format($best_result["deal_amount"]) . "',
		  '$deal_amount_main_asset',
		  '$deal_amount_main_asset',
		  '0',
          '{$best_result["expected_data"]["max_deal_amount"]}',
          
		  '" . EXCHANGE_CLASS . "',
		  
          '{$best_result["step_one"]["amountAsset"]}',
          '{$best_result["step_one"]["priceAsset"]}',
          '{$best_result["step_one"]["orderType"]}',
          '{$best_result["expected_data"]["stepOne_sell_price"]}',
          '{$best_result["expected_data"]["stepOne_sell_amount"]}',
          '{$best_result["expected_data"]["stepOne_buy_price"]}',
          '{$best_result["expected_data"]["stepOne_buy_amount"]}',
          '{$best_result["deal_amount"]}',
          '{$best_result["step_one"]["result"]}',
          
		  '" . EXCHANGE_CLASS . "',

          '{$best_result["step_two"]["amountAsset"]}',
          '{$best_result["step_two"]["priceAsset"]}',
          '{$best_result["step_two"]["orderType"]}',
          '{$best_result["expected_data"]["stepTwo_sell_price"]}',
          '{$best_result["expected_data"]["stepTwo_sell_amount"]}',
          '{$best_result["expected_data"]["stepTwo_buy_price"]}',
          '{$best_result["expected_data"]["stepTwo_buy_amount"]}',
          '{$best_result["step_one"]["result"]}',
          '{$best_result["step_two"]["result"]}',
          
          '" . EXCHANGE_CLASS . "',
          
          '{$best_result["step_three"]["amountAsset"]}',
          '{$best_result["step_three"]["priceAsset"]}',
          '{$best_result["step_three"]["orderType"]}',
          '{$best_result["expected_data"]["stepThree_sell_price"]}',
          '{$best_result["expected_data"]["stepThree_sell_amount"]}',
          '{$best_result["expected_data"]["stepThree_buy_price"]}',
          '{$best_result["expected_data"]["stepThree_buy_amount"]}',
          '{$best_result["step_two"]["result"]}',
          '{$best_result["step_three"]["result"]}',

          '{$best_result["result"]}',
          '{$best_result["result_in_main_asset"]}',
          '{$best_result["result_in_main_asset"]}',
          '$result_in_usd',

          '$created'
		  )";

        $data_db_one['sql'] = $sql1;
        $data_db_one['do'] = 'insert';

        SwooleSockets::send($data_db_one, $server->db_manager);
        $server->db_manager->recv();

        $sql2 = "SELECT id FROM triangles_expected ORDER BY id DESC LIMIT 1;";

        $data_db_two['sql'] = $sql2;
        $data_db_two['do'] = 'select';
        $data_db_two['all'] = false;

        SwooleSockets::send($data_db_two, $server->db_manager);
        $expected_id = json_decode(rtrim($server->db_manager->recv()), true);

        $expected_id = $expected_id['id'];

        $deal_result["0"] = $order1;
        $deal_result["1"] = $order2;
        $deal_result["2"] = $order3;

        $dom_position["0"] = $best_result["step_one"]["dom_position"];
        $dom_position["1"] = $best_result["step_two"]["dom_position"];
        $dom_position["2"] = $best_result["step_three"]["dom_position"];

        for ($i = 0; $i < 3; $i++) {

            $created_time = date("Y-m-d H:i:s", time());

            if ($deal_result[$i] != NULL && isset($deal_result[$i]["id"])) {

                $order_id = $deal_result[$i]["id"];
                $side = $deal_result[$i]["side"] ?? "null";
                $amount = $deal_result[$i]["amount"] ?? 0;
                $price = $deal_result[$i]["price"] ?? 0;
                $average = (isset($deal_result[$i]["average"]) && $deal_result[$i]["average"] != null) ? $deal_result[$i]["average"] : 0;
                $remain = (isset($deal_result[$i]["remaining"]) && $deal_result[$i]["remaining"] != null) ? $deal_result[$i]["remaining"] : 0;

                $base_or_quote = explode("/", $deal_result[$i]["symbol"]);
                $base_asset = $base_or_quote["0"];
                $quote_asset = $base_or_quote["1"];

                $main_asset_amount = ($base_asset == MAIN_ASSET) ? $amount : round($amount / $rater[$base_asset]['rate'], 8);
                $usd_amount = ($base_asset == USDT_ASSET) ? $amount : round($main_asset_amount * $rater[USDT_ASSET]['rate'], 8);

                $sql_three = "INSERT INTO triangles (exchange, expected_id, dom_position, step, order_id, base_asset, quote_asset, operation, amount, main_asset_amount, btc_amount, usd_amount, remain, price, average, status, execution_time, created) VALUES ('" . EXCHANGE_CLASS . "', $expected_id, {$dom_position[$i]}, " . ($i + 1) . ", '$order_id', '$base_asset', '$quote_asset', '$side', '$amount', '$main_asset_amount', '$main_asset_amount', '$usd_amount', '$remain', '$price', '$average', 'open', '$execution_time', '$created_time')";

                $data_db_three['sql'] = $sql_three;
                $data_db_three['do'] = 'insert';

                SwooleSockets::send($data_db_three, $server->db_manager);
                $server->db_manager->recv();

            }
        }

    }

    public function runConstruct($balances, $orderbooks, $rates)
    {
        $this->balances = $balances;
        /*        Array
                (
                    [BTC] => Array
                    (
                        [free] => 0.00913954
                        [used] => 0
                        [total] => 0.00913954
                    )

                    [ETH] => Array
                    (
                        [free] => 0.06751604
                        [used] => 0
                        [total] => 0.06751604
                    )

                    [USDT] => Array
                    (
                        [free] => 1784.4393761956
                        [used] => 0
                        [total] => 1784.4393761956
                    )
        )*/

        $this->orderbook = $orderbooks;
        /*        Array
                (
                    [BTC/USDT] => Array
                    (
                        [symbol] => BTC/USDT
                            [bids] => Array
                            (
                                [0] => Array
                                (
                                    [0] => 54071.59
                                    [1] => 0.034291
                                )

                                [1] => Array
                                (
                                    [0] => 54071.41
                                    [1] => 0.131042
                                )

                                [2] => Array
                                (
                                    [0] => 54071.13
                                    [1] => 0.108768
                                )
                            )

                            [asks] => Array
                            (
                                [0] => Array
                                (
                                    [0] => 54076.07
                                    [1] => 0.108299
                                )

                                [1] => Array
                                (
                                    [0] => 54076.75
                                    [1] => 0.038454
                                )

                                [2] => Array
                                (
                                    [0] => 54077.29
                                    [1] => 0.119129
                                )
                            )

                        [timestamp] =>
                        [datetime] =>
                        [nonce] =>
                    )

                    [ETH/USDT] => Array
                    (
                        [symbol] => ETH/USDT
                            [bids] => Array
                                (
                                    [0] => Array
                                    (
                                        [0] => 3555.18
                                        [1] => 1.87143
                                    )

                                    [1] => Array
                                        (
                                            [0] => 3555.08
                                            [1] => 2.54244
                                        )

                                    [2] => Array
                                        (
                                            [0] => 3554.97
                                            [1] => 2.26443
                                        )
                                )

                            [asks] => Array
                                    (
                                        [0] => Array
                                        (
                                            [0] => 3556.41
                                            [1] => 0.88
                                        )

                                        [1] => Array
                                            (
                                                [0] => 3556.65
                                                [1] => 2.31131
                                            )

                                        [2] => Array
                                            (
                                                [0] => 3556.75
                                                [1] => 2.05857
                                            )
                                    )

                        [timestamp] =>
                        [datetime] =>
                        [nonce] =>
                    )

        )*/

        $this->rates = $rates;
        /*        Array
                (
                    [BTC] => Array
                    (
                        [BTC] => 1
                        [USDT] => 54286.365
                    )

                    [ETH] => Array
                    (
                        [BTC] => 0
                        [USDT] => 0
                    )

                    [USDT] => Array
                    (
                        [BTC] => 1.8420831823976E-5
                        [USDT] => 1
                    )

            )*/
    }

}