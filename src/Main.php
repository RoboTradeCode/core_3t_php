<?php

namespace Src;

use Throwable;

class Main
{

    /**
     * Возвращает результат треугольника
     *
     * @param float $max_deal_amount Максимальный размер сделки в main_asset
     * @param int $max_depth Максимальная глубина в стакан
     * @param array $rates Курсы
     * @param array $combinations Комбинация
     * @param array $orderbook Три шага ордербука
     * @param array $balances Балансы
     * @return array Отдает массив результатов и reason
     */
    public function getResults(
        float $max_deal_amount,
        int $max_depth,
        array $rates,
        array $combinations,
        array $orderbook,
        array $balances,
    ): array
    {

        $results = [];
        $depth = 0;
        $deal_amount = ["min" => 0, "step_one" => 0, "step_two" => 0, "step_three" => 0];
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

        while (true) {

            $this->getOrderbookInfo($orderbook_info, $orderbook, $deal_amount, $max_deal_amount);

            try {

                $deal_amount = $this->DealAmount(
                    $orderbook,
                    $orderbook_info,
                    $combinations['main_asset_name'],
                    $combinations['main_asset_amount_precision'],
                    $max_deal_amount
                );

            } catch(Throwable $e) {

                echo '[' . date('Y-m-d H:i:s') . '] Division by zero Deal Amount. Error Message: ' . $e->getMessage() . PHP_EOL;

                break;

            }

            $result = $this->findResult(
                $orderbook,
                $orderbook_info,
                $balances,
                $combinations,
                $rates,
                $deal_amount['min'],
                $max_deal_amount
            );

            if ($result["status"]) {

                $results[] = $result;

            } elseif (!$result["status"]) {

                $reason = $result["reason"];

                break;

            }

            if (
                $reason = $this->findReason(
                    $result,
                    $depth,
                    $max_depth,
                    $orderbook_info,
                    $orderbook,
                    $combinations,
                    $deal_amount,
                    $max_deal_amount
                )
            ) {

                break;

            }

        }

        return [
            'results' => $results,
            'reason' => $reason,
        ];

    }

