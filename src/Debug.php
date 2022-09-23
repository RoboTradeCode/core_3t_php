<?php

namespace Src;

class Debug
{
    public static function printAll(array $array, array $balances, array $orders, string $exchange): void
    {
        self::printBalances($balances);
        self::printOrders($orders, $exchange);
        self::simplePrint($array, 'ALGO INFO');
    }

    public static function simplePrint(array $array, string $head_message): void
    {
        echo PHP_EOL . $head_message . ' [START]----------------------------------------------------------------------------------' . PHP_EOL;

        foreach ($array as $key => $arr) echo '[' . date('Y-m-d H:i:s') . '] ' . $key . ' ' . $arr . PHP_EOL;

        echo $head_message . ' [END]------------------------------------------------------------------------------------' . PHP_EOL . PHP_EOL;
    }

    public static function printOrders(array $orders, string $exchange): void
    {
        echo PHP_EOL . 'Orders: ' . $exchange . ' [START]----------------------------------------------------------------------------------' . PHP_EOL;

        foreach ($orders as $order) {
            $message = '[' . date('Y-m-d H:i:s') . '] Client Order Id ' . $order['client_order_id'] . ' Price ' . $order['price'] . ' Side ' . $order['side'] . ' Amount: ' . ($order['amount'] ?? 'null');

            if (isset($order['status'])) $message .= ' Status ' . $order['status'];

            echo $message . PHP_EOL;
        }

        echo 'Orders: ' . $exchange . ' [END]------------------------------------------------------------------------------------' . PHP_EOL . PHP_EOL;
    }

    public static function printBalances(array $balances): void
    {
        echo PHP_EOL . 'Balances [START]---------------------------------------------------------------------------------' . PHP_EOL;

        foreach ($balances as $asset => $balance)
            echo '[' . date('Y-m-d H:i:s') . '] ' . $asset . ' (free: ' . $balance['free'] . ' | used: ' . $balance['used'] . ' | total: ' . $balance['total'] . ') ' . PHP_EOL;

        echo 'Balances [END]----------------------------------------------------------------------------------' . PHP_EOL . PHP_EOL;
    }
}