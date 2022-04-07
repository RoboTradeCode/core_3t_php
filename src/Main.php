<?php

namespace Src;

class Main
{

    private $ip;
    private $exchange;
    private array|bool $markets;

    /**
     * Connect to DB
     *
     * @param string $host
     * @param int $port
     * @param string $db
     * @param string $user
     * @param string $password
     * @param bool $presistent
     * @return PDO|string
     */
    public function connectToDB(string $host, int $port, string $db, string $user, string $password, bool $presistent = false)
    {

        while (true) {

            try {
                $db = new PDO("mysql:host=" . $host . ";port=" . $port . ";dbname=" . $db . "", $user, $password, [PDO::ATTR_PERSISTENT => $presistent]);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return $db;
            } catch (PDOException $e) {
                sleep(1);
                echo "[ERROR] Can not connect to db" . PHP_EOL;
                continue;
            }

        }

    }

    public function DealAmount($max_deal_amount, $mainAsset_decimals, $mainAsset_id, $stepOne_amountAsset, $stepOne_priceAsset, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepThree_amountAsset, $stepThree_priceAsset, $stepOne_buy_price, $stepOne_sell_price, $stepOne_buy_amount, $stepOne_sell_amount, $stepTwo_buy_price, $stepTwo_sell_price, $stepTwo_buy_amount, $stepTwo_sell_amount, $stepThree_buy_price, $stepThree_sell_price, $stepThree_buy_amount, $stepThree_sell_amount)
    {
        //Step 1
        $deal_amount_stepOne = ($stepOne_amountAsset == $mainAsset_id) ? $stepOne_sell_amount : $stepOne_buy_amount * $stepOne_buy_price;

        //Step 2
        if ($stepTwo_amountAsset == $stepOne_amountAsset) $deal_amount_stepTwo = $stepTwo_sell_amount * $stepOne_buy_price;
        elseif ($stepTwo_amountAsset == $stepOne_priceAsset) $deal_amount_stepTwo = $stepTwo_sell_amount / $stepOne_sell_price;
        elseif ($stepTwo_priceAsset == $stepOne_amountAsset) $deal_amount_stepTwo = $stepTwo_buy_amount * $stepTwo_buy_price * $stepOne_buy_price;
        elseif ($stepTwo_priceAsset == $stepOne_priceAsset) $deal_amount_stepTwo = $stepTwo_buy_amount * $stepTwo_buy_price / $stepOne_sell_price;

        //Step 3
        if ($stepThree_amountAsset == $stepTwo_amountAsset && $stepThree_priceAsset == $stepOne_amountAsset) $deal_amount_stepThree = $stepThree_sell_amount * $stepTwo_buy_price / $stepOne_sell_price;
        elseif ($stepThree_amountAsset == $stepTwo_amountAsset && $stepThree_priceAsset == $stepOne_priceAsset) $deal_amount_stepThree = $stepThree_sell_amount * $stepTwo_buy_price * $stepOne_buy_price;
        elseif ($stepThree_amountAsset == $stepTwo_priceAsset && $stepThree_priceAsset == $stepOne_priceAsset) $deal_amount_stepThree = $stepThree_sell_amount / $stepTwo_sell_price * $stepOne_buy_price;
        elseif ($stepThree_priceAsset == $stepTwo_priceAsset && $stepThree_amountAsset == $stepOne_priceAsset) $deal_amount_stepThree = $stepThree_buy_amount * $stepThree_buy_price / $stepTwo_sell_price * $stepOne_buy_price;
        elseif ($stepThree_priceAsset == $stepTwo_priceAsset && $stepThree_amountAsset == $stepOne_amountAsset) $deal_amount_stepThree = $stepThree_buy_amount * $stepThree_buy_price / $stepTwo_sell_price / $stepOne_sell_price;
        elseif ($stepThree_priceAsset == $stepTwo_amountAsset && $stepThree_amountAsset == $stepOne_amountAsset) $deal_amount_stepThree = $stepThree_buy_amount * $stepThree_buy_price * $stepTwo_buy_price / $stepOne_sell_price;

        $deal_amount_min = round(min($deal_amount_stepOne, $deal_amount_stepTwo, $deal_amount_stepThree, $max_deal_amount), $mainAsset_decimals);

        return [
            "min" => $deal_amount_min,
            "step_one" => round($deal_amount_stepOne, $mainAsset_decimals),
            "step_two" => round($deal_amount_stepTwo, $mainAsset_decimals),
            "step_three" => round($deal_amount_stepThree, $mainAsset_decimals)
        ];
    }

    /**
     * Сalculates a market order in a DOM
     *
     * @param array $orderbook
     * @param float $amount
     * @param string $bidask
     * @param string $base_or_quote
     * @return array|false
     */
    public function MarketOrder(array $orderbook, float $amount, string $bidask, string $base_or_quote)
    {

        if ($bidask != "bids" && $bidask != "asks") return false;

        if ($base_or_quote != "base" && $base_or_quote != "quote") return false;

        if (isset($orderbook[$bidask]) && count($orderbook[$bidask]) > 0) $dom_count = count($orderbook[$bidask]);
        else return false;

        $base_amount_sum = $quote_amount_sum = $quote_amount_max = $base_amount_max = 0;

        for ($i = 0; $i < $dom_count; $i++) {

            $current_amount = $orderbook[$bidask][$i]["1"];
            $current_price = $orderbook[$bidask][$i]["0"];

            $quote_amount = $current_amount * $current_price;

            $quote_amount_max += $quote_amount;
            $base_amount_max += $current_amount;

            if (($base_or_quote === "base" && $base_amount_sum < $amount) || ($base_or_quote === "quote" && $quote_amount_sum < $amount)) {

                $quote_amount_sum += $quote_amount;
                $base_amount_sum += $current_amount;

                if ($base_or_quote === "base") {

                    if (($base_amount_max - $amount) > 0 && $i == 0) $result[$bidask]["amount"] = $amount * $current_price;
                    else $result[$bidask]["amount"] = ($amount - $base_amount_max) * $current_price + $quote_amount_sum;

                } else {

                    if (($quote_amount_max - $amount) > 0 && $i == 0) $result[$bidask]["amount"] = $amount / $current_price;
                    else $result[$bidask]["amount"] = ($amount - $quote_amount_max) / $current_price + $base_amount_sum;

                }

                $result[$bidask]["price"] = $current_price;

                //echo "Step $i) $bidask:  price:  " . $current_price . ", amount: $current_amount, amount base sum: $base_amount_max, quote sum: $quote_amount_max, current: $quote_amount) \n";
            }
        }

        $result[$bidask]["base_max"] = $base_amount_max;
        $result[$bidask]["quote_max"] = $quote_amount_max;

        if (!isset($result[$bidask]["amount"]) || $result[$bidask]["amount"] == 0) return false;
        else return $result;
    }

