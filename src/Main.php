<?php

namespace Src;

class Main
{

    public function DealAmount(
        $orderbook,
        $mainAsset_id,
        $mainAsset_decimals,
        $max_deal_amount
    ): array
    {

        //Step 1
        $deal_amount_stepOne = ($orderbook['step_one']['amountAsset'] == $mainAsset_id) ? $orderbook['step_one']['sell_amount'] : $orderbook['step_one']['buy_amount'] * $orderbook['step_one']['buy_price'];

        //Step 2
        if ($orderbook['step_two']['amountAsset'] == $orderbook['step_one']['amountAsset']) $deal_amount_stepTwo = $orderbook['step_two']['sell_amount'] * $orderbook['step_one']['buy_price'];
        elseif ($orderbook['step_two']['amountAsset'] == $orderbook['step_one']['priceAsset']) $deal_amount_stepTwo = $orderbook['step_two']['sell_amount'] / $orderbook['step_one']['sell_price'];
        elseif ($orderbook['step_two']['priceAsset'] == $orderbook['step_one']['amountAsset']) $deal_amount_stepTwo = $orderbook['step_two']['buy_amount'] * $orderbook['step_two']['buy_price'] * $orderbook['step_one']['buy_price'];
        elseif ($orderbook['step_two']['priceAsset'] == $orderbook['step_one']['priceAsset']) $deal_amount_stepTwo = $orderbook['step_two']['buy_amount'] * $orderbook['step_two']['buy_price'] / $orderbook['step_one']['sell_price'];

        //Step 3
        if ($orderbook['step_three']['amountAsset'] == $orderbook['step_two']['amountAsset'] && $orderbook['step_three']['priceAsset'] == $orderbook['step_one']['amountAsset']) $deal_amount_stepThree = $orderbook['step_three']['sell_amount'] * $orderbook['step_two']['buy_price'] / $orderbook['step_one']['sell_price'];
        elseif ($orderbook['step_three']['amountAsset'] == $orderbook['step_two']['amountAsset'] && $orderbook['step_three']['priceAsset'] == $orderbook['step_one']['priceAsset']) $deal_amount_stepThree = $orderbook['step_three']['sell_amount'] * $orderbook['step_two']['buy_price'] * $orderbook['step_one']['buy_price'];
        elseif ($orderbook['step_three']['amountAsset'] == $orderbook['step_two']['priceAsset'] && $orderbook['step_three']['priceAsset'] == $orderbook['step_one']['priceAsset']) $deal_amount_stepThree = $orderbook['step_three']['sell_amount'] / $orderbook['step_two']['sell_price'] * $orderbook['step_one']['buy_price'];
        elseif ($orderbook['step_three']['priceAsset'] == $orderbook['step_two']['priceAsset'] && $orderbook['step_three']['amountAsset'] == $orderbook['step_one']['priceAsset']) $deal_amount_stepThree = $orderbook['step_three']['buy_amount'] * $orderbook['step_three']['buy_price'] / $orderbook['step_two']['sell_price'] * $orderbook['step_one']['buy_price'];
        elseif ($orderbook['step_three']['priceAsset'] == $orderbook['step_two']['priceAsset'] && $orderbook['step_three']['amountAsset'] == $orderbook['step_one']['amountAsset']) $deal_amount_stepThree = $orderbook['step_three']['buy_amount'] * $orderbook['step_three']['buy_price'] / $orderbook['step_two']['sell_price'] / $orderbook['step_one']['sell_price'];
        elseif ($orderbook['step_three']['priceAsset'] == $orderbook['step_two']['amountAsset'] && $orderbook['step_three']['amountAsset'] == $orderbook['step_one']['amountAsset']) $deal_amount_stepThree = $orderbook['step_three']['buy_amount'] * $orderbook['step_three']['buy_price'] * $orderbook['step_two']['buy_price'] / $orderbook['step_one']['sell_price'];

        $deal_amount_min = round(min($deal_amount_stepOne, $deal_amount_stepTwo ?? 0, $deal_amount_stepThree ?? 0, $max_deal_amount), $mainAsset_decimals);

        return [
            "min" => $deal_amount_min,
            "step_one" => round($deal_amount_stepOne, $mainAsset_decimals),
            "step_two" => round($deal_amount_stepTwo ?? 0, $mainAsset_decimals),
            "step_three" => round($deal_amount_stepThree ?? 0, $mainAsset_decimals)
        ];

    }

