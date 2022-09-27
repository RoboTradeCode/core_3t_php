<?php

namespace Src\SpreadBot;

use Src\TimeV2;

class SpreadBot
{
    private string $exchange;
    private string $market_discovery_exchange;

    public function __construct(string $exchange, string $market_discovery_exchange)
    {
        $this->exchange = $exchange;
        $this->market_discovery_exchange = $market_discovery_exchange;
    }

    public function getBestOrderbook(array $orderbooks, string $symbol, bool $is_exchange = true): array
    {
        $exchange = $is_exchange ? $this->exchange : $this->market_discovery_exchange;

        return [
            'bid' => $orderbooks[$symbol][$exchange]['bids'][0][0],
            'ask' => $orderbooks[$symbol][$exchange]['asks'][0][0],
        ];
    }

    public function getProfit(array $market_discovery, array $min_profit): array
    {
        return [
            'bid' => $market_discovery['bid'] - ($market_discovery['bid'] * $min_profit['bid'] / 100),
            'ask' => $market_discovery['ask'] + ($market_discovery['ask'] * $min_profit['ask'] / 100),
        ];
    }

    public function filterOrdersBySideAndSymbol(array $real_orders, string $symbol): array
    {
        return [
            'buy' => isset($real_orders[$this->exchange]) ? array_filter($real_orders[$this->exchange], fn($real_order_for_symbol) => ($real_order_for_symbol['symbol'] == $symbol) && ($real_order_for_symbol['side'] == 'buy')) : [],
            'sell' => isset($real_orders[$this->exchange]) ? array_filter($real_orders[$this->exchange], fn($real_order_for_symbol) => ($real_order_for_symbol['symbol'] == $symbol) && ($real_order_for_symbol['side'] == 'sell')) : []
        ];
    }

    public function isCreateBuyOrder(
        array $exchange_orderbook,
        array $profit,
        array $balances,
        string $quote_asset,
        array $min_deal_amounts,
        array $real_orders_for_symbol,
        array $must_orders
    ): bool
    {
        return $exchange_orderbook['bid'] <= $profit['bid'] && $balances[$this->exchange][$quote_asset]['free'] >= $min_deal_amounts[$quote_asset] &&
            count($real_orders_for_symbol['buy']) <= $must_orders['buy'] && TimeV2::up(1, 'create_order', true);
    }

    public function isCreateSellOrder(
        array $exchange_orderbook,
        array $profit,
        array $balances,
        string $base_asset,
        array $min_deal_amounts,
        array $real_orders_for_symbol,
        array $must_orders
    ): bool
    {
        return $exchange_orderbook['ask'] >= $profit['ask'] && $balances[$this->exchange][$base_asset]['free'] >= $min_deal_amounts[$base_asset] &&
            count($real_orders_for_symbol['sell']) <= $must_orders['sell'] && TimeV2::up(1, 'create_order', true);
    }

    public function incrementNumber(float $number, float $increment): float
    {
        return $increment * floor($number / $increment);
    }

    public function getMarket(array $markets, string $symbol)
    {
        return $markets[$this->exchange][array_search($symbol, array_column($markets, 'common_symbol'))] ?? [];
    }
}