    /**
     * Находит причину выхода из глубины стакана
     *
     * @param array $result Результат
     * @param int $depth Глубина
     * @param int $max_depth Максимальная глубина
     * @param array $orderbook_info Информация об шагах ордербуках
     * @param array $orderbook Три шага ордербука
     * @param array $combinations Комбинация
     * @param array $deal_amount Размер сделки
     * @param float $max_deal_amount Максимальный размер сделки
     * @return string Причина в виде текста
     */
    private function findReason(
        array $result,
        int &$depth,
        int $max_depth,
        array $orderbook_info,
        array $orderbook,
        array $combinations,
        array $deal_amount,
        float $max_deal_amount
    ): string
    {

        if (!$result["status"]) {
            return $result["reason"];
        } elseif ($depth++ > $max_depth) {
            return "Maximum depth";
        } elseif (
            $combinations["main_asset_name"] == $orderbook['step_one']['amountAsset'] &&
            !isset($orderbook["step_one"]["bids"][$orderbook_info['step_one']['dom_position']]["1"])
        ) {
            return "End of DOM (step 1, bids, position " . $orderbook_info['step_one']['dom_position'] . "). Pair:" . $orderbook['step_one']['amountAsset'] . "/" . $orderbook['step_one']['priceAsset'];
        } elseif (
            $combinations["main_asset_name"] == $orderbook['step_one']['priceAsset'] &&
            !isset($orderbook["step_one"]["asks"][$orderbook_info['step_one']['dom_position']]["1"])
        ) {
            return "End of DOM (step 1, asks, position " . $orderbook_info['step_one']['dom_position'] . "). Pair:" . $orderbook['step_one']['amountAsset'] . "/" . $orderbook['step_one']['priceAsset'];
        } elseif (
            $combinations["step_one_symbol"] == $orderbook['step_two']['amountAsset'] &&
            !isset($orderbook["step_two"]["bids"][$orderbook_info['step_two']['dom_position']]["1"])
        ) {
            return "End of DOM (step 2, bids, position " . $orderbook_info['step_two']['dom_position'] . "). Pair:" . $orderbook['step_two']['amountAsset'] . "/" . $orderbook['step_two']['priceAsset'] . ", amount: " . $orderbook_info['step_two']['sell_amount'] . ", price: " . $orderbook_info['step_two']['sell_price'];
        } elseif (
            $combinations["step_one_symbol"] == $orderbook['step_two']['priceAsset'] &&
            !isset($orderbook["step_two"]["asks"][$orderbook_info['step_two']['dom_position']]["1"])
        ) {
            return "End of DOM (step 2, asks, position " . $orderbook_info['step_two']['dom_position'] . "). Pair:" . $orderbook['step_two']['amountAsset'] . "/" . $orderbook['step_two']['priceAsset'] . ", amount: " . $orderbook_info['step_two']['sell_amount'] . ", price: " . $orderbook_info['step_two']['sell_price'];
        } elseif (
            $combinations["asset_two_name"] === $orderbook['step_three']['amountAsset'] &&
            !isset($orderbook["step_three"]["bids"][$orderbook_info['step_three']['dom_position']]["1"])
        ) {
            return "End of DOM (step 3, bids, position " . $orderbook_info['step_three']['dom_position'] . "). Pair: " . $orderbook['step_three']['amountAsset'] . "/" . $orderbook['step_three']['priceAsset'];
        } elseif (
            $combinations["asset_two_name"] === $orderbook['step_three']['priceAsset'] &&
            !isset($orderbook["step_three"]["asks"][$orderbook_info['step_three']['dom_position']]["1"])
        ) {
            return "End of DOM (step 3, asks, position " . $orderbook_info['step_three']['dom_position'] . "). Pair: " . $orderbook['step_three']['amountAsset'] . "/" . $orderbook['step_three']['priceAsset'];
        } elseif ($deal_amount["min"] >= $max_deal_amount) {
            return "Maximum reached";
        }

        return '';

    }

    /**
     * Обновляет информацию в $orderbook_info
     *
     * @param array $orderbook_info Информация об шагах ордербуках
     * @param array $orderbook Три шага ордербука
     * @param array $deal_amount Размер сделки
     * @param float $max_deal_amount Максимальный размер сделки
     * @return void
     */
    private function getOrderbookInfo(
        array &$orderbook_info,
        array $orderbook,
        array $deal_amount,
        float $max_deal_amount
    ): void
    {

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

    }

