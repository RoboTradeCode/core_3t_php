<?php

namespace Src;

use Throwable;

class Main
{

    /**
     * Made html vision for best result file into folder
     *
     * @param array $best_result The best result
     * @param string $file_path
     * @return void
     */
    public function madeHtmlVisionForBestResult(array $best_result, string $file_path): void
    {

        $date = date("d.m.y H:i:s", time());

        $html = <<<HTML
         <style> body {font-family: monospace;} table {border-collapse: collapse;} td, th {border: 1px solid #000; padding: 5px;} th {font-weight: bold;}</style>
         <i>Generated: $date</i><br /><br />

        <table>
        <tr><th>#</th><th>Triangle</th><th>Step 1</th><th>Step 2</th><th>Step 3</th><th>Result</th></tr>
        HTML;

        if ($best_result) {

            $result_first_step = ($best_result["step_one"]["orderType"] == 'buy') ? $best_result["deal_amount"] : $best_result["step_one"]["amount"];

            $table = "<tr><td>0</td>";

            $calculations = "<td><strong>{$best_result["main_asset_name"]} -> {$best_result["asset_one_name"]} -> {$best_result["asset_two_name"]}</strong><br /><small>Deal: " . $this->format($best_result["deal_amount"]) . " {$best_result["main_asset_name"]}<br />Max: {$best_result["expected_data"]["max_deal_amount"]}" . "</small></td>";

            $calculations .= "<td>Market: {$best_result["step_one"]["amountAssetName"]} -> {$best_result["step_one"]["priceAssetName"]} ({$best_result["step_one"]["orderType"]})<br />Position: {$best_result["step_one"]["dom_position"]}<br />Sell: {$best_result["expected_data"]["stepOne_sell_price"]} ({$best_result["expected_data"]["stepOne_sell_amount"]})<br />Buy: {$best_result["expected_data"]["stepOne_buy_price"]} ({$best_result["expected_data"]["stepOne_buy_amount"]})<br />Result: <span style=\"color: red;\">-{$result_first_step} {$best_result["main_asset_name"]}</span>, <span style=\"color: green;\">+" . $this->format($best_result["step_one"]["result"]) . " {$best_result["asset_one_name"]}</span><br />{$best_result["step_one"]["exchange"]}</td>";

            $calculations .= "<td>Market: {$best_result["step_two"]["amountAssetName"]} -> {$best_result["step_two"]["priceAssetName"]} ({$best_result["step_two"]["orderType"]})<br />Position: {$best_result["step_two"]["dom_position"]}<br />Sell: {$best_result["expected_data"]["stepTwo_sell_price"]} ({$best_result["expected_data"]["stepTwo_sell_amount"]})<br />Buy: {$best_result["expected_data"]["stepTwo_buy_price"]} ({$best_result["expected_data"]["stepTwo_buy_amount"]})<br />Result: <span style=\"color: red;\">-" . $this->format($best_result["step_one"]["result"]) . " {$best_result["asset_one_name"]}</span>, <span style=\"color: green;\">+" . $this->format($best_result["step_two"]["result"]) . " {$best_result["asset_two_name"]}</span><br />{$best_result["step_two"]["exchange"]}</td>";

            $calculations .= "<td>Market: {$best_result["step_three"]["amountAssetName"]} -> {$best_result["step_three"]["priceAssetName"]} ({$best_result["step_three"]["orderType"]})<br />Position: {$best_result["step_three"]["dom_position"]}<br />Sell: {$best_result["expected_data"]["stepThree_sell_price"]} ({$best_result["expected_data"]["stepThree_sell_amount"]})<br />Buy: {$best_result["expected_data"]["stepThree_buy_price"]} ({$best_result["expected_data"]["stepThree_buy_amount"]})<br />Result: <span style=\"color: red;\">-" . $this->format($best_result["step_two"]["result"]) . " {$best_result["asset_two_name"]}</span>, <span style=\"color: green;\">+" . $this->format($best_result["step_three"]["result"]) . " {$best_result["main_asset_name"]}</span><br />{$best_result["step_three"]["exchange"]}</td>";

            $calculations .= "<td><span style=\"color: " . (($best_result["result"] > 0) ? "green" : "red;") . ";\">" . $this->format($best_result["result"]) . " {$best_result["main_asset_name"]}</span></td>";

            $calculations .= "</tr>";

            $table .= $calculations;

            $html .= $table;

            $html .= '<tr><td colspan="6" style="background-color: grey; color: #fff; text-align: center;"> Best Result </td></tr>' . '<tr><td colspan="6" style="border-left: 0; border-right: 0;">&nbsp;</td></tr>';

            $html .= '</table>';

        }

        $index = fopen($file_path, 'w');

        fwrite($index, $html);

        fclose($index);

    }

    /**
     * Made html vision file into folder
     *
     * @param array $results All results
     * @param array $best_result The best result
     * @param array $orderbooks All orderbooks
     * @param array $balances All balances
     * @param string $file_path
     * @return void
     */
    public function madeHtmlVision(array $results, array $best_result, array $orderbooks, array $balances, string $file_path): void
    {

        $date = date("d.m.y H:i:s", time());

        $i = 1;

        $html = <<<HTML
         <style> body {font-family: monospace;} table {border-collapse: collapse;} td, th {border: 1px solid #000; padding: 5px;} th {font-weight: bold;}</style>
         <i>Generated: $date</i><br /><br />

        <table>
        <tr><th>#</th><th>Triangle</th><th>Step 1</th><th>Step 2</th><th>Step 3</th><th>Result</th></tr>
        HTML;

        if ($best_result) {

            $result_first_step = ($best_result["step_one"]["orderType"] == 'buy') ? $best_result["deal_amount"] : $best_result["step_one"]["amount"];

            $table = "<tr><td>0</td>";

            $calculations = "<td><strong>{$best_result["main_asset_name"]} -> {$best_result["asset_one_name"]} -> {$best_result["asset_two_name"]}</strong><br /><small>Deal: " . $this->format($best_result["deal_amount"]) . " {$best_result["main_asset_name"]}<br />Max: {$best_result["expected_data"]["max_deal_amount"]}" . "</small></td>";

            $calculations .= "<td>Market: {$best_result["step_one"]["amountAssetName"]} -> {$best_result["step_one"]["priceAssetName"]} ({$best_result["step_one"]["orderType"]})<br />Position: {$best_result["step_one"]["dom_position"]}<br />Sell: {$best_result["expected_data"]["stepOne_sell_price"]} ({$best_result["expected_data"]["stepOne_sell_amount"]})<br />Buy: {$best_result["expected_data"]["stepOne_buy_price"]} ({$best_result["expected_data"]["stepOne_buy_amount"]})<br />Result: <span style=\"color: red;\">-{$result_first_step} {$best_result["main_asset_name"]}</span>, <span style=\"color: green;\">+" . $this->format($best_result["step_one"]["result"]) . " {$best_result["asset_one_name"]}</span><br />{$best_result["step_one"]["exchange"]}</td>";

            $calculations .= "<td>Market: {$best_result["step_two"]["amountAssetName"]} -> {$best_result["step_two"]["priceAssetName"]} ({$best_result["step_two"]["orderType"]})<br />Position: {$best_result["step_two"]["dom_position"]}<br />Sell: {$best_result["expected_data"]["stepTwo_sell_price"]} ({$best_result["expected_data"]["stepTwo_sell_amount"]})<br />Buy: {$best_result["expected_data"]["stepTwo_buy_price"]} ({$best_result["expected_data"]["stepTwo_buy_amount"]})<br />Result: <span style=\"color: red;\">-" . $this->format($best_result["step_one"]["result"]) . " {$best_result["asset_one_name"]}</span>, <span style=\"color: green;\">+" . $this->format($best_result["step_two"]["result"]) . " {$best_result["asset_two_name"]}</span><br />{$best_result["step_two"]["exchange"]}</td>";

            $calculations .= "<td>Market: {$best_result["step_three"]["amountAssetName"]} -> {$best_result["step_three"]["priceAssetName"]} ({$best_result["step_three"]["orderType"]})<br />Position: {$best_result["step_three"]["dom_position"]}<br />Sell: {$best_result["expected_data"]["stepThree_sell_price"]} ({$best_result["expected_data"]["stepThree_sell_amount"]})<br />Buy: {$best_result["expected_data"]["stepThree_buy_price"]} ({$best_result["expected_data"]["stepThree_buy_amount"]})<br />Result: <span style=\"color: red;\">-" . $this->format($best_result["step_two"]["result"]) . " {$best_result["asset_two_name"]}</span>, <span style=\"color: green;\">+" . $this->format($best_result["step_three"]["result"]) . " {$best_result["main_asset_name"]}</span><br />{$best_result["step_three"]["exchange"]}</td>";

            $calculations .= "<td><span style=\"color: " . (($best_result["result"] > 0) ? "green" : "red;") . ";\">" . $this->format($best_result["result"]) . " {$best_result["main_asset_name"]}</span></td>";

            $calculations .= "</tr>";

            $table .= $calculations;

            $html .= $table;

            $html .= '<tr><td colspan="6" style="background-color: grey; color: #fff; text-align: center;"> Best Result </td></tr>' . '<tr><td colspan="6" style="border-left: 0; border-right: 0;">&nbsp;</td></tr>';

        }

        foreach ($results as $result) {

            foreach ($result['results'] as $res) {

                $res_first_step = ($res["step_one"]["orderType"] == 'buy') ? $res["deal_amount"] : $res["step_one"]["amount"];

                $table = "<tr><td>$i</td>";

                $calculations = "<td><strong>{$res["main_asset_name"]} -> {$res["asset_one_name"]} -> {$res["asset_two_name"]}</strong><br /><small>Deal: " . $this->format($res["deal_amount"]) . " {$res["main_asset_name"]}<br />Max: {$res["expected_data"]["max_deal_amount"]}" . "</small></td>";

                $calculations .= "<td>Market: {$res["step_one"]["amountAssetName"]} -> {$res["step_one"]["priceAssetName"]} ({$res["step_one"]["orderType"]})<br />Position: {$res["step_one"]["dom_position"]}<br />Sell: {$res["expected_data"]["stepOne_sell_price"]} ({$res["expected_data"]["stepOne_sell_amount"]})<br />Buy: {$res["expected_data"]["stepOne_buy_price"]} ({$res["expected_data"]["stepOne_buy_amount"]})<br />Result: <span style=\"color: red;\">-{$res_first_step} {$res["main_asset_name"]}</span>, <span style=\"color: green;\">+" . $this->format($res["step_one"]["result"]) . " {$res["asset_one_name"]}</span><br />{$res["step_one"]["exchange"]}</td>";

                $calculations .= "<td>Market: {$res["step_two"]["amountAssetName"]} -> {$res["step_two"]["priceAssetName"]} ({$res["step_two"]["orderType"]})<br />Position: {$res["step_two"]["dom_position"]}<br />Sell: {$res["expected_data"]["stepTwo_sell_price"]} ({$res["expected_data"]["stepTwo_sell_amount"]})<br />Buy: {$res["expected_data"]["stepTwo_buy_price"]} ({$res["expected_data"]["stepTwo_buy_amount"]})<br />Result: <span style=\"color: red;\">-" . $this->format($res["step_one"]["result"]) . " {$res["asset_one_name"]}</span>, <span style=\"color: green;\">+" . $this->format($res["step_two"]["result"]) . " {$res["asset_two_name"]}</span><br />{$res["step_two"]["exchange"]}</td>";

                $calculations .= "<td>Market: {$res["step_three"]["amountAssetName"]} -> {$res["step_three"]["priceAssetName"]} ({$res["step_three"]["orderType"]})<br />Position: {$res["step_three"]["dom_position"]}<br />Sell: {$res["expected_data"]["stepThree_sell_price"]} ({$res["expected_data"]["stepThree_sell_amount"]})<br />Buy: {$res["expected_data"]["stepThree_buy_price"]} ({$res["expected_data"]["stepThree_buy_amount"]})<br />Result: <span style=\"color: red;\">-" . $this->format($res["step_two"]["result"]) . " {$res["asset_two_name"]}</span>, <span style=\"color: green;\">+" . $this->format($res["step_three"]["result"]) . " {$res["main_asset_name"]}</span><br />{$res["step_three"]["exchange"]}</td>";

                $calculations .= "<td><span style=\"color: " . (($res["result"] > 0) ? "green" : "red;") . ";\">" . $this->format($res["result"]) . " {$res["main_asset_name"]}</span></td>";

                $calculations .= "</tr>";

                $table .= $calculations;

                $html .= $table;

                $i++;

            }

            $html .= '<tr><td colspan="6" style="background-color: grey; color: #fff; text-align: center;">' . $result['reason'] . '</td></tr>' . '<tr><td colspan="6" style="border-left: 0; border-right: 0;">&nbsp;</td></tr>';

        }

        $html .= '</table>';

        $sum_balances = [];

        foreach ($balances as $balance) {

            foreach ($balance as $asset => $b) {

                $sum_balances[$asset]['free'] = 0;
                $sum_balances[$asset]['used'] = 0;
                $sum_balances[$asset]['total'] = 0;

            }

        }

        foreach ($balances as $balance) {

            foreach ($balance as $asset => $b) {

                $sum_balances[$asset]['free'] += $b['free'];
                $sum_balances[$asset]['used'] += $b['used'];
                $sum_balances[$asset]['total'] += $b['total'];

            }

        }

        $html .= '<br /><br /> All Balances: <pre>' . json_encode($sum_balances, JSON_PRETTY_PRINT) . '</pre> <br /><br /> Balances: <pre>' . json_encode($balances, JSON_PRETTY_PRINT) . '</pre> <br /><br /> Orderbooks: <pre>' . json_encode($orderbooks, JSON_PRETTY_PRINT) . '</pre> <br /><br /> Best results: <pre>' . json_encode($best_result, JSON_PRETTY_PRINT) . '</pre> <br /><br /> Results: <pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';

        $index = fopen($file_path, 'w');

        fwrite($index, $html);

        fclose($index);

    }

    /**
     * Возвращает результат треугольника
     *
     * @param int $max_depth Максимальная глубина в стакан
     * @param array $rates Курсы
     * @param array $max_deal_amounts Максимальный размер сделки в main_asset
     * @param array $combinations Комбинация
     * @param array $orderbook Три шага ордербука
     * @param array $balances Балансы
     * @return array Отдает массив результатов и reason
     */
    public function getResults(
        int $max_depth,
        array $rates,
        array $max_deal_amounts,
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

        $max_deal_amount = $this->getMaxDealAmount(
            $orderbook,
            $combinations['main_asset_name'],
            $combinations['main_asset_amount_precision'],
            $max_deal_amounts,
            $balances
        );

        while (true) {

            if ($max_deal_amount != 0) {

                $this->getOrderbookInfo($orderbook_info, $orderbook, $deal_amount, $max_deal_amount);

                try {

                    $deal_amount = $this->DealAmount(
                        $orderbook,
                        $orderbook_info,
                        $combinations['main_asset_name'],
                        $max_deal_amount
                    );

                } catch(Throwable $e) {

                    echo '[' . date('Y-m-d H:i:s') . '] Division by zero Deal Amount. Error Message: ' . $e->getMessage() . PHP_EOL;

                    sleep(1);

                    break;

                }

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

            } elseif (!$result["status"] && $deal_amount == $max_deal_amount) {

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
            'reason' => $reason ?? '',
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

            $asks_step_one_condition = isset($orderbook["step_one"]["asks"][$orderbook_info['step_one']['dom_position']]["0"]) && isset($orderbook["step_one"]["asks"][$orderbook_info['step_one']['dom_position']]["1"]);
            $bids_step_one_condition = isset($orderbook["step_one"]["bids"][$orderbook_info['step_one']['dom_position']]["0"]) && isset($orderbook["step_one"]["bids"][$orderbook_info['step_one']['dom_position']]["1"]);

            if ($asks_step_one_condition) {

                $orderbook_info['step_one']['buy_price'] = $orderbook["step_one"]["asks"][$orderbook_info['step_one']['dom_position']]["0"];

                $orderbook_info['step_one']['buy_amount'] += $orderbook["step_one"]["asks"][$orderbook_info['step_one']['dom_position']]["1"];

            }

            if ($bids_step_one_condition) {

                $orderbook_info['step_one']['sell_price'] = $orderbook["step_one"]["bids"][$orderbook_info['step_one']['dom_position']]["0"];

                $orderbook_info['step_one']['sell_amount'] += $orderbook["step_one"]["bids"][$orderbook_info['step_one']['dom_position']]["1"];

            }

            if ($asks_step_one_condition || $bids_step_one_condition) {

                $orderbook_info['step_one']['dom_position']++;

            }

        }

        //Step 2
        if ($deal_amount["step_two"] < $max_deal_amount) {

            $asks_step_two_condition = isset($orderbook["step_two"]["asks"][$orderbook_info['step_two']['dom_position']]["0"]) && isset($orderbook["step_two"]["asks"][$orderbook_info['step_two']['dom_position']]["1"]);
            $bids_step_two_condition = isset($orderbook["step_two"]["bids"][$orderbook_info['step_two']['dom_position']]["0"]) && isset($orderbook["step_two"]["bids"][$orderbook_info['step_two']['dom_position']]["1"]);

            if ($asks_step_two_condition) {

                $orderbook_info['step_two']['buy_price'] = $orderbook["step_two"]["asks"][$orderbook_info['step_two']['dom_position']]["0"];

                $orderbook_info['step_two']['buy_amount'] += $orderbook["step_two"]["asks"][$orderbook_info['step_two']['dom_position']]["1"];

            }

            if ($bids_step_two_condition) {

                $orderbook_info['step_two']['sell_price'] = $orderbook["step_two"]["bids"][$orderbook_info['step_two']['dom_position']]["0"];

                $orderbook_info['step_two']['sell_amount'] += $orderbook["step_two"]["bids"][$orderbook_info['step_two']['dom_position']]["1"];

            }

            if ($asks_step_two_condition || $bids_step_two_condition) {

                $orderbook_info['step_two']['dom_position']++;

            }

        }

        //Step 3
        if ($deal_amount["step_three"] < $max_deal_amount) {

            $asks_step_three_condition = isset($orderbook["step_three"]["asks"][$orderbook_info['step_three']['dom_position']]["0"]) && isset($orderbook["step_three"]["asks"][$orderbook_info['step_three']['dom_position']]["1"]);
            $bids_step_three_condition = isset($orderbook["step_three"]["bids"][$orderbook_info['step_three']['dom_position']]["0"]) && isset($orderbook["step_three"]["bids"][$orderbook_info['step_three']['dom_position']]["1"]);

            if ($asks_step_three_condition) {

                $orderbook_info['step_three']['buy_price'] = $orderbook["step_three"]["asks"][$orderbook_info['step_three']['dom_position']]["0"];

                $orderbook_info['step_three']['buy_amount'] += $orderbook["step_three"]["asks"][$orderbook_info['step_three']['dom_position']]["1"];

            }

            if ($bids_step_three_condition) {

                $orderbook_info['step_three']['sell_price'] = $orderbook["step_three"]["bids"][$orderbook_info['step_three']['dom_position']]["0"];

                $orderbook_info['step_three']['sell_amount'] += $orderbook["step_three"]["bids"][$orderbook_info['step_three']['dom_position']]["1"];

            }

            if ($asks_step_three_condition || $bids_step_three_condition) {

                $orderbook_info['step_three']['dom_position']++;

            }

        }

    }

    /**
     * Отдает максимальный размер сделки в main_asset
     *
     * @param array $orderbook Три шага ордербука
     * @param string $mainAsset_id Main_asset
     * @param float $mainAsset_decimals Decimals в main_asset
     * @param float $balances Балансы
     * @return float Максимальный размер сделки
     */
    private function getMaxDealAmount(
        array $orderbook,
        string $mainAsset_id,
        float $mainAsset_decimals,
        array $max_deal_amounts,
        array $balances
    ): float
    {

        //Step 1
        if (isset($orderbook["step_one"]["asks"]["0"]["0"]) && isset($orderbook["step_one"]["asks"]["0"]["1"]))
            $orderbook_info['step_one']['buy_price'] = $orderbook["step_one"]["asks"]["0"]["0"];

        if (isset($orderbook["step_one"]["bids"]["0"]["0"]) && isset($orderbook["step_one"]["bids"]["0"]["1"]))
            $orderbook_info['step_one']['sell_price'] = $orderbook["step_one"]["bids"]["0"]["0"];

        //Step 2
        if (isset($orderbook["step_two"]["asks"]["0"]["0"]) && isset($orderbook["step_two"]["asks"]["0"]["1"]))
            $orderbook_info['step_two']['buy_price'] = $orderbook["step_two"]["asks"]["0"]["0"];

        if (isset($orderbook["step_two"]["bids"]["0"]["0"]) && isset($orderbook["step_two"]["bids"]["0"]["1"]))
            $orderbook_info['step_two']['sell_price'] = $orderbook["step_two"]["bids"]["0"]["0"];

        //Step 3
        if (isset($orderbook["step_three"]["asks"]["0"]["0"]) && isset($orderbook["step_three"]["asks"]["0"]["1"]))
            $orderbook_info['step_three']['buy_price'] = $orderbook["step_three"]["asks"]["0"]["0"];

        if (isset($orderbook["step_three"]["bids"]["0"]["0"]) && isset($orderbook["step_three"]["bids"]["0"]["1"]))
            $orderbook_info['step_three']['sell_price'] = $orderbook["step_three"]["bids"]["0"]["0"];

//        $step_one_max_deal_amount = empty($orderbook['step_one']['asks'])
//            ? $balances[$orderbook['step_one']['amountAsset']]['free'] * 0.95
//            : $balances[$orderbook['step_one']['priceAsset']]['free'] / $orderbook_info['step_one']['buy_price'] * 0.95;
//
//        $step_two_max_deal_amount = empty($orderbook['step_two']['asks'])
//            ? $balances[$orderbook['step_two']['amountAsset']]['free'] * 0.95
//            : $balances[$orderbook['step_two']['priceAsset']]['free'] / $orderbook_info['step_two']['buy_price'] * 0.95;
//
//        $step_three_max_deal_amount = empty($orderbook['step_three']['asks'])
//            ? $balances[$orderbook['step_three']['amountAsset']]['free'] * 0.95
//            : $balances[$orderbook['step_three']['priceAsset']]['free'] / $orderbook_info['step_three']['buy_price'] * 0.95;

        $step_one_max_deal_amount = $max_deal_amounts[$orderbook['step_one']['amountAsset']];
        $step_two_max_deal_amount = $max_deal_amounts[$orderbook['step_two']['amountAsset']];
        $step_three_max_deal_amount = $max_deal_amounts[$orderbook['step_three']['amountAsset']];

        $orderbook_info['step_one']['sell_amount'] = $step_one_max_deal_amount;
        $orderbook_info['step_one']['buy_amount'] = $step_one_max_deal_amount;

        $orderbook_info['step_two']['sell_amount'] = $step_two_max_deal_amount;
        $orderbook_info['step_two']['buy_amount'] = $step_two_max_deal_amount;

        $orderbook_info['step_three']['sell_amount'] = $step_three_max_deal_amount;
        $orderbook_info['step_three']['buy_amount'] = $step_three_max_deal_amount;

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

        return $this->incrementNumber(min($deal_amount_stepOne, $deal_amount_stepTwo, $deal_amount_stepThree), $mainAsset_decimals);

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

        $deal_amount_min = min($deal_amount_stepOne, $deal_amount_stepTwo, $deal_amount_stepThree);

        if ($deal_amount_min >= $max_deal_amount) $deal_amount_min = $max_deal_amount;

        return [
            "min" => $deal_amount_min,
            "step_one" => $deal_amount_stepOne,
            "step_two" => $deal_amount_stepTwo,
            "step_three" => $deal_amount_stepThree
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
//            if ($deal_amount > $balances[$combinations["main_asset_name"]]["free"]) {
//                $status = false;
//                $reason = "Not enough balance (step 1, sell). Asset: {$combinations["main_asset_name"]} ({$balances[$combinations["main_asset_name"]]["free"]} < $deal_amount)";
//            }

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
//            if ($deal_amount > $balances[$combinations["main_asset_name"]]["free"]) {
//                $status = false;
//                $reason = "Not enough balance (step 1, buy). Asset: {$combinations["main_asset_name"]} ({$balances[$combinations["main_asset_name"]]["free"]} < $deal_amount)";
//            }

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
                "reason" => "Amount limit error (step 1): {$combinations["step_one_symbol"]} min amount: $min_amount_step_one, current amount: {$stepOne["amount"]}, order type: {$stepOne["orderType"]}, exchange: {$stepOne["exchange"]}, deal amount: {$deal_amount}"
            ];
        }

        // Cost limit check (step 1)
        $cost_limit_step_one = $orderbook["step_one"]["limits"]["cost"]["min"] ?? 0;

        if ($cost_limit_step_one > $stepOne["amount"] * $stepOne["price"]) {
            return [
                "status" => false,
                "reason" => "Cost limit error (step 1): {$combinations["step_one_symbol"]} min cost: $cost_limit_step_one, current cost: " . ($stepOne["amount"] * $stepOne["price"]) . " current amount: {$stepOne["amount"]}, order type: {$stepOne["orderType"]}, exchange: {$stepOne["exchange"]}, deal amount: {$deal_amount}"
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
//            if ($stepOne["result"] > $balances[$orderbook['step_two']['amountAsset']]["free"]) {
//                $status = false;
//                $reason = "Not enough balance (step 2, sell). Asset: {$stepTwo["amountAssetName"]} ({$balances[$orderbook['step_two']['amountAsset']]["free"]} < {$stepOne["result"]})";
//            }

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
//            if ($stepOne["result"] > $balances[$orderbook['step_two']['priceAsset']]["free"]) {
//                $status = false;
//                $reason = "Not enough balance (step 2, buy). Asset: {$stepTwo["priceAssetName"]} ({$balances[$orderbook['step_two']['priceAsset']]["free"]} < {$stepOne["result"]})";
//            }

        }

        // Subtract fee (step 2)
        $stepTwo["result"] = $this->incrementNumber(
            $stepTwo["result"] - $stepTwo["result"] / 100 * $orderbook["step_two"]['fee'],
            $orderbook["step_two"]['amount_increment']
        );

        // Amount limit check (step 2)
        $min_amount_step_two = $orderbook["step_two"]["limits"]["amount"]["min"] ?? 0;

        if ($min_amount_step_two > $stepTwo["amount"]) {
            return [
                "status" => false,
                "reason" => "Amount limit error (step 2): {$combinations["step_two_symbol"]} min amount: $min_amount_step_two, current amount: {$stepTwo["amount"]}, order type: {$stepTwo["orderType"]}, exchange: {$stepTwo["exchange"]}, deal amount: {$deal_amount}"
            ];
        }

        // Cost limit check (step 2)
        $cost_limit_step_two = $orderbook["step_two"]["limits"]["cost"]["min"] ?? 0;

        if ($cost_limit_step_two > $stepTwo["amount"] * $stepTwo["price"]) {
            return [
                "status" => false,
                "reason" => "Cost limit error (step 2): {$combinations["step_two_symbol"]} min cost: $cost_limit_step_two, current cost: " . ($stepTwo["amount"] * $stepTwo["price"]) . " current amount: {$stepTwo["amount"]}, order type: {$stepTwo["orderType"]}, exchange: {$stepTwo["exchange"]}, deal amount: {$deal_amount}"
            ];
        }

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
//            if ($stepTwo["result"] > $balances[$orderbook['step_three']['amountAsset']]["free"]) {
//                $status = false;
//                $reason = "Not enough balance (step 3, sell). Asset: {$stepThree["amountAssetName"]} ({$balances[$orderbook['step_three']['amountAsset']]["free"]} < {$stepTwo["result"]})";
//            }

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
//            if ($step_three_result > $balances[$combinations["main_asset_name"]]["free"]) {
//                $status = false;
//                $reason = "Not enough balance (step 3, buy). Asset: {$combinations["main_asset_name"]} ({$balances[$combinations["main_asset_name"]]["free"]} < $step_three_result)";
//            }

        }

        // Subtract fee (step 3)
        $stepThree["result"] = $this->incrementNumber(
            $stepThree["result"] - $stepThree["result"] / 100 * $orderbook["step_three"]['fee'],
            $orderbook["step_three"]['amount_increment']
        );

        //Amount limit check (step 3)
        $min_amount_step_three = $orderbook["step_three"]["limits"]["amount"]["min"] ?? 0;

        if ($min_amount_step_three > $stepThree["amount"]) {
            return [
                "status" => false,
                "reason" => "Amount limit error (step 3): {$combinations["step_three_symbol"]} min amount: $min_amount_step_three, current amount: {$stepThree["amount"]}, current amount: {$stepThree["amount"]}, order type: {$stepThree["orderType"]}, exchange: {$stepThree["exchange"]}, deal amount: {$deal_amount}"
            ];
        }

        // Cost limit check (step 3)
        $cost_limit_step_three = $orderbook["step_three"]["limits"]["cost"]["min"] ?? 0;

        if ($cost_limit_step_three > $stepThree["amount"] * $stepThree["price"]) {
            return [
                "status" => false,
                "reason" => "Cost limit error (step 3): {$combinations["step_three_symbol"]} min cost: $cost_limit_step_three, current cost: " . ($stepThree["amount"] * $stepThree["price"]) . " current amount: {$stepThree["amount"]}, order type: {$stepThree["orderType"]}, exchange: {$stepThree["exchange"]}, deal amount: {$deal_amount}"
            ];
        }

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

    /**
     * @param float $float
     * @param int $decimals
     * @return string
     */
    public function format(float $float, int $decimals = 8): string
    {
        return number_format($float, $decimals, ".", "");
    }

}