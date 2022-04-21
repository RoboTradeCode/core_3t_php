<?php
set_time_limit(0);
ini_set("max_execution_time", 0);

require __DIR__ . "/vendor/autoload.php";
require __DIR__ . "/src/Main.php";
require __DIR__ . "/configs/bot.config.php";
require __DIR__ . "/configs/db.config.php";

$exchange_class = "\\ccxt\\" . EXCHANGE_CLASS;
$exchange = new $exchange_class (["apiKey" => API_KEY, "secret" => API_SECRET, "timeout" => API_TIMEOUT, "enableRateLimit" => API_RATE_LIMIT]);

$bot = new Main();

$db = $bot->connectToDB(MYSQL_HOST, MYSQL_PORT, MYSQL_DB, MYSQL_USER, MYSQL_PASSWORD, true);

// Pause between attempts
$pause = BOT_SLEEP * 1000000;

//$bot->сheckExistenceTables($db); die();

$date = date("d.m.y H:i:s", time());

// Get all markets
$markets = $bot->getMarkets($exchange, ASSETS);

if ($markets === false) die("Failed to get markets") . PHP_EOL;

// Get symbols array
$symbols = array_keys($markets);

// Pause between attempts
$pause = BOT_SLEEP * 1000000;

$best_place_step_one = $best_place_step_two = $best_place_step_three = [0, 0];

// Get bot array
$combinations = json_decode(file_get_contents(__DIR__ . '/cache/triangles.json'), true);
$combinations_count = count($combinations);

