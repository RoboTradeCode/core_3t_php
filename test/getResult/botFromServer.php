<?php

$deal_amount = ["min" => 0, "step_one" => 0, "step_two" => 0, "step_three" => 0];

$reason = "";

$depth = 0;

$orderbook_info = [
    'step_one' => [
        'sell_price' => 0,
        'buy_price' => 0,
        'sell_amount' => 0,
        'buy_amount' => 0,
        'dom_position' => 0
    ],
    'step_two' => [
        'sell_price' => 0,
        'buy_price' => 0,
        'sell_amount' => 0,
        'buy_amount' => 0,
        'dom_position' => 0
    ],
    'step_three' => [
        'sell_price' => 0,
        'buy_price' => 0,
        'sell_amount' => 0,
        'buy_amount' => 0,
        'dom_position' => 0
    ],
];

// DOM calculation
while (1) {

    ###<DEAL VARIABLES>###
    //Step 1
    if ($deal_amount["step_one"] < $max_deal_amount) {

        $orderbook_info['step_one']['buy_price'] = (isset($orderbook["step_one"]["asks"][$orderbook_info['step_one']['dom_position']]["0"])) ? $orderbook["step_one"]["asks"][$orderbook_info['step_one']['dom_position']]["0"] : 0;
        $orderbook_info['step_one']['sell_price'] = (isset($orderbook["step_one"]["bids"][$orderbook_info['step_one']['dom_position']]["0"])) ? $orderbook["step_one"]["bids"][$orderbook_info['step_one']['dom_position']]["0"] : 0;

        $orderbook_info['step_one']['buy_amount'] += (isset($orderbook["step_one"]["asks"][$orderbook_info['step_one']['dom_position']]["1"])) ? $orderbook["step_one"]["asks"][$orderbook_info['step_one']['dom_position']]["1"] : 0;
        $orderbook_info['step_one']['sell_amount'] += (isset($orderbook["step_one"]["bids"][$orderbook_info['step_one']['dom_position']]["1"])) ? $orderbook["step_one"]["bids"][$orderbook_info['step_one']['dom_position']]["1"] : 0;

        $orderbook_info['step_one']['dom_position']++;

    }

    //Step 2
    if ($deal_amount["step_two"] < $max_deal_amount) {

        $orderbook_info['step_two']['buy_price'] = (isset($orderbook["step_two"]["asks"][$orderbook_info['step_two']['dom_position']]["0"])) ? $orderbook["step_two"]["asks"][$orderbook_info['step_two']['dom_position']]["0"] : 0;
        $orderbook_info['step_two']['sell_price'] = (isset($orderbook["step_two"]["bids"][$orderbook_info['step_two']['dom_position']]["0"])) ? $orderbook["step_two"]["bids"][$orderbook_info['step_two']['dom_position']]["0"] : 0;

        $orderbook_info['step_two']['buy_amount'] += (isset($orderbook["step_two"]["asks"][$orderbook_info['step_two']['dom_position']]["1"])) ? $orderbook["step_two"]["asks"][$orderbook_info['step_two']['dom_position']]["1"] : 0;
        $orderbook_info['step_two']['sell_amount'] += (isset($orderbook["step_two"]["bids"][$orderbook_info['step_two']['dom_position']]["1"])) ? $orderbook["step_two"]["bids"][$orderbook_info['step_two']['dom_position']]["1"] : 0;

        $orderbook_info['step_two']['dom_position']++;

    }

    //Step 3
    if ($deal_amount["step_three"] < $max_deal_amount) {

        $orderbook_info['step_three']['buy_price'] = (isset($orderbook["step_three"]["asks"][$orderbook_info['step_three']['dom_position']]["0"])) ? $orderbook["step_three"]["asks"][$orderbook_info['step_three']['dom_position']]["0"] : 0;
        $orderbook_info['step_three']['sell_price'] = (isset($orderbook["step_three"]["bids"][$orderbook_info['step_three']['dom_position']]["0"])) ? $orderbook["step_three"]["bids"][$orderbook_info['step_three']['dom_position']]["0"] : 0;

        $orderbook_info['step_three']['buy_amount'] += (isset($orderbook["step_three"]["asks"][$orderbook_info['step_three']['dom_position']]["1"])) ? $orderbook["step_three"]["asks"][$orderbook_info['step_three']['dom_position']]["1"] : 0;
        $orderbook_info['step_three']['sell_amount'] += (isset($orderbook["step_three"]["bids"][$orderbook_info['step_three']['dom_position']]["1"])) ? $orderbook["step_three"]["bids"][$orderbook_info['step_three']['dom_position']]["1"] : 0;

        $orderbook_info['step_three']['dom_position']++;

    }
    ###</DEAL VARIABLES>###

    $deal_amount = $bot->DealAmount($max_deal_amount, $combinations[$i]["step_one_amount_decimals"], $combinations[$i]["main_asset_name"], $stepOne_amountAsset, $stepOne_priceAsset, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepThree_amountAsset, $stepThree_priceAsset, $orderbook_info['step_one']['buy_price'], $orderbook_info['step_one']['sell_price'], $orderbook_info['step_one']['buy_amount'], $orderbook_info['step_one']['sell_amount'], $orderbook_info['step_two']['buy_price'], $orderbook_info['step_two']['sell_price'], $orderbook_info['step_two']['buy_amount'], $orderbook_info['step_two']['sell_amount'], $orderbook_info['step_three']['buy_price'], $orderbook_info['step_three']['sell_price'], $orderbook_info['step_three']['buy_amount'], $orderbook_info['step_three']['sell_amount']);

    $result = $bot->getResult($orderbook, $deal_amount["min"], $rater, FEE_TAKER, $combinations[$i], $stepOne_amountAsset, $stepOne_amount_decimals, $orderbook_info['step_one']['sell_price'], $stepOne_price_decimals, $orderbook_info['step_one']['buy_price'], $stepTwo_amountAsset, $stepTwo_priceAsset, $stepTwo_amount_decimals, $orderbook_info['step_two']['sell_price'], $stepTwo_price_decimals, $orderbook_info['step_two']['buy_price'], $stepThree_amountAsset, $orderbook_info['step_three']['sell_price'], $stepThree_price_decimals, $orderbook_info['step_three']['buy_price'], $stepThree_amount_decimals, $orderbook_info['step_one']['dom_position'], $orderbook_info['step_two']['dom_position'], $orderbook_info['step_three']['dom_position'], $orderbook_info['step_one']['sell_amount'], $orderbook_info['step_one']['buy_amount'], $orderbook_info['step_two']['sell_amount'], $orderbook_info['step_two']['buy_amount'], $orderbook_info['step_three']['sell_amount'], $orderbook_info['step_three']['buy_amount'], $max_deal_amount);


    if ($result["status"] === true && $result["result_in_main_asset"] > MIN_PROFIT) {
        $plus_results[$i] = $result;
        $plus_results[$i]["info"] = $combinations[$i];
    }

}
