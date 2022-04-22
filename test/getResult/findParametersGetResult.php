<?php

use Src\Main;

require dirname(__DIR__, 2) . '/index.php';

$bot = new Main();

// конфигурация
$min_profit = 0;

$max_deal_amount = 0.03;

$max_depth = 10;
// конфигурация

// обновляемые данные
$combinations = [
    'main_asset_name' => 'BTC',
    'main_asset_amount_precision' => 0.00000001,
    'asset_one_name' => 'ETH',
    'asset_two_name' => 'USDT',
    'step_one_symbol' => 'ETH/BTC',
    'step_two_symbol' => 'ETH/USDT',
    'step_three_symbol' => 'BTC/USDT',
];

$orderbook = [
    'step_one' => [
        'bids' => [
            [0.07431, 3.1505174],
            [0.0742405, 2.22],
            [0.07421204, 0.0008692],
            [0.07412322, 3.00182053],
            [0.07412321, 4.32],
        ],
        'asks' => [
            [0.07439746, 0.00086418],
            [0.07448674, 0.00086371],
            [0.07449799, 1.00026367],
            [0.074498, 2.99999847],
            [0.07451689, 0.98161981]
        ],
        'symbol' => 'ETH/BTC',
        'limits' => [
            'amount' => [
                'min' => 0.0005,
                'max' => 5000.0,
            ],
            'price' => [
                'min' => 1.0E-8,
                'max' => 10.0,
            ],
            'cost' => [
                'min' => 0.0002,
                'max' => 100.0,
            ]
        ],
        'price_increment' => 0.00000001,
        'amount_increment' => 0.00000001,
        'amountAsset' => 'ETH',
        'priceAsset' => 'BTC',
        'exchange' => 'binance',
        'fee' => 0.1
    ],
    'step_two' => [
        'bids' => [
            [2925.3012, 0.01883708],
            [2925.3011, 5.007],
            [2925.3, 0.2],
            [2925.2501, 0.056],
            [2924.8584, 0.05260747]
        ],
        'asks' => [
            [2925.9623, 0.10254971],
            [2925.9624, 0.0549],
            [2926.7999, 2.98116292],
            [2926.8, 0.2],
            [2926.957, 0.53547509]
        ],
        'symbol' => 'ETH/USDT',
        'limits' => [
            'amount' => [
                'min' => 0.0005,
                'max' => 5000.0,
            ],
            'price' => [
                'min' => 0.01,
                'max' => 100000.0,
            ],
            'cost' => [
                'min' => 1.0,
                'max' => 500000.0
            ],
        ],
        'price_increment' => 0.0001,
        'amount_increment' => 0.00000001,
        'amountAsset' => 'ETH',
        'priceAsset' => 'USDT',
        'exchange' => 'exmo',
        'fee' => 0.1
    ],
    'step_three' => [
        'bids' => [
            [39319.32, 0.029533],
            [39318.0, 0.00408],
            [39312.52, 0.00762934],
            [39312.51, 0.2],
            [39312.5, 0.01924316]
        ],
        'asks' => [
            [39324.35, 0.00405],
            [39327.03, 0.01270578],
            [39327.15, 0.00145942],
            [39331.0, 0.04],
            [39331.12, 0.01270686]
        ],
        'symbol' => 'BTC/USDT',
        'limits' => [
            'amount' => [
                'min' => 2.0E-5,
                'max' => 1000.0,
            ],
            'price' => [
                'min' => 0.01,
                'max' => 150000.0,
            ],
            'cost' => [
                'min' => 1.0,
                'max' => 500000.0,
            ],
        ],
        'price_increment' => 0.01,
        'amount_increment' => 0.00000001,
        'amountAsset' => 'BTC',
        'priceAsset' => 'USDT',
        'exchange' => 'kuna',
        'fee' => 0.1
    ],
];

$balances = [
    'BTC' => [
        'free' => '0.51828021',
        'used' => '0.00000000',
        'total' => '0.51828021',
        'exchange' => 'binance'
    ],
    'ETH' => [
        'free' => '0.00023513',
        'used' => '0.00000000',
        'total' => '0.00023513',
        'exchange' => 'exmo'
    ],
    'USDT' => [
        'free' => '20.76792778',
        'used' => '0.00000000',
        'total' => '20.76792778',
        'exchange' => 'kuna'
    ],
];
// обновляемые данные

// начало подсчета getResult()
$results = [];
$reason = '';
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

    sleep(1);

    $bot->getOrderbookInfo($orderbook_info, $orderbook, $deal_amount, $max_deal_amount);

    $deal_amount = $bot->DealAmount(
        $orderbook,
        $orderbook_info,
        $combinations['main_asset_name'],
        $combinations['main_asset_amount_precision'],
        $max_deal_amount
    );

    $result = $bot->getResult(
        $orderbook,
        $orderbook_info,
        $balances,
        $combinations,
        $deal_amount['min'] * 10, // тут 10 необходимо убирать (здесь он для тестов)
        $max_deal_amount
    );

    if ($result["status"] && $result["result_in_main_asset"] > $min_profit) {

        $results[] = $result;

    } elseif (!$result["status"]) {

        $reason = $result["reason"];

        break;

    }

    if (
        $reason = $bot->findReason(
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
// начало подсчета getResult()

print_r($result);
