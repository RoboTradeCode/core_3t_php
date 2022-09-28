<?php

use Src\OrderBookCorrect;

require dirname(__DIR__, 2) . '/index.php';

$symbol = 'BTC/USDT';
$amount = 1;
$price = 20485.6;
$exchange = 'binance';

$orderbooks = [
    'ETH/BTC' => [
        'binance' => [
            'bids' => [
                [0.057799, 11.0577],
                [0.057797, 3.32],
                [0.057796, 4.1],
                [0.057795, 2.15],
                [0.057794, 4.3556],
            ],
            'asks' => [
                [0.0578, 9.0768],
                [0.057802, 1.1238],
                [0.057804, 1.7469],
                [0.057806, 5.7136],
                [0.057807, 1.3966],
            ],
            'symbol' => 'ETH/BTC',
            'timestamp' => 1657186328235,
            'core_timestamp' => 1657186328.3505,
        ]
    ],
    'BTC/USDT' => [
        'binance' => [
            'bids' => [
                [20485.39, 11.46419],
                [20485.35, 0.01954],
                [20485.33, 1.25725],
                [20485.29, 0.00127],
                [20485.2, 2.49111],
            ],
            'asks' => [
                [20485.4, 0.07126],
                [20485.6, 1.00054],
                [20485.89, 0.07528],
                [20486, 0.01964],
                [20486.05, 0.01335],
            ],
            'symbol' => 'BTC/USDT',
            'timestamp' => 1657186328235,
            'core_timestamp' => 1657186328.352,
        ]
    ],
    'ETH/USDT' => [
        'binance' => [
            'bids' => [
                [1184.35, 1.622],
                [1184.34, 0.4331],
                [1184.25, 0.01],
                [1184.18, 2],
                [1184.14, 4.95],
            ],
            'asks' => [
                [1184.36, 50.9203],
                [1184.39, 4],
                [1184.4, 2],
                [1184.41, 0.4],
                [1184.45, 0.8],
            ],
            'symbol' => 'ETH/USDT',
            'timestamp' => 1657186328235,
            'core_timestamp' => 1657186328.3586,
        ]
    ]
];

OrderBookCorrect::beforeRealCancelOrder($orderbooks[$symbol][$exchange], $amount, $price);

print_r($orderbooks[$symbol][$exchange]);
echo PHP_EOL;