    public function MarketOrder(array $orderbook, float $amount, string $bidask, string $base_or_quote): bool|array
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

            }
        }

        $result[$bidask]["base_max"] = $base_amount_max;
        $result[$bidask]["quote_max"] = $quote_amount_max;

        if (!isset($result[$bidask]["amount"]) || $result[$bidask]["amount"] == 0) return false;
        else return $result;

    }

    public function getResult(
        $orderbook,
        $balances,
        $combinations,
        $deal_amount,
        $max_deal_amount
    ): array
    {

        $status = true;
        $reason = "";

        /* STEP 1 */
        if ($orderbook['step_one']['amountAsset'] == $combinations["main_asset_name"]) {

            $market_amount_step_one = $this->MarketOrder($orderbook["step_one"], $deal_amount, "bids", "base");

            if ($market_amount_step_one === false) {
                return [
                    "status" => false,
                    "reason" => "Market calculation error (step 1, sell)"
                ];
            }

            $stepOne = [
                "orderType" => "sell",
                "dom_position" => $orderbook['step_one']['dom_position'],
                "amountAsset" => $combinations["main_asset_name"],
                "priceAsset" => $combinations["asset_one_name"],
                "amountAssetName" => $combinations["main_asset_name"],
                "priceAssetName" => $combinations["asset_one_name"],
                "amount" => $deal_amount,
                "price" => $orderbook['step_one']['sell_price'],
                "result" => $market_amount_step_one["bids"]["amount"]

            ];

            // Balance check (step 1, sell)
            if ($deal_amount > $balances[$combinations["main_asset_name"]]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 1, sell). Asset: {$combinations["main_asset_name"]} ({$balances[$combinations["main_asset_name"]]["free"]} < $deal_amount)";
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
                "dom_position" => $orderbook['step_one']['dom_position'],
                "amountAsset" => $combinations["asset_one_name"],
                "priceAsset" => $combinations["main_asset_name"],
                "amountAssetName" => $combinations["asset_one_name"],
                "priceAssetName" => $combinations["main_asset_name"],
                "amount" => $deal_amount / $orderbook['step_one']['buy_price'],
                "price" => $orderbook['step_one']['buy_price'],
                "result" => $step_one_result
            ];

            // Balance check (step 1, buy)
            if ($deal_amount > $balances[$combinations["main_asset_name"]]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 1, buy). Asset: {$combinations["main_asset_name"]} ({$balances[$combinations["main_asset_name"]]["free"]} < $deal_amount)";
            }

        }

        // Subtract fee (step 1)
        $stepOne["result"] = (FEE_TYPE === "percentages") ? $stepOne["result"] - $stepOne["result"] / 100 * FEE_TAKER : $stepOne["result"];

        // Amount limit check (step 1)
        $min_amount_step_one = $orderbook["step_one"]["limits"]["amount"]["min"] ?? 0;

        if ($min_amount_step_one > $stepOne["amount"]) {
            return [
                "status" => false,
                "reason" => "Amount limit error (step 1): {$combinations["step_one_symbol"]} min amount: $min_amount_step_one, current amount: {$stepOne["amount"]}"
            ];
        }

        // Cost limit check (step 1)
        $cost_limit_step_one = $orderbook["step_one"]["limits"]["cost"]["min"] ?? 0;

        if ($cost_limit_step_one > $stepOne["amount"] * $stepOne["price"]) {
            return [
                "status" => false,
                "reason" => "Cost limit error (step 1): {$combinations["step_one_symbol"]} min cost: $cost_limit_step_one, current cost: " . ($stepOne["amount"] * $stepOne["price"])
            ];
        }

        /* STEP 2 */
        if ($orderbook['step_two']['amountAsset'] == $combinations["asset_one_name"]) {

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
                "dom_position" => $orderbook['step_two']['dom_position'],
                "amountAsset" => $combinations["asset_one_name"],
                "priceAsset" => $combinations["asset_two_name"],
                "amountAssetName" => $combinations["asset_one_name"],
                "priceAssetName" => $combinations["asset_two_name"],
                "amount" => $step_two_amount,
                "price" => $orderbook['step_two']['sell_price'],
                "result" => $market_amount_step_two["bids"]["amount"]
            ];

            // Balance check (step 1, sell)
            if ($stepOne["result"] > $balances[$orderbook['step_two']['amountAsset']]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 2, sell). Asset: {$stepTwo["amountAssetName"]} ({$balances[$orderbook['step_two']['amountAsset']]["free"]} < {$stepOne["result"]})";
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
                "dom_position" => $orderbook['step_two']['dom_position'],
                "amountAsset" => $combinations["asset_two_name"],
                "priceAsset" => $combinations["asset_one_name"],
                "amountAssetName" => $combinations["asset_two_name"],
                "priceAssetName" => $combinations["asset_one_name"],
                "amount" => $stepOne["result"] / $orderbook['step_two']['buy_price'],
                "price" => $orderbook['step_two']['buy_price'],
                "result" => $step_two_result
            ];

            // Balance check (step 2, buy)
            if ($stepOne["result"] > $balances[$orderbook['step_two']['priceAsset']]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 2, buy). Asset: {$stepTwo["priceAssetName"]} ({$balances[$orderbook['step_two']['priceAsset']]["free"]} < {$stepOne["result"]})";
            }

        }

        // Amount limit check (step 2)
        $min_amount_step_two = $orderbook["step_two"]["limits"]["amount"]["min"] ?? 0;

        if ($min_amount_step_two > $stepTwo["amount"]) {
            return [
                "status" => false,
                "reason" => "Amount limit error (step 2): {$combinations["step_two_symbol"]} min amount: $min_amount_step_two, current amount: {$stepTwo["amount"]}"
            ];
        }

        // Cost limit check (step 2)
        $cost_limit_step_two = $orderbook["step_two"]["limits"]["cost"]["min"] ?? 0;

        if ($cost_limit_step_two > $stepTwo["amount"] * $stepTwo["price"]) {
            return [
                "status" => false,
                "reason" => "Cost limit error (step 2): {$combinations["step_two_symbol"]} min cost: $cost_limit_step_two, current cost: " . ($stepTwo["amount"] * $stepTwo["price"])
            ];
        }

        // Subtract fee (step 2)
        $stepTwo["result"] = (FEE_TYPE === "percentages") ? $stepTwo["result"] - $stepTwo["result"] / 100 * FEE_TAKER : $stepTwo["result"];

        /* STEP 3 */
        if ($orderbook['step_three']['amountAsset'] != $combinations["main_asset_name"]) {

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
                "dom_position" => $orderbook['step_three']['dom_position'],
                "amountAsset" => $combinations["asset_two_name"],
                "priceAsset" => $combinations["main_asset_name"],
                "amountAssetName" => $combinations["asset_two_name"],
                "priceAssetName" => $combinations["main_asset_name"],
                "amount" => $step_three_amount,
                "price" => $orderbook['step_three']['sell_price'],
                "result" => $market_amount_step_three["bids"]["amount"]
            ];

            // Balance check (step 2, sell)
            if ($stepTwo["result"] > $balances[$orderbook['step_three']['amountAsset']]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 3, sell). Asset: {$stepThree["amountAssetName"]} ({$balances[$orderbook['step_three']['amountAsset']]["free"]} < {$stepTwo["result"]})";
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
                "dom_position" => $orderbook['step_three']['dom_position'],
                "amountAsset" => $combinations["main_asset_name"],
                "priceAsset" => $combinations["asset_two_name"],
                "amountAssetName" => $combinations["main_asset_name"],
                "priceAssetName" => $combinations["asset_two_name"],
                "amount" => $stepTwo["result"] / $orderbook['step_three']['buy_price'],
                "price" => $orderbook['step_three']['buy_price'],
                "result" => $step_three_result

            ];

            // Balance check (step 2, buy)
            if ($step_three_result > $balances[$combinations["main_asset_name"]]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 3, buy). Asset: {$combinations["main_asset_name"]} ({$balances[$combinations["main_asset_name"]]["free"]} < $step_three_result)";
            }

        }

        //Amount limit check (step 3)
        $min_amount_step_three = $orderbook["step_three"]["limits"]["amount"]["min"] ?? 0;

        if ($min_amount_step_three > $stepThree["amount"]) {
            return [
                "status" => false,
                "reason" => "Amount limit error (step 3): {$combinations["step_three_symbol"]} min amount: $min_amount_step_three, current amount: {$stepThree["amount"]}"
            ];
        }

        // Cost limit check (step 3)
        $cost_limit_step_three = $orderbook["step_three"]["limits"]["cost"]["min"] ?? 0;

        if ($cost_limit_step_three > $stepThree["amount"] * $stepThree["price"]) {
            return [
                "status" => false,
                "reason" => "Cost limit error (step 3): {$combinations["step_three_symbol"]} min cost: $cost_limit_step_three, current cost: " . ($stepThree["amount"] * $stepThree["price"])
            ];
        }

        // Subtract fee (step 3)
        $stepThree["result"] = (FEE_TYPE === "percentages") ? $stepThree["result"] - $stepThree["result"] / 100 * FEE_TAKER : $stepThree["result"];

        $result = round(($stepThree["result"] - $deal_amount), 8);

        $expected_data = [
            "fee" => FEE_TAKER,
            "stepOne_sell_price" => $orderbook['step_one']['sell_price'],
            "stepOne_sell_amount" => $orderbook['step_one']['sell_amount'],
            "stepOne_buy_price" => $orderbook['step_one']['buy_price'],
            "stepOne_buy_amount" => $orderbook['step_one']['buy_amount'],
            "stepTwo_sell_price" => $orderbook['step_two']['sell_price'],
            "stepTwo_sell_amount" => $orderbook['step_two']['sell_amount'],
            "stepTwo_buy_price" => $orderbook['step_two']['buy_price'],
            "stepTwo_buy_amount" => $orderbook['step_two']['buy_amount'],
            "stepThree_sell_price" => $orderbook['step_three']['sell_price'],
            "stepThree_sell_amount" => $orderbook['step_three']['sell_amount'],
            "stepThree_buy_price" => $orderbook['step_three']['buy_price'],
            "stepThree_buy_amount" => $orderbook['step_three']['buy_amount'],
            "max_deal_amount" => $max_deal_amount
        ];

        return [
            "result" => $result,
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

    /**
     * Gets the best of results
     *
     * @param array $plus_results
     * @return array
     */
    public function getBestResult(array $plus_results): array
    {
        $plus_results = array_values($plus_results);
        $best_results = array_column($plus_results, "result_in_main_asset");
        $best_key = array_keys($best_results, max($best_results));

        return $plus_results[$best_key["0"]];
    }

    public function CalcVisualizationHeader(array $orderbooks, string $current_symbol): string
    {

        $date = date("d.m.y H:i:s", time());

        return <<<HTML
         <style> body {font-family: monospace;} table {border-collapse: collapse;} td, th {border: 1px solid #000; padding: 5px;} th {font-weight: bold;}</style>
         <i>Current symbol: <b>$current_symbol</b>. Generated: $date</i><br /><br />

        <table>
        <tr><th>#</th><th>Triangle</th><th>Step 1</th><th>Step 2</th><th>Step 3</th><th>Result</th></tr>
        HTML;
    }

    public function CalcVisualizationBody($i, $step_one_dom_position, $step_two_dom_position, $step_three_dom_position, $result, $waves_deal_amount, $deal_amount, $fee, $combinations, $stepOne_sell_amount, $stepTwo_sell_amount, $stepOne_buy_amount, $stepTwo_buy_amount, $stepThree_buy_amount, $stepThree_sell_amount, $stepOne_amountAsset, $stepOne_amount_decimals, $stepOne_sell_price, $stepOne_price_decimals, $stepOne_buy_price, $stepTwo_amountAsset, $stepTwo_priceAsset, $stepTwo_amount_decimals, $stepTwo_sell_price, $stepTwo_price_decimals, $stepTwo_buy_price, $stepThree_amountAsset, $stepThree_sell_price, $stepThree_price_decimals, $stepThree_buy_price, $stepThree_amount_decimals): string
    {

        $table = "<tr><td>$i</td>";

        $calculations = "<td><strong>{$combinations["main_asset_name"]} -> {$combinations["asset_one_name"]} -> {$combinations["asset_two_name"]}</strong><br /><small>Deal: " . $this->format($deal_amount) . " {$combinations["main_asset_name"]}<br />Max: $waves_deal_amount<br />Fee: " . $this->format($fee) . "</small></td>";

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

    public function CalcVisualizationDelimeter($reason): string
    {
        $html = '<tr><td colspan="6" style="background-color: grey; color: #fff; text-align: center;">' . $reason . '</td></tr>';
        $html .= '<tr><td colspan="6" style="border-left: 0; border-right: 0;">&nbsp;</td></tr>';

        return $html;
    }

    public function CalcVisualizationFooter(): string
    {
        return '</table>';
    }

    public function format($float, $decimals = 8): string
    {
        return number_format($float, $decimals, ".", "");
    }

}