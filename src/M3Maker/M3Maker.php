<?php

namespace Src\M3Maker;

class M3Maker
{

    private array $config;
    private int $interation;

    public function __construct(string $file_path)
    {

        $this->config = $this->receiveConfig($file_path);

        $this->interation = 0;

    }

    public function getConfig(): array
    {

        return $this->config;

    }

    public function getMarket(string $exchange, string $symbol)
    {

        return $this->config['markets'][$exchange][array_search($symbol, array_column($this->config['markets'][$exchange], 'common_symbol'))] ?? [];

    }

    public function getGrids(array $orderbook, float $price_increment): array
    {

        // находим middle price для данного рынка (нулевой уровень)
        $zero = $this->incrementNumber(
            ($orderbook['asks'][0][0] + $orderbook['bids'][0][0]) / 2,
            $price_increment
        );

        // создаем сетку начиная с нулевого уровня
        $grids[] = $zero;

        // проходимся по 1000 циклов
        for ($i = 0; $i < 1000; $i++) {

            // если не был достигнут нулевой уровень
            if ($zero * (100 - ($this->config['interval'] * ($i + 1))) / 100 >= 0) {

                // добавить в сетку элемент на один уровень выше
                $grids[] = $this->incrementNumber(
                    $zero * (100 + ($this->config['interval'] * ($i + 1))) / 100,
                    $price_increment
                );

                // добавить в сетку элемент на один уровень ниже
                $grids[] = $this->incrementNumber(
                    $zero * (100 - ($this->config['interval'] * ($i + 1))) / 100,
                    $price_increment
                );

            } else {

                // если был достигнут нулевой уровень цены, то остановить рассчет
                break;

            }

        }

        // отсортировать массив по возрастанию
        sort($grids);

        return $grids;

    }

    public function getMustOrders($orders, $real_orders): array
    {

        // теоретические ордера, которые должны быть поставлены
        $must_orders = $orders;

        // ордера, которые уже должны быть поставлены в реальности
        $must_real_orders = $real_orders;

        // пройтись по всем теоретическим ордерам, которые должны быть поставлены
        foreach ($must_orders as $must_key => $must_order) {

            // пройтись по всем реальным ордерам, которые должны уже поставлены
            foreach ($must_real_orders as $must_real_key => $real_order) {

                // если цена уже поставленного ордера совпадает с теоретическим и статус этого ордера open
                if (abs($real_order['price'] - $must_order['price']) < PHP_FLOAT_EPSILON && $real_order['status'] == 'open') {

                    // удалить из теоретического массива ордеров данный ордер
                    unset($must_orders[$must_key]);

                    // удалить из реального массива ордеров данный ордер
                    unset($must_real_orders[$must_real_key]);

                    // остановиться и перейти к другому теоретическому ордеру
                    break;

                }

            }

        }

        return [$must_orders, $must_real_orders];

    }

    public function getOrders(int $sell_orders, int $buy_orders, string $symbol, array $lower, array $higher, float $price, float $amount_increment): array
    {

        // берем base_asset и quote_asset для данного рынка
        list($base_asset, $quote_asset) = explode('/', $symbol);

        // составить массив ордеров на продажу в нужном количестве по сетке
        for ($i = 0; $i < $sell_orders; $i++) {

            $orders[] = [
                'symbol' => $symbol,
                'type' => 'limit',
                'side' => 'sell',
                'amount' => $this->config['deal_amounts'][$base_asset],
                'price' => array_shift($higher)
            ];

        }

        // составить массив ордеров на покупку в нужном количестве по сетке
        for ($i = 0; $i < $buy_orders; $i++) {

            $orders[] = [
                'symbol' => $symbol,
                'type' => 'limit',
                'side' => 'buy',
                'amount' => $this->incrementNumber($this->config['deal_amounts'][$quote_asset] / $price, $amount_increment),
                'price' => array_pop($lower)
            ];

        }

        return $orders ?? [];

    }

    public function getLowerAndHigherGrids(array $grids, float $point_below, float $point_higher): array
    {

        // находим все, что в сетке ниже $point_below и выше $point_higher
        return [
            array_filter($grids, function ($grid) use ($point_below) {
                return $grid < $point_below;
            }),
            array_filter($grids, function ($grid) use ($point_higher) {
                return $grid > $point_higher;
            })
        ];

    }