    /**
     * Отдает размер сделки в main_asset
     *
     * @param array $orderbook Три шага ордербука
     * @param array $orderbook_info Информация об шагах ордербуках
     * @param string $mainAsset_id Main_asset
     * @param float $mainAsset_decimals Decimals в main_asset
     * @param float $max_deal_amount Максимальный размер сделки
     * @return array Размер сделки
     */
    private function DealAmount(
        array $orderbook,
        array $orderbook_info,
        string $mainAsset_id,
        float $mainAsset_decimals,
        float $max_deal_amount
    ): array
    {

        //Step 1
        $deal_amount_stepOne = ($orderbook['step_one']['amountAsset'] == $mainAsset_id) ? $orderbook_info['step_one']['sell_amount'] : $orderbook_info['step_one']['buy_amount'] * $orderbook_info['step_one']['buy_price'];

        //Step 2
        if ($orderbook['step_two']['amountAsset'] == $orderbook['step_one']['amountAsset']) $deal_amount_stepTwo = $orderbook_info['step_two']['sell_amount'] * $orderbook_info['step_one']['buy_price'];
        elseif ($orderbook['step_two']['amountAsset'] == $orderbook['step_one']['priceAsset']) $deal_amount_stepTwo = $orderbook_info['step_two']['sell_amount'] / $orderbook_info['step_one']['sell_price'];
        elseif ($orderbook['step_two']['priceAsset'] == $orderbook['step_one']['amountAsset']) $deal_amount_stepTwo = $orderbook_info['step_two']['buy_amount'] * $orderbook_info['step_two']['buy_price'] * $orderbook_info['step_one']['buy_price'];
        elseif ($orderbook['step_two']['priceAsset'] == $orderbook['step_one']['priceAsset']) $deal_amount_stepTwo = $orderbook_info['step_two']['buy_amount'] * $orderbook_info['step_two']['buy_price'] / $orderbook_info['step_one']['sell_price'];

        //Step 3
        if ($orderbook['step_three']['amountAsset'] == $orderbook['step_two']['amountAsset'] && $orderbook['step_three']['priceAsset'] == $orderbook['step_one']['amountAsset']) $deal_amount_stepThree = $orderbook_info['step_three']['sell_amount'] * $orderbook_info['step_two']['buy_price'] / $orderbook_info['step_one']['sell_price'];
        elseif ($orderbook['step_three']['amountAsset'] == $orderbook['step_two']['amountAsset'] && $orderbook['step_three']['priceAsset'] == $orderbook['step_one']['priceAsset']) $deal_amount_stepThree = $orderbook_info['step_three']['sell_amount'] * $orderbook_info['step_two']['buy_price'] * $orderbook_info['step_one']['buy_price'];
        elseif ($orderbook['step_three']['amountAsset'] == $orderbook['step_two']['priceAsset'] && $orderbook['step_three']['priceAsset'] == $orderbook['step_one']['priceAsset']) $deal_amount_stepThree = $orderbook_info['step_three']['sell_amount'] / $orderbook_info['step_two']['sell_price'] * $orderbook_info['step_one']['buy_price'];
        elseif ($orderbook['step_three']['priceAsset'] == $orderbook['step_two']['priceAsset'] && $orderbook['step_three']['amountAsset'] == $orderbook['step_one']['priceAsset']) $deal_amount_stepThree = $orderbook_info['step_three']['buy_amount'] * $orderbook_info['step_three']['buy_price'] / $orderbook_info['step_two']['sell_price'] * $orderbook_info['step_one']['buy_price'];
        elseif ($orderbook['step_three']['priceAsset'] == $orderbook['step_two']['priceAsset'] && $orderbook['step_three']['amountAsset'] == $orderbook['step_one']['amountAsset']) $deal_amount_stepThree = $orderbook_info['step_three']['buy_amount'] * $orderbook_info['step_three']['buy_price'] / $orderbook_info['step_two']['sell_price'] / $orderbook_info['step_one']['sell_price'];
        elseif ($orderbook['step_three']['priceAsset'] == $orderbook['step_two']['amountAsset'] && $orderbook['step_three']['amountAsset'] == $orderbook['step_one']['amountAsset']) $deal_amount_stepThree = $orderbook_info['step_three']['buy_amount'] * $orderbook_info['step_three']['buy_price'] * $orderbook_info['step_two']['buy_price'] / $orderbook_info['step_one']['sell_price'];

        $deal_amount_min = $this->incrementNumber(min($deal_amount_stepOne, $deal_amount_stepTwo ?? 0, $deal_amount_stepThree ?? 0, $max_deal_amount), $mainAsset_decimals);

        return [
            "min" => $deal_amount_min,
            "step_one" => $this->incrementNumber($deal_amount_stepOne, $mainAsset_decimals),
            "step_two" => $this->incrementNumber($deal_amount_stepTwo ?? 0, $mainAsset_decimals),
            "step_three" => $this->incrementNumber($deal_amount_stepThree ?? 0, $mainAsset_decimals)
        ];

    }