    public function getResult($orderbook, $markets, $deal_amount, $rater, $fee, $combinations, $stepOne_amountAsset, $stepOne_amount_decimals, $stepOne_sell_price, $stepOne_price_decimals, $stepOne_buy_price, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepTwo_amount_decimals, $stepTwo_sell_price, $stepTwo_price_decimals, $stepTwo_buy_price, $stepThree_amountAsset, $stepThree_sell_price, $stepThree_price_decimals, $stepThree_buy_price, $stepThree_amount_decimals, $step_one_dom_position, $step_two_dom_position, $step_three_dom_position, $stepOne_sell_amount, $stepOne_buy_amount, $stepTwo_sell_amount, $stepTwo_buy_amount, $stepThree_sell_amount, $stepThree_buy_amount, $max_deal_amount)
    {
        $status = true;
        $reason = "";

        /* STEP 1 */
        if ($stepOne_amountAsset == $combinations["main_asset_name"]) {

            $market_amount_step_one = $this->MarketOrder($orderbook["step_one"], $deal_amount, "bids", "base");

            if ($market_amount_step_one === false) {
                return [
                    "status" => false,
                    "reason" => "Market calculation error (step 1, sell)"
                ];
            }

            $stepOne = [
                "orderType" => "sell",
                "dom_position" => $step_one_dom_position,
                "amountAsset" => $combinations["main_asset_name"],
                "priceAsset" => $combinations["asset_one_name"],
                "amountAssetName" => $combinations["main_asset_name"],
                "priceAssetName" => $combinations["asset_one_name"],
                "amount" => $deal_amount,
                "price" => $stepOne_sell_price,
                "result" => $market_amount_step_one["bids"]["amount"]

            ];

            // Balance check (step 1, sell)
            if ($deal_amount > $rater[$combinations["main_asset_name"]]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 1, sell). Asset: {$combinations["main_asset_name"]} ({$rater[$combinations["main_asset_name"]]["free"]} < $deal_amount)";
            }

        } else {

            $market_amount_step_one = $this->MarketOrder($orderbook["step_one"], $deal_amount, "asks", "quote");

            if ($market_amount_step_one === false) {
                return [
                    "status" => false,
                    "reason" => "Market calculation error (step 1, buy)"
                ];
            }

            $step_one_result = $market_amount_step_one["asks"]["amount"];

            $stepOne = [
                "orderType" => "buy",
                "dom_position" => $step_one_dom_position,
                "amountAsset" => $combinations["asset_one_name"],
                "priceAsset" => $combinations["main_asset_name"],
                "amountAssetName" => $combinations["asset_one_name"],
                "priceAssetName" => $combinations["main_asset_name"],
                "amount" => $deal_amount / $stepOne_buy_price,
                "price" => $stepOne_buy_price,
                "result" => $step_one_result
            ];

            // Balance check (step 1, buy)
            if ($deal_amount > $rater[$combinations["main_asset_name"]]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 1, buy). Asset: {$combinations["main_asset_name"]} ({$rater[$combinations["main_asset_name"]]["free"]} < $deal_amount)";
            }

        }

        // Subtract fee (step 1)
        $stepOne["result"] = (FEE_TYPE === "percentages") ? $stepOne["result"] - $stepOne["result"] / 100 * FEE_TAKER : $stepOne["result"];

        // Amount limit check (step 1)
        $min_amount_step_one = $markets[$combinations["step_one_symbol"]]["limits"]["amount"]["min"] ?? 0;

        if ($min_amount_step_one > $stepOne["amount"]) {
            return [
                "status" => false,
                "reason" => "Amount limit error (step 1): {$combinations["step_one_symbol"]} min amount: $min_amount_step_one, current amount: {$stepOne["amount"]}"
            ];
        }

        // Cost limit check (step 1)
        $cost_limit_step_one = $markets[$combinations["step_one_symbol"]]["limits"]["cost"]["min"] ?? 0;

        if ($cost_limit_step_one > $stepOne["amount"] * $stepOne["price"]) {
            return [
                "status" => false,
                "reason" => "Cost limit error (step 1): {$combinations["step_one_symbol"]} min cost: $cost_limit_step_one, current cost: " . ($stepOne["amount"] * $stepOne["price"])
            ];
        }


        /* STEP 2 */
        if ($stepTwo_amountAsset == $combinations["asset_one_name"]) {

            $market_amount_step_two = $this->MarketOrder($orderbook["step_two"], $stepOne["result"], "bids", "base");

            if ($market_amount_step_two === false) {
                return [
                    "status" => false,
                    "reason" => "Market calculation error (step 2, sell)"
                ];
            }

            $step_two_amount = $stepOne["result"];

            $stepTwo = [
                "orderType" => "sell",
                "dom_position" => $step_two_dom_position,
                "amountAsset" => $combinations["asset_one_name"],
                "priceAsset" => $combinations["asset_two_name"],
                "amountAssetName" => $combinations["asset_one_name"],
                "priceAssetName" => $combinations["asset_two_name"],
                "amount" => $step_two_amount,
                "price" => $stepTwo_sell_price,
                "result" => $market_amount_step_two["bids"]["amount"]
            ];

            // Balance check (step 1, sell)
            if ($stepOne["result"] > $rater[$stepTwo_amountAsset]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 2, sell). Asset: {$stepTwo["amountAssetName"]} ({$rater[$stepTwo_amountAsset]["free"]} < {$stepOne["result"]})";
            }

        } else {

            $market_amount_step_two = $this->MarketOrder($orderbook["step_two"], $stepOne["result"], "asks", "quote");

            if ($market_amount_step_two === false) {
                return [
                    "status" => false,
                    "reason" => "Market calculation error (step 2, buy)"
                ];
            }

            $step_two_result = $market_amount_step_two["asks"]["amount"];

            $stepTwo = [
                "orderType" => "buy",
                "dom_position" => $step_two_dom_position,
                "amountAsset" => $combinations["asset_two_name"],
                "priceAsset" => $combinations["asset_one_name"],
                "amountAssetName" => $combinations["asset_two_name"],
                "priceAssetName" => $combinations["asset_one_name"],
                "amount" => $stepOne["result"] / $stepTwo_buy_price,
                "price" => $stepTwo_buy_price,
                "result" => $step_two_result
            ];

            // Balance check (step 2, buy)
            if ($stepOne["result"] > $rater[$stepTwo_priceAsset]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 2, buy). Asset: {$stepTwo["priceAssetName"]} ({$rater[$stepTwo_priceAsset]["free"]} < {$stepOne["result"]})";
            }

        }

        // Amount limit check (step 2)
        $min_amount_step_two = $markets[$combinations["step_two_symbol"]]["limits"]["amount"]["min"] ?? 0;

        if ($min_amount_step_two > $stepTwo["amount"]) {
            return [
                "status" => false,
                "reason" => "Amount limit error (step 2): {$combinations["step_two_symbol"]} min amount: $min_amount_step_two, current amount: {$stepTwo["amount"]}"
            ];
        }

        // Cost limit check (step 2)
        $cost_limit_step_two = $markets[$combinations["step_two_symbol"]]["limits"]["cost"]["min"] ?? 0;

        if ($cost_limit_step_two > $stepTwo["amount"] * $stepTwo["price"]) {
            return [
                "status" => false,
                "reason" => "Cost limit error (step 2): {$combinations["step_two_symbol"]} min cost: $cost_limit_step_two, current cost: " . ($stepTwo["amount"] * $stepTwo["price"])
            ];
        }

        // Subtract fee (step 2)
        $stepTwo["result"] = (FEE_TYPE === "percentages") ? $stepTwo["result"] - $stepTwo["result"] / 100 * FEE_TAKER : $stepTwo["result"];

        /* STEP 3 */
        if ($stepThree_amountAsset != $combinations["main_asset_name"]) {

            $market_amount_step_three = $this->MarketOrder($orderbook["step_three"], $stepTwo["result"], "bids", "base");

            if ($market_amount_step_three === false) {
                return [
                    "status" => false,
                    "reason" => "Market calculation error (step 3, sell)"
                ];
            }

            $step_three_amount = $stepTwo["result"];

            $stepThree = [
                "orderType" => "sell",
                "dom_position" => $step_three_dom_position,
                "amountAsset" => $combinations["asset_two_name"],
                "priceAsset" => $combinations["main_asset_name"],
                "amountAssetName" => $combinations["asset_two_name"],
                "priceAssetName" => $combinations["main_asset_name"],
                "amount" => $step_three_amount,
                "price" => $stepThree_sell_price,
                "result" => $market_amount_step_three["bids"]["amount"]
            ];

            // Balance check (step 2, sell)
            if ($stepTwo["result"] > $rater[$stepThree_amountAsset]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 3, sell). Asset: {$stepThree["amountAssetName"]} ({$rater[$stepThree_amountAsset]["free"]} < {$stepTwo["result"]})";
            }

        } else {

            $market_amount_step_three = $this->MarketOrder($orderbook["step_three"], $stepTwo["result"], "asks", "quote");

            if ($market_amount_step_three === false) {
                return [
                    "status" => false,
                    "reason" => "Market calculation error (step 3, buy)"
                ];
            }

            $step_three_result = $market_amount_step_three["asks"]["amount"];

            $stepThree = [
                "orderType" => "buy",
                "dom_position" => $step_three_dom_position,
                "amountAsset" => $combinations["main_asset_name"],
                "priceAsset" => $combinations["asset_two_name"],
                "amountAssetName" => $combinations["main_asset_name"],
                "priceAssetName" => $combinations["asset_two_name"],
                "amount" => $stepTwo["result"] / $stepThree_buy_price,
                "price" => $stepThree_buy_price,
                "result" => $step_three_result

            ];

            // Balance check (step 2, buy)
            if ($step_three_result > $rater[$combinations["main_asset_name"]]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 3, buy). Asset: {$combinations["main_asset_name"]} ({$rater[$combinations["main_asset_name"]]["free"]} < $step_three_result)";
            }
        }

        //Amount limit check (step 3)
        $min_amount_step_three = $markets[$combinations["step_three_symbol"]]["limits"]["amount"]["min"] ?? 0;

        if ($min_amount_step_three > $stepThree["amount"]) {
            return [
                "status" => false,
                "reason" => "Amount limit error (step 3): {$combinations["step_three_symbol"]} min amount: $min_amount_step_three, current amount: {$stepThree["amount"]}"
            ];
        }

        // Cost limit check (step 3)
        $cost_limit_step_three = $markets[$combinations["step_three_symbol"]]["limits"]["cost"]["min"] ?? 0;

        if ($cost_limit_step_three > $stepThree["amount"] * $stepThree["price"]) {
            return [
                "status" => false,
                "reason" => "Cost limit error (step 3): {$combinations["step_three_symbol"]} min cost: $cost_limit_step_three, current cost: " . ($stepThree["amount"] * $stepThree["price"])
            ];
        }

        // Subtract fee (step 3)
        $stepThree["result"] = (FEE_TYPE === "percentages") ? $stepThree["result"] - $stepThree["result"] / 100 * FEE_TAKER : $stepThree["result"];

        $result = round(($stepThree["result"] - $deal_amount), 8);

        $result_in_main_asset = ($combinations["main_asset_name"] === MAIN_ASSET) ? $result : round(1 / $rater[$combinations["main_asset_name"]]["rate"] * $result, 8);

        $expected_data = [
            "fee" => $fee,
            "stepOne_sell_price" => $stepOne_sell_price,
            "stepOne_sell_amount" => $stepOne_sell_amount,
            "stepOne_buy_price" => $stepOne_buy_price,
            "stepOne_buy_amount" => $stepOne_buy_amount,
            "stepTwo_sell_price" => $stepTwo_sell_price,
            "stepTwo_sell_amount" => $stepTwo_sell_amount,
            "stepTwo_buy_price" => $stepTwo_buy_price,
            "stepTwo_buy_amount" => $stepTwo_buy_amount,
            "stepThree_sell_price" => $stepThree_sell_price,
            "stepThree_sell_amount" => $stepThree_sell_amount,
            "stepThree_buy_price" => $stepThree_buy_price,
            "stepThree_buy_amount" => $stepThree_buy_amount,
            "max_deal_amount" => $max_deal_amount
        ];

        return [
            "result" => $result,
            "result_in_main_asset" => $result_in_main_asset,
            "status" => $status,
            "reason" => $reason,
            "deal_amount" => $deal_amount,
            "main_asset_name" => $combinations["main_asset_name"],
            "asset_one_name" => $combinations["asset_one_name"],
            "asset_two_name" => $combinations["asset_two_name"],
            "step_one" => $stepOne,
            "step_two" => $stepTwo,
            "step_three" => $stepThree,
            "expected_data" => $expected_data
        ];
    }

    public function getMarketsJson()
    {

        return json_decode(file_get_contents(dirname(__DIR__) . '/cache/markets.json'), true);

    }

    /**
     * Gets a list of markets for assets from the config
     *
     * @param object $exchange
     * @param array $assets
     * @return array|false
     */
    public function getMarkets(object $exchange, array $assets): bool|array
    {
        try {
            $markets = $exchange->fetch_markets();
        } catch (Throwable $e) {
            echo "[Error] " . $e->getMessage() . PHP_EOL;
            return false;
        }

        $markets_final = [];

        foreach ($markets as $id => $market) {

            if (in_array($market["base"], $assets) &&
                in_array($market["quote"], $assets) &&
                $market["symbol"] == $market["base"] . "/" . $market["quote"]) {

                /* Getting precision if it is absent */
                if (!isset($market["precision"]["amount"]) ||
                    !isset($market["precision"]["price"]) ||
                    is_null($market["precision"]["amount"]) ||
                    is_null($market["precision"]["price"])) {

                    $precision = $this->getPrecision($exchange, $market["symbol"]);
                    sleep(1);
                    $markets[$id]["precision"]["amount"] = $precision["amount_precision"];
                    $markets[$id]["precision"]["price"] = $precision["price_precision"];

                } else {
                    if (isset($market["precision"]["amount"]) && is_float($market["precision"]["amount"])) {
                        $markets[$id]["precision"]["amount"] = strlen(0.1 / $market["precision"]["amount"]);
                    }

                    if (isset($market["precision"]["price"]) && is_float($market["precision"]["price"])) {
                        $markets[$id]["precision"]["price"] = strlen(0.1 / $market["precision"]["price"]);
                    }
                }

                $markets_final[$markets[$id]["symbol"]] = $markets[$id];
            }
        }

        return (is_array($markets_final) && count($markets_final) > 0) ? $markets_final : false;
    }

    public function marketData(string $symbol, array $markets): array
    {

        $market["base"] = $markets[$symbol]["base"] ?? null;
        $market["quote"] = $markets[$symbol]["quote"] ?? null;
        $market["amount_precision"] = $markets[$symbol]["precision"]["amount"] ?? null;
        $market["price_precision"] = $markets[$symbol]["precision"]["price"] ?? null;

        return $market;

    }

    public function getOrderbookMin(object $exchange, string $symbol, int $depth = MAX_DEPTH)
    {
        try {

            $orderbook = $exchange->fetch_order_book($symbol, $depth);

            $orderbook["symbol"] = $symbol;

        } catch (Throwable $e) {

            echo '[Error] ' . $e->getMessage() . PHP_EOL;

            return false;

        }

        if (isset($orderbook["asks"]) && isset($orderbook["bids"])) return $orderbook;
        else return false;
    }

    public function addPropertyExchange($exchange_name, $api_public = '', $api_secret = '', $api_password = '', $api_uid = '')
    {
        $exchange_class = "\\ccxt\\" . $exchange_name;

        $exchange = new $exchange_class([
            "apiKey" => $api_public,
            "secret" => $api_secret,
            "password" => $api_password,
            "uid" => $api_uid,
            "timeout" => 10000,
            "enableRateLimit" => false
        ]);

        $this->exchange = $exchange;

        $this->markets = $this->getMarkets($exchange, ASSETS);
    }

    /**
     * Gets an orderbook for the symbol
     *
     * @param object $exchange
     * @param string $symbol
     * @param array $markets
     * @param int $depth
     * @return array|false
     */
    public function getOrderbook(string $symbol, int $depth = MAX_DEPTH)
    {
        try {

            $orderbook = $this->exchange->fetch_order_book($symbol, $depth);
            $orderbook["symbol"] = $symbol;
            $orderbook["base"] = $this->markets[$symbol]["base"] ?? null;
            $orderbook["quote"] = $this->markets[$symbol]["quote"] ?? null;
            $orderbook["amount_precision"] = $this->markets[$symbol]["precision"]["amount"] ?? null;
            $orderbook["price_precision"] = $this->markets[$symbol]["precision"]["price"] ?? null;

        } catch (\ccxt\NetworkError $e) {
            $this->addToErrorLog('NetworkError', $e->getMessage());
            echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
            return false;
        } catch (\ccxt\ExchangeError $e) {
            $this->addToErrorLog('ExchangeError', $e->getMessage());
            echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
            return false;
        } catch (Throwable $e) {
            $this->addToErrorLog('Exception', $e->getMessage());
            echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
            return false;
        }

        if (isset($orderbook["asks"]) && isset($orderbook["bids"])) return $orderbook;
        else return false;
    }

    /**
     * Gets order status
     *
     * @param object $exchange
     * @param string $order_id
     * @param string $symbol
     * @return array|false
     */
    public function getOrderStatus(object $exchange, string $order_id, string $symbol, $timestamp = null): bool|array
    {

        if ($exchange->has["fetchOrder"] !== false) {

            try {
                $status = $exchange->fetch_order($order_id, $symbol);
            } catch (Throwable $e) {
                $this->addToErrorLog('getOrderStatus', '[ERROR] Main()->getOrderStatus() Error in method fetch_order. Error is: ' . $e->getMessage());
                echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
                return false;
            }

            if (isset($status["id"])) return $status;
            else return false;
        } elseif ($exchange->has["fetchOpenOrders"] === true) {
            try {
                $open_orders = $exchange->fetch_open_orders($symbol);
            } catch (Throwable $e) {
                $this->addToErrorLog('getOrderStatus', '[ERROR] Main()->getOrderStatus() Error in method fetch_open_orders. Error is: ' . $e->getMessage());
                echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
                return false;
            }

            foreach ($open_orders as $open_order) {
                if ($open_order["id"] === $order_id) return $open_order;
            }

            if ($exchange->has["fetchOrders"] !== false) {
                try {
                    $orders = $exchange->fetch_orders($symbol);
                } catch (Throwable $e) {
                    $this->addToErrorLog('getOrderStatus', '[ERROR] Main()->getOrderStatus() Error in method fetch_orders. Error is: ' . $e->getMessage());
                    echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
                    return false;
                }

                foreach ($orders as $order) {
                    if ($order["id"] === $order_id) return $order;
                }

                if ($timestamp) {

                    try {
                        $orders = $exchange->fetch_orders($symbol, $timestamp, 1);
                        if ($orders[0]["id"] == $order_id) return $orders[0];
                        return false;
                    } catch (Throwable $e) {
                        $this->addToErrorLog('getOrderStatus', '[ERROR] Main()->getOrderStatus() Error in method fetch_orders. Error is: ' . $e->getMessage());
                        echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
                        return false;
                    }

                }

                if ($order === null) return false;

            } else {
                return false;
            }

        } else return false;

        return false;
    }

    /**
     * Cancel order
     *
     * @param object $exchange
     * @param string $order_id
     * @param string $symbol
     * @return array|false
     */
    public function cancelOrder(object $exchange, string $order_id, string $symbol): bool|array
    {
        try {
            $result = $exchange->cancel_order($order_id, $symbol);
        } catch (Throwable $e) {
            $this->addToErrorLog('cancelOrder', '[ERROR] Main()->cancelOrder() Error, can\'t cancel order: ' . $order_id . '. Error is: ' . $e->getMessage());
            echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
            return false;
        }

        if ($result["id"] != $order_id) {
            $error_msg = '[ERROR] Result id is not the same: ' . $result["id"];
            $this->addToErrorLog('cancelOrder', $error_msg);
            echo $error_msg;
            return false;
        }

        return $result;
    }

    /**
     * Receive all open orders
     *
     * @param object $exchange
     * @param array $markets
     * @return array|false
     *
     */
    public function getOpenOrders(object $exchange, array $markets): bool|array
    {
        if ($exchange->has["fetchOpenOrders"] !== false) {

            try {
                $open_orders = $exchange->fetch_open_orders();
                return $open_orders;
            } catch (Throwable $e) {
                echo "[INFO] fetch_open_orders does not work without a symbol" . PHP_EOL;
            }

            $open_orders = [];

            foreach ($markets as $symbol => $data) {

                try {
                    $symbol_open_orders = $exchange->fetch_open_orders($symbol);
                } catch (Throwable $e) {
                    echo "[ERROR] fetch_open_orders does not work" . PHP_EOL;
                    return false;
                }

                if (is_array($symbol_open_orders) && count($symbol_open_orders) > 0) {

                    foreach ($symbol_open_orders as $symbol_open_order) {

                        $order_id = $symbol_open_order["id"];
                        $open_orders[$order_id] = $symbol_open_order;
                    }
                }
            }
            return $open_orders;
        } else return false;
    }

    /**
     * Closes all open orders by time from the configuration
     *
     * @param object $exchange
     * @param object $db
     * @param array $markets
     * @return array|false
     */
    public function cancelAllOpenOrders(object $exchange, object $db = null, array $markets): bool|array
    {
        if ($exchange->has["cancelAllOrders"] === true) {
            try {
                $cancel_all_orders = $exchange->cancel_all_orders();
            } catch (Throwable $e) {
                $this->addToErrorLog('cancelAllOpenOrders', '[ERROR] Main()->cancelAllOrders() Error, can\'t cancel order. Error is: ' . $e->getMessage());
                echo "[ERROR] Can't use method cancelAllOrders: $e" . PHP_EOL;
            }
        }

        if (isset($cancel_all_orders)) return true;
        else {
            if ($open_orders = $this->getOpenOrders($exchange, $markets)) {

                foreach ($open_orders as $open_order) {

                    $order_id = $open_order["id"];
                    $timestamp = $open_order["timestamp"] / 1000;
                    $lifetime_in_minutes = floor((time() - $timestamp) / 60);

                    /* Looking for an order from which table */
                    $tables = ["triangles", "distribution"];
                    $selected_table = null;
                    $id = $open_order["id"] ?? null;
                    $symbol = $open_order["symbol"] ?? null;
                    $remaining = $open_order["remaining"] ?? 0;

                    /*                    foreach ($tables as $table) {
                                            $stmt = $db->prepare("SELECT `id`, `base_asset`, `quote_asset` FROM `$table` WHERE `order_id` = ? ORDER BY `id` DESC LIMIT 1");
                                            $stmt->execute([$order_id]);

                                            if ($stmt->rowCount() > 0) {
                                                $selected_table = $table;
                                                break;
                                            }
                                        }*/

                    if ($selected_table === null) {
                        echo "[Error] Table for order $order_id not found" . PHP_EOL;
                    } else {
                        $update_order = $db->query("UPDATE `$selected_table` SET `status` = 'canceled', `remain` = '$remaining' WHERE `id` = $id ORDER BY `id` DESC LIMIT 1");
                    }

                    /* Cancel the order */
                    if (($selected_table === "triangles" && $lifetime_in_minutes > ORDER_LIFETIME) ||
                        ($selected_table === "distribution" && $lifetime_in_minutes > BALANCER_ORDER_LIFETIME) ||
                        ($selected_table === null)) {
                        $cancel_order = $this->cancelOrder($exchange, $order_id, $symbol);
                        echo "[Info] Order canceled: id: $order_id ($symbol), lifetime: $lifetime_in_minutes min, table: $selected_table" . PHP_EOL;
                    }
                }
            } else return false;
        }

        return false;
    }

    /**
     * Gets several orderbooks in parallel
     *
     * @param object $exchange
     * @param array $symbols
     * @param int $depth
     * @return array|false
     * @throws Throwable
     */
    public function getOrderbooks(object $exchange, array $symbols, int $depth = MAX_DEPTH)
    {

        if ($exchange->has["fetchOrderBooks"] === true) {
            try {
                $orderbooks = $exchange->fetch_order_books($symbols, $depth);

                foreach ($orderbooks as $symbol => $orderbook) {
                    if (in_array($symbol, $symbols) === false) unset($orderbooks[$symbol]);
                    else {
                        $orderbooks[$symbol]["symbol"] = $symbol;
                        $base_or_quote = explode("/", $symbol);
                        $orderbooks[$symbol]["base"] = $base_or_quote["0"];
                        $orderbooks[$symbol]["quote"] = $base_or_quote["1"];
                    }
                }
            } catch (Throwable $e) {
                echo '[Error] ' . $e->getMessage() . PHP_EOL;
                return false;
            }

        } else {

            $get_orderbook = function ($market) use ($exchange, $depth) {

                try {
                    $orderbook = $exchange->fetch_order_book($market, $depth);
                    $orderbook["symbol"] = $market;
                    $base_or_quote = explode("/", $market);
                    $orderbook["base"] = $base_or_quote["0"];
                    $orderbook["quote"] = $base_or_quote["1"];
                    return $orderbook;
                } catch (Throwable $e) {
                    echo '[Error] ' . $e->getMessage() . PHP_EOL;
                    return false;
                }
            };

            try {
                $result = array_map($symbols, $get_orderbook);
            } catch (Throwable $exception) {
                foreach ($exception->getReasons() as $e) {
                    var_dump((string)$e);
                }
                return false;
            }

            foreach ($result as $key => $data) {
                $orderbooks[$data["symbol"]] = $data;
            }
        }

        return $orderbooks;
    }

    /**
     * Make parallel orders
     *
     * @param object $exchange
     * @param array $best_result
     * @return bool|array
     * @throws Throwable
     */
    public function makeParallelOrders(object $exchange, array $best_result)
    {
        $create_order = function ($data) use ($exchange) {

            try {
                return $exchange->create_limit_order($data["symbol"], $data["side"], $data["amount"], $data["price"]);
            } catch (Throwable $e) {
                echo "Failed to create order {$data["symbol"]}: " . $e->getMessage();
                return false;
            }
        };

        $order_step_one = ["symbol" => $best_result["step_one"]["amountAsset"] . "/" . $best_result["step_one"]["priceAsset"], "side" => $best_result["step_one"]["orderType"], "amount" => $best_result["step_one"]["amount"], "price" => $best_result["step_one"]["price"]];
        $order_step_two = ["symbol" => $best_result["step_two"]["amountAsset"] . "/" . $best_result["step_two"]["priceAsset"], "side" => $best_result["step_two"]["orderType"], "amount" => $best_result["step_two"]["amount"], "price" => $best_result["step_two"]["price"]];
        $order_step_three = ["symbol" => $best_result["step_three"]["amountAsset"] . "/" . $best_result["step_three"]["priceAsset"], "side" => $best_result["step_three"]["orderType"], "amount" => $best_result["step_three"]["amount"], "price" => $best_result["step_three"]["price"]];

        try {
            return wait(parallelMap([$order_step_one, $order_step_two, $order_step_three], $create_order));
        } catch (Throwable $exception) {
            foreach ($exception->getReasons() as $e) {
                var_dump((string)$e);
            }
            return false;
        }
    }

    /**
     * Creates orders sequentially
     *
     * @param object $exchange
     * @param array $best_result
     * @return bool|array
     * @throws Throwable
     */
    public function makeOrders(object $exchange, array $best_result)
    {
        $create_order = function ($data) use ($exchange) {

            try {
                return $exchange->create_limit_order($data["symbol"], $data["side"], $data["amount"], $data["price"]);
            } catch (Throwable $e) {
                echo "Failed to create order {$data["symbol"]}: " . $e->getMessage();
                return false;
            }
        };

        $order_step_one = ["symbol" => $best_result["step_one"]["amountAsset"] . "/" . $best_result["step_one"]["priceAsset"], "side" => $best_result["step_one"]["orderType"], "amount" => $best_result["step_one"]["amount"], "price" => $best_result["step_one"]["price"]];
        $order_step_two = ["symbol" => $best_result["step_two"]["amountAsset"] . "/" . $best_result["step_two"]["priceAsset"], "side" => $best_result["step_two"]["orderType"], "amount" => $best_result["step_two"]["amount"], "price" => $best_result["step_two"]["price"]];
        $order_step_three = ["symbol" => $best_result["step_three"]["amountAsset"] . "/" . $best_result["step_three"]["priceAsset"], "side" => $best_result["step_three"]["orderType"], "amount" => $best_result["step_three"]["amount"], "price" => $best_result["step_three"]["price"]];

        return array_map($create_order, [$order_step_one, $order_step_two, $order_step_three]);
    }

    /**
     * Gets assets rater (free, used, total)
     *
     * @param object $exchange
     * @param array $assets
     * @return array|false
     */
    public function getBalances(object $exchange, array $assets)
    {
        $balances = [];

        try {
            $all_balances = $exchange->fetch_balance();
        } catch (Throwable $e) {
            $this->addToErrorLog('getBalances', '[ERROR] Main()->getBalances() Can\'t get balance. Error: ' . $e->getMessage());
            echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
            return false;
        }

        foreach ($assets as $asset) {
            if (isset($all_balances[$asset])) $balances[$asset] = $all_balances[$asset];
            else $balances[$asset] = ["free" => 0, "used" => 0, "total" => 0];
        }

        if (count($balances) === count($assets)) return $balances;
        else return false;
    }

    /**
     * Creates a limit order
     *
     * @param object $exchange
     * @param string $symbol
     * @param string $side
     * @param float $amount
     * @param float $price
     * @return array|false
     */
    public function createLimitOrder(object $exchange, string $symbol, string $side, float $amount, float $price)
    {
        try {
            $order = $exchange->create_order($symbol, "limit", $side, $amount, $price);
            return $order;
        } catch (Throwable $e) {
            echo '[Error] ' . $e->getMessage() . PHP_EOL;
            return false;
        }
    }

    /**
     * Creates an order
     *
     * @param object $exchange
     * @param string $symbol
     * @param string $type
     * @param string $side
     * @param float $amount
     * @param float $price
     * @return array|false
     */
    public function createOrder(object $exchange, string $symbol, string $type, string $side, float $amount, float $price): bool|array
    {
        try {
            $order = $exchange->create_order($symbol, $type, $side, $amount, $price);
        } catch (Throwable $e) {
            $error_msg = "Symbol: $symbol, type: $type, side: $side, amount: $amount, price: $price. Error: " . $e->getMessage();
            $this->addToErrorLog('createOrder', $error_msg);
            echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
            return false;
        }

        if (isset($order['id']) && $order['id'] != null) return $order;
        else return false;

    }

    /**
     * Receives amount and price precision for symbol
     *
     * @param object $exchange
     * @param string $symbol
     * @return array|false
     */
    public function getPrecision(object $exchange, string $symbol)
    {
        try {
            $orderbook = $this->getOrderbookMin($exchange, $symbol);
        } catch (Throwable $e) {
            echo "[ERROR] Failed to get orderbook for $symbol (getPrecision() function)" . PHP_EOL;
            return false;
        }

        echo "Getting precision for $symbol..." . PHP_EOL;

        $orderbook = array_merge($orderbook['bids'], $orderbook['asks']);

        $amount_precision = [];
        $price_precision = [];

        foreach ($orderbook as $dom) {
            $amount_precision[] = strlen(substr(strrchr($dom['1'], "."), 1));
            $price_precision[] = strlen(substr(strrchr($dom['0'], "."), 1));
        }

        $max_amount_precision = max($amount_precision);
        $max_price_precision = max($price_precision);

        return [
            "amount_precision" => $max_amount_precision,
            "price_precision" => $max_price_precision
        ];
    }

    /**
     * Gets rater and rater from DB
     *
     * @param object $db
     * @return array|false
     */
    public function getRaterData(object $db)
    {
        $get_rater = $db->query("SELECT * FROM rater WHERE exchange = '" . EXCHANGE_CLASS . "'");

        while ($row = $get_rater->fetch(PDO::FETCH_ASSOC)) $rater[$row["asset"]] = $row;

        if (!isset($rater) || $rater == NULL) return false;
        else return $rater;
    }

    /**
     * Adds an error to the database
     *
     * @param string $module
     * @param string $message
     * @param bool $error_log
     * @return bool
     */
    public function addToErrorLog(string $module, string $message, bool $error_log = false)
    {

        $db = $this->connectToDB(MYSQL_HOST, MYSQL_PORT, MYSQL_DB, MYSQL_USER, MYSQL_PASSWORD, false);

        $created = date("Y-m-d H:i:s", time());

        if ($error_log) error_log($message);

        try {
            $sth = $db->prepare("INSERT INTO `error_log` SET `exchange` = :exchange, `module` = :modules, `message` = :message, `created` = :created");
            $sth->execute([
                'exchange' => EXCHANGE_CLASS,
                'modules' => $module,
                'message' => $message,
                'created' => $created
            ]);
        } catch (PDOException $e) {
            echo "Failed to add an error to the database: " . $e->getMessage() . PHP_EOL;
            return false;
        }

        return true;
    }

    /**
     * Check if the necessary tables exist, if not, create
     *
     * @param object $db
     */
    public function checkExistenceTables(object $db)
    {

        if (!$db) die("Error: Unable to connect to MySQL." . PHP_EOL);

        //Таблица реальных сделок
        $real_deals_table = "CREATE TABLE IF NOT EXISTS `triangles` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `exchange` varchar(30) NOT NULL,
        `expected_id` int(11) NOT NULL,
        `dom_position` int(4) NOT NULL,
        `step` smallint(1) NOT NULL DEFAULT '0',
        `order_id` varchar(50) NOT NULL,
        `base_asset` varchar(15) NOT NULL,
        `quote_asset` varchar(15) NOT NULL,
        `operation` varchar(4) NOT NULL,
        `amount` decimal(25,8) NOT NULL,
        `main_asset_amount` decimal(25,8) NOT NULL,
        `btc_amount` decimal(25,8) NOT NULL,
        `usd_amount` decimal(25,8) NOT NULL,
		`remain` decimal(25,8) NOT NULL,
		`price` decimal(25,8) NOT NULL,
		`average` decimal(25,8) NOT NULL,
        `status` varchar(30) NOT NULL,
        `execution_time` decimal(5,3) NOT NULL,
        `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY (`id`)
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8";

        //Таблица предполагаемых сделок
        $expected_deals_table = "CREATE TABLE IF NOT EXISTS `triangles_expected` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `mainAsset_name` varchar(20) NOT NULL,
        `assetOne_name` varchar(20) NOT NULL,
        `assetTwo_name` varchar(20) NOT NULL,
        `deal_amount` decimal(25,8) NOT NULL,
        `deal_amount_main_asset` decimal(25,8) NOT NULL,
        `deal_amount_btc` decimal(25,8) NOT NULL,
        `deal_amount_usd` decimal(25,8) NOT NULL,
        `max_deal_amount` decimal(25,8) NOT NULL,
        `stepOne_exchange` varchar(20) NOT NULL,
        `stepOne_amountAssetName` varchar(20) NOT NULL,
        `stepOne_priceAssetName` varchar(20) NOT NULL,
        `stepOne_orderType` varchar(4) NOT NULL,
        `stepOne_sell_price` decimal(25,8) NOT NULL,
        `stepOne_sell_amount` decimal(25,8) NOT NULL,
        `stepOne_buy_price` decimal(25,8) NOT NULL,
        `stepOne_buy_amount` decimal(25,8) NOT NULL,
        `stepOne_amountAsset_result` decimal(25,8) NOT NULL,
        `stepOne_priceAsset_result` decimal(25,8) NOT NULL,
        `stepTwo_exchange` varchar(20) NOT NULL,
        `stepTwo_amountAssetName` varchar(20) NOT NULL,
        `stepTwo_priceAssetName` varchar(20) NOT NULL,
        `stepTwo_orderType` varchar(4) NOT NULL,
        `stepTwo_sell_price` decimal(25,8) NOT NULL,
        `stepTwo_sell_amount` decimal(25,8) NOT NULL,
        `stepTwo_buy_price` decimal(25,8) NOT NULL,
        `stepTwo_buy_amount` decimal(25,8) NOT NULL,
        `stepTwo_amountAsset_result` decimal(25,8) NOT NULL,
        `stepTwo_priceAsset_result` decimal(25,8) NOT NULL,
        `stepThree_exchange` varchar(20) NOT NULL,
        `stepThree_amountAssetName` varchar(20) NOT NULL,
        `stepThree_priceAssetName` varchar(20) NOT NULL,
        `stepThree_orderType` varchar(4) NOT NULL,
        `stepThree_sell_price` decimal(25,8) NOT NULL,
        `stepThree_sell_amount` decimal(25,8) NOT NULL,
        `stepThree_buy_price` decimal(25,8) NOT NULL,
        `stepThree_buy_amount` decimal(25,8) NOT NULL,
        `stepThree_amountAsset_result` decimal(25,8) NOT NULL,
        `stepThree_priceAsset_result` decimal(25,8) NOT NULL,
        `result` decimal(25,8) NOT NULL,
        `result_in_main_asset` decimal(25,8) NOT NULL,
        `result_in_btc` decimal(25,8) DEFAULT NULL,
        `result_in_usd` decimal(25,8) DEFAULT NULL,
        `date` datetime NOT NULL,
        PRIMARY KEY (`id`)
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8";

        //Таблица балансировщика
        $distribution_table = "CREATE TABLE IF NOT EXISTS `distribution` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `exchange` varchar(30) NOT NULL,
        `step` int(2) NOT NULL DEFAULT '0',
        `type` smallint(1) NOT NULL DEFAULT '0',
        `order_id` varchar(50) NOT NULL,
        `base_asset` varchar(15) NOT NULL,
        `quote_asset` varchar(15) NOT NULL,
        `operation` varchar(4) NOT NULL,
        `amount` decimal(25,8) NOT NULL,
        `main_asset_amount` decimal(25,8) NOT NULL,
        `btc_amount` decimal(25,8) NOT NULL,
        `usd_amount` decimal(25,8) NOT NULL,
		`remain` decimal(25,8) NOT NULL,
		`price` decimal(25,8) NOT NULL,
		`average` decimal(25,8) NOT NULL,
        `status` varchar(30) NOT NULL,
        `execution_time` decimal(5,3) NOT NULL,
        `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8";

        //Таблица кешированной статистики
        $stats_table = "CREATE TABLE IF NOT EXISTS `stats` (
        `date` date NOT NULL,
        `expected_operations` int(11) NOT NULL,
        `canceled_operations` int(11) NOT NULL,
        `canceled_operations_percent` decimal(4,2) NOT NULL,
        `expected_result_in_main_asset` decimal(25,8) NOT NULL,
        `expected_result_in_btc` decimal(25,8) NOT NULL,
        `expected_result_in_usd` decimal(25,8) NOT NULL,
        `volume` decimal(25,8) NOT NULL,
        PRIMARY KEY (`date`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8";

        //Таблица актуальных балансов
        $rater_table = "CREATE TABLE IF NOT EXISTS `rater` (
        `exchange` varchar(30) NOT NULL,
        `asset` varchar(50) NOT NULL,
        `free` decimal(25,8) DEFAULT NULL,
        `used` decimal(25,8) DEFAULT NULL,
        `total` decimal(25,8) DEFAULT NULL,
        `total_main_asset` decimal(25,8) DEFAULT NULL,
        `total_main_asset_free` decimal(25,8) DEFAULT NULL,
        `rate` decimal(25,8) DEFAULT NULL,
        `last_update` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`exchange`, `asset`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

        //Таблица логов ошибок
        $error_log_table = "CREATE TABLE IF NOT EXISTS `error_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `exchange` varchar(30),
        `module` varchar(30),
        `message` text NOT NULL,
        `created` datetime NOT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8";


        if ($check_real_deals_table = $db->query($real_deals_table)) echo "Table 'real_deals' exists or already created... OK" . PHP_EOL;
        else die("Unable to create table 'real_deals'");

        if ($check_expected_deals_table = $db->query($expected_deals_table)) echo "Table 'expected_deals' exists or already created... OK" . PHP_EOL;
        else die("Unable to create table 'expected_deals'");

        if ($check_distribution_table = $db->query($distribution_table)) echo "Table 'distribution' exists or already created... OK" . PHP_EOL;
        else die("Unable to create table 'distribution'");

        if ($check_stats_table = $db->query($stats_table)) echo "Table 'stats' exists or already created... OK" . PHP_EOL;
        else die("Unable to create table 'stats'");

        if ($check_rater_table = $db->query($rater_table)) echo "Table 'rater' exists or already created... OK" . PHP_EOL;
        else die("Unable to create table 'rater'");

        if ($check_error_log_table = $db->query($error_log_table)) echo "Table 'error_log' exists or already created... OK" . PHP_EOL;
        else die("Unable to create table 'error_log'");

        //Очищаем таблицу с балансами
        if ($db->query("TRUNCATE TABLE rater")) echo "Table 'rater' cleared... OK" . PHP_EOL;
        else die("Unable to clear table 'rater'");

        foreach (ASSETS as $asset) {

            $db->query("INSERT INTO rater (`exchange`, `asset`) VALUES ('" . EXCHANGE_CLASS . "', '{$asset}')");

        }

    }

    /**
     * Gets the best of results
     *
     * @param array $plus_results
     * @return array
     */
    public function getBestResult(array $plus_results)
    {
        $plus_results = array_values($plus_results);
        $best_results = array_column($plus_results, "result_in_main_asset");
        $best_key = array_keys($best_results, max($best_results));
        $best_result = $plus_results[$best_key["0"]];

        return $best_result;
    }

    /**
     * Add expected result to DB and get last expected ID
     *
     * @param object $db
     * @param array $best_result
     * @param array $rater
     * @return int|bool
     */
    public function addExpectedResultToDB(object $db, array $best_result, array $rater)
    {
        $created = date("Y-m-d H:i:s", time());

        $deal_amount_main_asset = ($best_result["main_asset_name"] === MAIN_ASSET) ? $best_result["deal_amount"] : round($rater[$best_result["main_asset_name"]]['rate'] * $best_result["deal_amount"], 8);
        $result_in_usd = (isset($rater[USDT_ASSET]['rate'])) ? $best_result["result_in_main_asset"] * $rater[USDT_ASSET]['rate'] : 0;

        $query = $db->query("INSERT INTO triangles_expected (
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
		  )");

        $last_expected_id = $db->lastInsertId();
        $last_expected_id = ($last_expected_id != NULL) ? $last_expected_id : false;

        return $last_expected_id;
    }

    public function addRealResultToDB(object $db, array $best_result, array $orders, array $rater, int $expected_id, float $execution_time)
    {

        $deal_result["0"] = $orders["0"];
        $deal_result["1"] = $orders["1"];
        $deal_result["2"] = $orders["2"];

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

                $query = $db->query("INSERT INTO triangles (exchange, expected_id, dom_position, step, order_id, base_asset, quote_asset, operation, amount, main_asset_amount, btc_amount, usd_amount, remain, price, average, status, execution_time, created) VALUES
                ('" . EXCHANGE_CLASS . "', $expected_id, {$dom_position[$i]}, " . ($i + 1) . ", '$order_id', '$base_asset', '$quote_asset', '$side', '$amount', '$main_asset_amount', '$main_asset_amount', '$usd_amount', '$remain', '$price', '$average', 'open', '$execution_time', '$created_time')");
            }
        }
    }

    public function CalcVisualizationHeader(array $orderbooks, string $current_symbol)
    {

        $date = date("d.m.y H:i:s", time());

//        echo '<h2>Orderbooks</h2><pre>';
//        print_r($orderbooks);
//        echo '<pre>';

        $html = <<<HTML
         <style> body {font-family: monospace;} table {border-collapse: collapse;} td, th {border: 1px solid #000; padding: 5px;} th {font-weight: bold;}</style>
         <i>Current symbol: <b>$current_symbol</b>. Generated: $date</i><br /><br />

        <table>
        <tr><th>#</th><th>Triangle</th><th>Step 1</th><th>Step 2</th><th>Step 3</th><th>Result</th></tr>
        HTML;

        return $html;
    }

    public function CalcVisualizationBody($i, $step_one_dom_position, $step_two_dom_position, $step_three_dom_position, $result, $waves_deal_amount, $deal_amount, $fee, $combinations, $stepOne_sell_amount, $stepTwo_sell_amount, $stepOne_buy_amount, $stepTwo_buy_amount, $stepThree_buy_amount, $stepThree_sell_amount, $stepOne_amountAsset, $stepOne_amount_decimals, $stepOne_sell_price, $stepOne_price_decimals, $stepOne_buy_price, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepTwo_amount_decimals, $stepTwo_sell_price, $stepTwo_price_decimals, $stepTwo_buy_price, $stepThree_amountAsset, $stepThree_sell_price, $stepThree_price_decimals, $stepThree_buy_price, $stepThree_amount_decimals)
    {

        $table = "<tr><td>$i</td>";

        $calculations = "<td><strong>{$combinations["main_asset_name"]} -> {$combinations["asset_one_name"]} -> {$combinations["asset_two_name"]}</strong><br /><small>Deal: " . $this->format($deal_amount) . " {$combinations["main_asset_name"]}<br />Max: {$waves_deal_amount}<br />Fee: " . $this->format($fee) . "</small></td>";

        // Step 1
        $calculations .= "<td>Market: {$result["step_one"]["amountAssetName"]} -> {$result["step_one"]["priceAssetName"]} ({$result["step_one"]["orderType"]})<br />Position: $step_one_dom_position<br />Sell: " . $this->format($stepOne_sell_price) . " (" . $this->format($stepOne_sell_amount) . ")<br />Buy: " . $this->format($stepOne_buy_price) . " (" . $this->format($stepOne_buy_amount) . ")<br />Result: <span style=\"color: red;\">-" . $this->format($deal_amount) . " {$combinations["main_asset_name"]}</span>, <span style=\"color: green;\">+" . $this->format($result["step_one"]["result"]) . " {$combinations["asset_one_name"]}</span></td>";

        // Step 2
        $calculations .= "<td>Market: {$result["step_two"]["amountAssetName"]} -> {$result["step_two"]["priceAssetName"]} ({$result["step_two"]["orderType"]})<br />Position: $step_two_dom_position<br />Sell: " . $this->format($stepTwo_sell_price) . " (" . $this->format($stepTwo_sell_amount) . ")<br />Buy: " . $this->format($stepTwo_buy_price) . " (" . $this->format($stepTwo_buy_amount) . ")<br />Result: <span style=\"color: red;\">-" . $this->format($result["step_one"]["result"]) . " {$combinations["asset_one_name"]}</span>, <span style=\"color: green;\">+" . $this->format($result["step_two"]["result"]) . " {$combinations["asset_two_name"]}</span></td>";

        // Step 3
        $calculations .= "<td>Market: {$result["step_three"]["amountAssetName"]} -> {$result["step_three"]["priceAssetName"]} ({$result["step_three"]["orderType"]})<br />Position: $step_three_dom_position<br />Sell: " . $this->format($stepThree_sell_price) . " (" . $this->format($stepThree_sell_amount) . ")<br />Buy: " . $this->format($stepThree_buy_price) . " (" . $this->format($stepThree_buy_amount) . ")<br />Result: <span style=\"color: red;\">-" . $this->format($result["step_two"]["result"]) . " {$combinations["asset_two_name"]}</span>, <span style=\"color: green;\">+" . $this->format($result["step_three"]["result"]) . " {$combinations["main_asset_name"]}</span></td>";

        // Result
        $calculations .= "<td><span style=\"color: " . (($result["result"] > 0) ? "green" : "red;") . ";\">" . $this->format($result["result"]) . " {$combinations["main_asset_name"]}</span></td>";

        $calculations .= "</tr>";

        $table .= $calculations;

        return $table;
    }

    public function CalcVisualizationDelimeter($reason)
    {
        $html = '<tr><td colspan="6" style="background-color: grey; color: #fff; text-align: center;">' . $reason . '</td></tr>';
        $html .= '<tr><td colspan="6" style="border-left: 0; border-right: 0;">&nbsp;</td></tr>';

        return $html;
    }

    public function CalcVisualizationFooter()
    {
        $html = '</table>';

        return $html;
    }

    public function format($float, $decimals = 8)
    {
        return number_format($float, $decimals, ".", "");
    }

    public function getRate($symbol, $price)
    {

        list($first_asset, $second_asset) = explode('/', $symbol);

        if ($first_asset == MAIN_ASSET || $second_asset == MAIN_ASSET) {

            $condition = ($first_asset == MAIN_ASSET);

            return [
                $condition ? $second_asset : $first_asset,
                $condition ? $price : round(1 / $price, 2)
            ];

        }

        return ['', 0];

    }

    public function getRates($rates)
    {

        if (!empty($rates)) {

            $rate_courses = [];

            foreach (ASSETS as $asset) {

                foreach (COURSE as $course) {

                    $pair = $asset . '/' . $course;

                    if ($asset != $course) {

                        if (isset($rates[$pair])) $rate_courses[$pair] = $rates[$pair];
                        elseif (isset($rates[$course . '/' . $asset])) $rate_courses[$pair] = 1 / $rates[$course . '/' . $asset];
                        else {

                            $this->mergeAsset($rate_courses, $rates, $asset, $course, $pair);

                        }

                    } else {
                        $rate_courses[$pair] = 1;
                    }

                }

            }

            $zero_courses = array_filter($rate_courses, function ($v) {
                return $v == 0;
            }, ARRAY_FILTER_USE_BOTH);

            foreach ($zero_courses as $key_p => $zero_course) {

                $delete = COURSE;
                $delete[] = '/';

                $asset = str_replace($delete, '', $key_p);
                $course = str_replace(['/', $asset], '', $key_p);

                $this->mergeAsset($rate_courses, $rate_courses, $asset, $course, $key_p);

            }

            foreach ($rate_courses as $key => $rate_course) {

                list($asset, $course) = explode('/', $key);

                $final[$asset][$course] = $rate_course;

            }

        }

        return $final ?? [];

    }

    private function mergeAsset(&$rate_courses, $rates, $asset, $course, $pair)
    {

        $common_pairs = array_filter($rates, function ($v, $k) use ($asset) {
            return is_int(strpos($k, $asset)) && $v != 0;
        }, ARRAY_FILTER_USE_BOTH);

        if (!empty($common_pairs)) {

            foreach ($common_pairs as $key => $common_pair) {

                $k1 = 0;
                $k2 = 0;

                $find_asset = str_replace(['/', $asset], '', $key);

                if (isset($rates[$course . '/' . $find_asset]) && $rates[$course . '/' . $find_asset] != 0) {

                    $k1 = $common_pair;

                    if (isset($common_pairs[$asset . '/' . $find_asset])) $k2 = 1 / $rates[$course . '/' . $find_asset];
                    elseif (isset($common_pairs[$find_asset . '/' . $asset])) $k2 = $rates[$course . '/' . $find_asset];

                } elseif (isset($rates[$find_asset . '/' . $course]) && $rates[$find_asset . '/' . $course] != 0) {

                    $k1 = 1 / $common_pair;

                    if (isset($common_pairs[$asset . '/' . $find_asset])) $k2 = 1 / $rates[$find_asset . '/' . $course];
                    elseif (isset($common_pairs[$find_asset . '/' . $asset])) $k2 = $rates[$find_asset . '/' . $course];

                }

                if ($k1 != 0 && $k2 != 0) {

                    $rate_courses[$pair] = $k1 * $k2;

                    break;

                }

            }

            $rate_courses[$pair] = $rate_courses[$pair] ?? 0;

        } else $rate_courses[$pair] = 0;
    }
}