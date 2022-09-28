<?php

namespace Src\SpreadDelta;

class SpreadDelta
{

    public function countOrders(array $exchange_balances, array $common_symbols, array $max_deal_amounts, array $amount_limitation)
    {
        foreach ($max_deal_amounts as $asset => $deal_amount)
            $sell_assets[$asset] = intval(min($exchange_balances[$asset]['total'], $amount_limitation[$asset]) / $deal_amount);

        foreach ($common_symbols as $common_symbol) {
            $count_orders[$common_symbol]['sell'] = 0;

            $count_orders[$common_symbol]['buy'] = 0;
        }

        foreach ($sell_assets as $asset => $count) {
            while (true)
                foreach ($common_symbols as $common_symbol) {
                    list($base_asset, $quote_asset) = explode('/', $common_symbol);

                    if ($base_asset == $asset) {
                        if (isset($count_orders[$common_symbol]['sell'])) {
                            $count_orders[$common_symbol]['sell']++;
                        } else
                            $count_orders[$common_symbol]['sell'] = 1;

                        $count--;
                    } elseif ($quote_asset == $asset) {
                        $count_orders[$common_symbol]['buy']++;

                        $count--;
                    }

                    if ($count == 0) break 2;
                }
        }

        return $count_orders ?? [];
    }

}