    public function getTheNumberOfSellAndBuyOrders(array $balances, string $exchange, string $base_asset, string $quote_asset): array
    {

        // берем баланс для определенной биржи
        $balance_exchange = $balances[$exchange];

        // если в сумме по deal_amount для base asset можем поставить нужное количество ордеров, то
        if ($balance_exchange[$base_asset]['total'] / $this->config['deal_amounts'][$base_asset] >= $this->config['order_pairs']) {

            // смотрим сколько хватит поставить ордеров на покупку
            $buy_orders = ($balance_exchange[$quote_asset]['total'] / $this->config['deal_amounts'][$quote_asset] >= $this->config['order_pairs'])
                ? $this->config['order_pairs']
                : intval($balance_exchange[$quote_asset]['total'] / $this->config['deal_amounts'][$quote_asset]);

            // количество ордеров на продажу
            $sell_orders = $this->config['order_pairs'] + $this->config['order_pairs'] - $buy_orders;

        } else {

            // количество ордеров на продажу ставим столько сколько максимально возможно
            $sell_orders = intval($balance_exchange[$base_asset]['total'] / $this->config['deal_amounts'][$base_asset]);

            // количество ордеров на покупку
            $buy_orders = $this->config['order_pairs'] + $this->config['order_pairs'] - $sell_orders;

        }

        // если по балансу все проходит, то функция возвращает нужные количества
        if (
            $balance_exchange[$base_asset]['total'] / $this->config['deal_amounts'][$base_asset] >= $sell_orders &&
            $balance_exchange[$quote_asset]['total'] / $this->config['deal_amounts'][$quote_asset] >= $buy_orders
        ) {

            $this->interation++;

            return [$sell_orders, $buy_orders];

        }

        // в случае нехватк балансов, возвращаются нули
        return [0, 0];

    }

    public function getInteration(): int
    {

        return $this->interation;

    }

    public function incrementNumber(float $number, float $increment): float
    {

        return $increment * floor($number / $increment);

    }

    public function printOrders(array $orders, string $head_message): void
    {

        if ($this->debugMode()) {

            echo PHP_EOL . $head_message . ' [START]----------------------------------------------------------------------------------' . PHP_EOL;

            foreach ($orders as $order) {

                $message = '[' . date('Y-m-d H:i:s') . '] Price ' . $order['price'] . ' Side ' . $order['side'];

                if (isset($order['status']))
                    $message .= ' Status ' . $order['status'];

                echo $message . PHP_EOL;

            }

            echo $head_message . ' [END]------------------------------------------------------------------------------------' . PHP_EOL . PHP_EOL;

        }

    }

    public function printArray(array $array, string $head_message): void
    {

        if ($this->debugMode()) {

            echo PHP_EOL . $head_message . ' [START]----------------------------------------------------------------------------------' . PHP_EOL;

            foreach ($array as $key => $arr)
                echo '[' . date('Y-m-d H:i:s') . '] ' . $key . ' ' . $arr . PHP_EOL;

            echo $head_message . ' [END]------------------------------------------------------------------------------------' . PHP_EOL . PHP_EOL;

        }

    }

    public function sleepDebug(): void
    {

        if ($this->debugMode())
            sleep(1);

    }

    private function debugMode(): bool
    {

        if (isset($this->config['debug']))
            return $this->config['debug'];

        return false;

    }

    private function receiveConfig(string $file_path)
    {

        // Получает конфиг из файла spread_bot.json
        $config = json_decode(file_get_contents($file_path), true);

        foreach ($config['exchanges'] as $exchange) {

            $config_from_configurator = json_decode(
                $this->file_get_contents_ssl('https://configurator.robotrade.io/' . $exchange . '/' . $config['instances'][$exchange] . '?only_new=false'),
                true
            )['data'];

            $markets[$exchange] = $config_from_configurator['markets'];

            $assets_labels[$exchange] = $config_from_configurator['assets_labels'];

        }

        if (empty($markets) || empty($assets_labels)) {

            echo '[' . date('Y-m-d H:i:s') . '] Die $routes or $assets_labels or $markets empty' . PHP_EOL;

            die();

        }

        $config['markets'] = $markets;

        $config['assets_labels'] = $assets_labels;

        return $config;

    }

    private function file_get_contents_ssl(string $url): bool|string
    {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        curl_setopt($ch, CURLOPT_HEADER, false);

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_REFERER, $url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3000); // 3 sec.

        curl_setopt($ch, CURLOPT_TIMEOUT, 10000); // 10 sec.

        $result = curl_exec($ch);

        curl_close($ch);

        return $result;

    }

}