// Run daemon
while (1) {

    $exchange = new $exchange_class (["apiKey" => API_KEY, "secret" => API_SECRET, "timeout" => API_TIMEOUT, "enableRateLimit" => API_RATE_LIMIT]);

    // Get rater and rater
    $rater = $bot->getRaterData($db);

    // Total time countdown start
    $total_time_start = microtime(true);

    // Market data fetching time countdown start
    $market_data_time_start = microtime(true);

//    $markets_data = $bot->getOrderbooks($exchange, $symbols, MAX_DEPTH);

    $markets_data[$symbols["0"]] = $bot->getOrderbook($exchange, $symbols["0"], $markets, MAX_DEPTH);
    $markets_data[$symbols["1"]] = $bot->getOrderbook($exchange, $symbols["1"], $markets,MAX_DEPTH);
    $markets_data[$symbols["2"]] = $bot->getOrderbook($exchange, $symbols["2"], $markets,MAX_DEPTH);

    $market_data_time = round(microtime(true) - $market_data_time_start, 3);

    $operations_count = 0;
    $deal_time = 0;
    $result = 0;
    $orderbook = [];
    $plus_results = [];

    if (DEBUG_STATUS === true) $html = $bot->CalcVisualizationHeader();

    $cycle_time_start = microtime(true);

    for ($i = 0; $i < $combinations_count; $i++) {

        if (isset($markets_data[$combinations[$i]["step_one_symbol"]])) $orderbook["step_one"] = $markets_data[$combinations[$i]["step_one_symbol"]];
        else continue;

        if (isset($markets_data[$combinations[$i]["step_two_symbol"]])) $orderbook["step_two"] = $markets_data[$combinations[$i]["step_two_symbol"]];
        else continue;

        if (isset($markets_data[$combinations[$i]["step_three_symbol"]])) $orderbook["step_three"] = $markets_data[$combinations[$i]["step_three_symbol"]];
        else continue;

        // Step 1 constants
        $timestamp[0] = $orderbook["step_one"]["timestamp"];
        $stepOne_priceAsset = $orderbook["step_one"]["quote"];
        $stepOne_amountAsset = $orderbook["step_one"]["base"];

        $stepOne_amount_decimals = $combinations[$i]["step_one_amount_decimals"];
        $stepOne_price_decimals = $combinations[$i]["step_one_price_decimals"];

        // Step 2 constants
        $timestamp[1] = $orderbook["step_two"]["timestamp"];
        $stepTwo_amountAsset = $orderbook["step_two"]["base"];
        $stepTwo_priceAsset = $orderbook["step_two"]["quote"];

        $stepTwo_amount_decimals = $combinations[$i]["step_two_amount_decimals"];
        $stepTwo_price_decimals = $combinations[$i]["step_two_price_decimals"];

        // Step 3 constants
        $timestamp[2] = $orderbook["step_three"]["timestamp"];
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

        $reason = "";

        $depth = 0;

        // DOM calculation
        while (1) {

            ###<DEAL VARIABLES>###
            //Step 1
            if ($bot->format($deal_amount["step_one"]) < $bot->format($max_deal_amount)) {

                $stepOne_buy_price = (isset($orderbook["step_one"]["asks"][$step_one_dom_position]["0"])) ? $orderbook["step_one"]["asks"][$step_one_dom_position]["0"] : 0;
                $stepOne_sell_price = (isset($orderbook["step_one"]["bids"][$step_one_dom_position]["0"])) ? $orderbook["step_one"]["bids"][$step_one_dom_position]["0"] : 0;

                $stepOne_buy_amount += (isset($orderbook["step_one"]["asks"][$step_one_dom_position]["1"])) ? $orderbook["step_one"]["asks"][$step_one_dom_position]["1"] : 0;
                $stepOne_sell_amount += (isset($orderbook["step_one"]["bids"][$step_one_dom_position]["1"])) ? $orderbook["step_one"]["bids"][$step_one_dom_position]["1"] : 0;

                $step_one_dom_position++;
            }

            //Step 2
            if ($bot->format($deal_amount["step_two"]) < $bot->format($max_deal_amount)) {

                $stepTwo_buy_price = (isset($orderbook["step_two"]["asks"][$step_two_dom_position]["0"])) ? $orderbook["step_two"]["asks"][$step_two_dom_position]["0"] : 0;
                $stepTwo_sell_price = (isset($orderbook["step_two"]["bids"][$step_two_dom_position]["0"])) ? $orderbook["step_two"]["bids"][$step_two_dom_position]["0"] : 0;

                $stepTwo_buy_amount += (isset($orderbook["step_two"]["asks"][$step_two_dom_position]["1"])) ? $orderbook["step_two"]["asks"][$step_two_dom_position]["1"] : 0;
                $stepTwo_sell_amount += (isset($orderbook["step_two"]["bids"][$step_two_dom_position]["1"])) ? $orderbook["step_two"]["bids"][$step_two_dom_position]["1"] : 0;

                $step_two_dom_position++;
            }

            //Step 3
            if ($bot->format($deal_amount["step_three"]) < $bot->format($max_deal_amount)) {

                $stepThree_buy_price = (isset($orderbook["step_three"]["asks"][$step_three_dom_position]["0"])) ? $orderbook["step_three"]["asks"][$step_three_dom_position]["0"] : 0;
                $stepThree_sell_price = (isset($orderbook["step_three"]["bids"][$step_three_dom_position]["0"])) ? $orderbook["step_three"]["bids"][$step_three_dom_position]["0"] : 0;

                $stepThree_buy_amount += (isset($orderbook["step_three"]["asks"][$step_three_dom_position]["1"])) ? $orderbook["step_three"]["asks"][$step_three_dom_position]["1"] : 0;
                $stepThree_sell_amount += (isset($orderbook["step_three"]["bids"][$step_three_dom_position]["1"])) ? $orderbook["step_three"]["bids"][$step_three_dom_position]["1"] : 0;

                $step_three_dom_position++;
            }
            ###</DEAL VARIABLES>###

            $deal_amount = $bot->DealAmount($max_deal_amount, $combinations[$i]["step_one_amount_decimals"], $combinations[$i]["main_asset_name"], $stepOne_amountAsset, $stepOne_priceAsset, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepThree_amountAsset, $stepThree_priceAsset, $stepOne_buy_price, $stepOne_sell_price, $stepOne_buy_amount, $stepOne_sell_amount, $stepTwo_buy_price, $stepTwo_sell_price, $stepTwo_buy_amount, $stepTwo_sell_amount, $stepThree_buy_price, $stepThree_sell_price, $stepThree_buy_amount, $stepThree_sell_amount);

            $result = $bot->getResult($orderbook, $deal_amount["min"], $rater, FEE_TAKER, $combinations[$i], $stepOne_amountAsset, $stepOne_amount_decimals, $stepOne_sell_price, $stepOne_price_decimals, $stepOne_buy_price, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepTwo_amount_decimals, $stepTwo_sell_price, $stepTwo_price_decimals, $stepTwo_buy_price, $stepThree_amountAsset, $stepThree_sell_price, $stepThree_price_decimals, $stepThree_buy_price, $stepThree_amount_decimals, $step_one_dom_position, $step_two_dom_position, $step_three_dom_position, $stepOne_sell_amount, $stepOne_buy_amount, $stepTwo_sell_amount, $stepTwo_buy_amount, $stepThree_sell_amount, $stepThree_buy_amount, $max_deal_amount);

            if ($result["status"] === false) {
                $reason = $result["reason"];
                break;
            }

            $operations_count++;

            if ($result["status"] === true && $result["result_in_main_asset"] > MIN_PROFIT) {
                $plus_results[$i] = $result;
                $plus_results[$i]["info"] = $combinations[$i];
            }

            if (DEBUG_STATUS === true) $html .= $bot->CalcVisualizationBody($operations_count, $step_one_dom_position, $step_two_dom_position, $step_three_dom_position, $result, $max_deal_amount, $deal_amount["min"], FEE_TAKER, $combinations[$i], $stepOne_sell_amount, $stepTwo_sell_amount, $stepOne_buy_amount, $stepTwo_buy_amount, $stepThree_buy_amount, $stepThree_sell_amount, $stepOne_amountAsset, $stepOne_amount_decimals, $stepOne_sell_price, $stepOne_price_decimals, $stepOne_buy_price, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepTwo_amount_decimals, $stepTwo_sell_price, $stepTwo_price_decimals, $stepTwo_buy_price, $stepThree_amountAsset, $stepThree_sell_price, $stepThree_price_decimals, $stepThree_buy_price, $stepThree_amount_decimals);

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
            } elseif ($bot->format($deal_amount["min"]) >= $bot->format($max_deal_amount)) {
                $reason = "Maximum reached";
                break;
            }

        }

        if (DEBUG_STATUS === true) $html .= $bot->CalcVisualizationDelimeter($reason);
    }

    $cycle_time = round(microtime(true) - $cycle_time_start, 3);

    if (count($plus_results) > 0) {

        // Choosing the best result
        $best_result = $bot->getBestResult($plus_results);

        if ($best_place_step_one === [$best_result["step_one"]["amount"], $best_result["step_one"]["price"]] && ((time() - $deal_time) < 2)) {
            echo "Error: duplicate best place (step 1)" . PHP_EOL;
            continue;
        } elseif ($best_place_step_two === [$best_result["step_two"]["amount"], $best_result["step_two"]["price"]] && ((time() - $deal_time) < 2)) {
            echo "Error: duplicate best place (step 2)" . PHP_EOL;
            continue;
        } elseif ($best_place_step_three === [$best_result["step_three"]["amount"], $best_result["step_three"]["price"]] && ((time() - $deal_time) < 2)) {
            echo "Error: duplicate best place (step 3)" . PHP_EOL;
            continue;
        }

        // Orders execution time countdown start
        $order_execution_time_start = microtime(true);

        // Make orders
        $orders = $bot->makeParallelOrders($exchange, $best_result);

        // Orders execution time
        $orders_execution_time = round(microtime(true) - $order_execution_time_start, 3);

        // Add expected result to DB and get last expected ID
        $last_expected_id = $bot->addExpectedResultToDB($db, $best_result, $rater);

        // Add real results to DB
        $add_real_orders = $bot->addRealResultToDB($db, $best_result, $orders, $rater, $last_expected_id, $orders_execution_time);

        echo "\$MONEY!\$ Time: $orders_execution_time s. | {$best_result["main_asset_name"]} -> {$best_result["asset_one_name"]} -> {$best_result["asset_two_name"]} | Deal amount: {$best_result["deal_amount"]} | RESULT: +" . $bot->format($best_result["result_in_main_asset"]) . " {$best_result["main_asset_name"]}" . PHP_EOL;
    }

    if (DEBUG_STATUS === true) $html .= $bot->CalcVisualizationFooter();

    if (DEBUG_STATUS === true) {
        $index = fopen(__DIR__ . "/cache/index.html", "w");
        fwrite($index, $html);
        fclose($index);
    }

    $total_time = round(microtime(true) - $total_time_start, 3);

    echo "Fetched " . count($markets_data) . " out of " . count($symbols) . " responses in $market_data_time s., $operations_count operations in $cycle_time s., total: $total_time" . PHP_EOL;

    usleep($pause);
}

$db == null;