    /**
     * Считает результат
     *
     * @param array $orderbook Три шага ордербука
     * @param float $amount Количество
     * @param string $bidask Sell/Buy
     * @return bool|array Результат или false если что-то сломалось
     */
    private function MarketOrder(array $orderbook, float $amount, string $bidask): bool|array
    {

        if ($bidask != "bids" && $bidask != "asks") return false;

        if (isset($orderbook[$bidask]) && count($orderbook[$bidask]) > 0) $dom_count = count($orderbook[$bidask]);
        else return false;

        $base_amount_sum = $quote_amount_sum = $quote_amount_max = $base_amount_max = 0;

        for ($i = 0; $i < $dom_count; $i++) {

            $current_amount = $orderbook[$bidask][$i]["1"];
            $current_price = $orderbook[$bidask][$i]["0"];

            $quote_amount = $current_amount * $current_price;

            $quote_amount_max += $quote_amount;
            $base_amount_max += $current_amount;

            if (($bidask == "bids" && $base_amount_sum < $amount) || ($bidask == "asks" && $quote_amount_sum < $amount)) {

                $quote_amount_sum += $quote_amount;
                $base_amount_sum += $current_amount;

                if ($bidask == "bids") {

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

    /**
     * Находит результат
     *
     * @param array $orderbook Три шага ордербука
     * @param array $orderbook_info Информация об шагах ордербуках
     * @param array $balances Балансы
     * @param array $combinations Комбинация
     * @param array $rates Курсы из настроек
     * @param float $deal_amount Размер сделки
     * @param float $max_deal_amount Максимальный размер сделки
     * @return array Результат
     */
    private function findResult(
        array $orderbook,
        array $orderbook_info,
        array $balances,
        array $combinations,
        array $rates,
        float $deal_amount,
        float $max_deal_amount
    ): array
    {

        $status = true;
        $reason = "";

        /* STEP 1 */
        if ($orderbook['step_one']['amountAsset'] == $combinations["main_asset_name"]) {

            $market_amount_step_one = $this->MarketOrder($orderbook["step_one"], $deal_amount, "bids");

            if ($market_amount_step_one === false) {
                return [
                    "status" => false,
                    "reason" => "Market calculation error (step 1, sell)"
                ];
            }

            $stepOne = [
                "orderType" => "sell",
                "dom_position" => $orderbook_info['step_one']['dom_position'],
                "amountAsset" => $combinations["main_asset_name"],
                "priceAsset" => $combinations["asset_one_name"],
                "amountAssetName" => $combinations["main_asset_name"],
                "priceAssetName" => $combinations["asset_one_name"],
                "amount" => $this->incrementNumber($deal_amount, $orderbook["step_one"]['amount_increment']),
                "price" => $orderbook_info['step_one']['sell_price'],
                "exchange" => $orderbook['step_one']['exchange'],
                "result" => $market_amount_step_one["bids"]["amount"]

            ];

            // Balance check (step 1, sell)
            if ($deal_amount > $balances[$combinations["main_asset_name"]]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 1, sell). Asset: {$combinations["main_asset_name"]} ({$balances[$combinations["main_asset_name"]]["free"]} < $deal_amount)";
            }

        } else {

            $market_amount_step_one = $this->MarketOrder($orderbook["step_one"], $deal_amount, "asks");

            if ($market_amount_step_one === false) {
                return [
                    "status" => false,
                    "reason" => "Market calculation error (step 1, buy)"
                ];
            }

            $stepOne = [
                "orderType" => "buy",
                "dom_position" => $orderbook_info['step_one']['dom_position'],
                "amountAsset" => $combinations["asset_one_name"],
                "priceAsset" => $combinations["main_asset_name"],
                "amountAssetName" => $combinations["asset_one_name"],
                "priceAssetName" => $combinations["main_asset_name"],
                "amount" => $this->incrementNumber($deal_amount / $orderbook_info['step_one']['buy_price'], $orderbook["step_one"]['amount_increment']),
                "price" => $orderbook_info['step_one']['buy_price'],
                "exchange" => $orderbook['step_one']['exchange'],
                "result" => $market_amount_step_one["asks"]["amount"],
            ];

            // Balance check (step 1, buy)
            if ($deal_amount > $balances[$combinations["main_asset_name"]]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 1, buy). Asset: {$combinations["main_asset_name"]} ({$balances[$combinations["main_asset_name"]]["free"]} < $deal_amount)";
            }

        }

        // Subtract fee (step 1)
        $stepOne["result"] = $this->incrementNumber(
            $stepOne["result"] - $stepOne["result"] / 100 * $orderbook["step_one"]['fee'],
            $orderbook["step_one"]['amount_increment']
        );

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

            $market_amount_step_two = $this->MarketOrder($orderbook["step_two"], $stepOne["result"], "bids");

            if ($market_amount_step_two === false) {
                return [
                    "status" => false,
                    "reason" => "Market calculation error (step 2, sell)"
                ];
            }

            $stepTwo = [
                "orderType" => "sell",
                "dom_position" => $orderbook_info['step_two']['dom_position'],
                "amountAsset" => $combinations["asset_one_name"],
                "priceAsset" => $combinations["asset_two_name"],
                "amountAssetName" => $combinations["asset_one_name"],
                "priceAssetName" => $combinations["asset_two_name"],
                "amount" => $this->incrementNumber($stepOne["result"], $orderbook["step_two"]['amount_increment']),
                "price" => $orderbook_info['step_two']['sell_price'],
                "exchange" => $orderbook['step_two']['exchange'],
                "result" => $market_amount_step_two["bids"]["amount"]
            ];

            // Balance check (step 1, sell)
            if ($stepOne["result"] > $balances[$orderbook['step_two']['amountAsset']]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 2, sell). Asset: {$stepTwo["amountAssetName"]} ({$balances[$orderbook['step_two']['amountAsset']]["free"]} < {$stepOne["result"]})";
            }

        } else {

            $market_amount_step_two = $this->MarketOrder($orderbook["step_two"], $stepOne["result"], "asks");

            if ($market_amount_step_two === false) {
                return [
                    "status" => false,
                    "reason" => "Market calculation error (step 2, buy)"
                ];
            }

            $stepTwo = [
                "orderType" => "buy",
                "dom_position" => $orderbook_info['step_two']['dom_position'],
                "amountAsset" => $combinations["asset_two_name"],
                "priceAsset" => $combinations["asset_one_name"],
                "amountAssetName" => $combinations["asset_two_name"],
                "priceAssetName" => $combinations["asset_one_name"],
                "amount" => $this->incrementNumber($stepOne["result"] / $orderbook_info['step_two']['buy_price'], $orderbook["step_two"]['amount_increment']),
                "price" => $orderbook_info['step_two']['buy_price'],
                "exchange" => $orderbook['step_two']['exchange'],
                "result" => $market_amount_step_two["asks"]["amount"]
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
        $stepTwo["result"] = $this->incrementNumber(
            $stepTwo["result"] - $stepTwo["result"] / 100 * $orderbook["step_two"]['fee'],
            $orderbook["step_one"]['amount_increment']
        );

        /* STEP 3 */
        if ($orderbook['step_three']['amountAsset'] != $combinations["main_asset_name"]) {

            $market_amount_step_three = $this->MarketOrder($orderbook["step_three"], $stepTwo["result"], "bids");

            if ($market_amount_step_three === false) {
                return [
                    "status" => false,
                    "reason" => "Market calculation error (step 3, sell)"
                ];
            }

            $stepThree = [
                "orderType" => "sell",
                "dom_position" => $orderbook_info['step_three']['dom_position'],
                "amountAsset" => $combinations["asset_two_name"],
                "priceAsset" => $combinations["main_asset_name"],
                "amountAssetName" => $combinations["asset_two_name"],
                "priceAssetName" => $combinations["main_asset_name"],
                "amount" => $this->incrementNumber($stepTwo["result"], $orderbook["step_three"]['amount_increment']),
                "price" => $orderbook_info['step_three']['sell_price'],
                "exchange" => $orderbook['step_three']['exchange'],
                "result" => $market_amount_step_three["bids"]["amount"]
            ];

            // Balance check (step 2, sell)
            if ($stepTwo["result"] > $balances[$orderbook['step_three']['amountAsset']]["free"]) {
                $status = false;
                $reason = "Not enough balance (step 3, sell). Asset: {$stepThree["amountAssetName"]} ({$balances[$orderbook['step_three']['amountAsset']]["free"]} < {$stepTwo["result"]})";
            }

        } else {

            $market_amount_step_three = $this->MarketOrder($orderbook["step_three"], $stepTwo["result"], "asks");

            if ($market_amount_step_three === false) {
                return [
                    "status" => false,
                    "reason" => "Market calculation error (step 3, buy)"
                ];
            }

            $step_three_result = $market_amount_step_three["asks"]["amount"];

            $stepThree = [
                "orderType" => "buy",
                "dom_position" => $orderbook_info['step_three']['dom_position'],
                "amountAsset" => $combinations["main_asset_name"],
                "priceAsset" => $combinations["asset_two_name"],
                "amountAssetName" => $combinations["main_asset_name"],
                "priceAssetName" => $combinations["asset_two_name"],
                "amount" => $this->incrementNumber($stepTwo["result"] / $orderbook_info['step_three']['buy_price'], $orderbook["step_three"]['amount_increment']),
                "price" => $orderbook_info['step_three']['buy_price'],
                "exchange" => $orderbook['step_three']['exchange'],
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
        $stepThree["result"] = $this->incrementNumber(
            $stepThree["result"] - $stepThree["result"] / 100 * $orderbook["step_three"]['fee'],
            $orderbook["step_one"]['amount_increment']
        );

        $final_result = round(($stepThree["result"] - $deal_amount), 8);

        return [
            "result" => $final_result,
            "result_in_main_asset" => round($final_result * $rates[$combinations["main_asset_name"]], 8),
            "status" => $status,
            "reason" => $reason,
            "deal_amount" => $deal_amount,
            "main_asset_name" => $combinations["main_asset_name"],
            "asset_one_name" => $combinations["asset_one_name"],
            "asset_two_name" => $combinations["asset_two_name"],
            "step_one" => $stepOne,
            "step_two" => $stepTwo,
            "step_three" => $stepThree,
            "expected_data" => [
                "fee" => $orderbook['step_one']['fee'],
                "stepOne_sell_price" => $orderbook_info['step_one']['sell_price'],
                "stepOne_sell_amount" => $orderbook_info['step_one']['sell_amount'],
                "stepOne_buy_price" => $orderbook_info['step_one']['buy_price'],
                "stepOne_buy_amount" => $orderbook_info['step_one']['buy_amount'],
                "stepTwo_sell_price" => $orderbook_info['step_two']['sell_price'],
                "stepTwo_sell_amount" => $orderbook_info['step_two']['sell_amount'],
                "stepTwo_buy_price" => $orderbook_info['step_two']['buy_price'],
                "stepTwo_buy_amount" => $orderbook_info['step_two']['buy_amount'],
                "stepThree_sell_price" => $orderbook_info['step_three']['sell_price'],
                "stepThree_sell_amount" => $orderbook_info['step_three']['sell_amount'],
                "stepThree_buy_price" => $orderbook_info['step_three']['buy_price'],
                "stepThree_buy_amount" => $orderbook_info['step_three']['buy_amount'],
                "max_deal_amount" => $max_deal_amount
            ]
        ];

    }

    /**
     * Округляет число с нужным шагом
     *
     * @param float $number Значение в виде числа
     * @param float $increment С каким шагом нужно округлить число
     * @return float Округленное число с нужным шагом
     */
    private function incrementNumber(float $number, float $increment): float
    {

        return $increment * floor($number / $increment);

    }

}