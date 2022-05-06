<?php

require dirname(__DIR__,2) . '/index.php';

const DEBUG_HTML_VISION = true;

use Src\Cross3T;

$config = [
    'exchange' => 'ftx',
    'exchanges' => ['ftx'],
    'min_profit' => [
        'BTC' => 0,
        'ETH' => 0,
        'USDT' => 0
    ],
    'min_deal_amounts' => [
        'BTC' => 0,
        'ETH' => 0,
        'USDT' => 0
    ],
    'rates' => [
        'BTC' => 39000,
        'ETH' => 2900,
        'USDT' => 1
    ],
    'max_deal_amounts' => [
        'BTC' => 0.001,
        'ETH' => 0.01,
        'USDT' => 40
    ],
    'markets' => [
        [
            'exchange_symbol' => 'ETH/BTC',
            'common_symbol' => 'ETH/BTC',
            'price_increment' => 0.0000025,
            'amount_increment' => 0.001,
            'limits' => [
                'amount' => [
                    'min' => 0.001,
                    'max' => null,
                ],
                'price' => [
                    'min' => null,
                    'max' => null,
                ],
                'cost' => [
                    'min' => null,
                    'max' => null,
                ]
            ],
            'base_asset' => 'ETH',
            'quote_asset' => 'BTC',
        ],
        [
            'exchange_symbol' => 'BTC/USDT',
            'common_symbol' => 'BTC/USDT',
            'price_increment' => 1,
            'amount_increment' => 0.0001,
            'limits' => [
                'amount' => [
                    'min' => 0.0001,
                    'max' => null,
                ],
                'price' => [
                    'min' => null,
                    'max' => null,
                ],
                'cost' => [
                    'min' => null,
                    'max' => null,
                ],
            ],
            'base_asset' => 'BTC',
            'quote_asset' => 'USDT',
        ],
        [
            'exchange_symbol' => 'ETH/USDT',
            'common_symbol' => 'ETH/USDT',
            'price_increment' => 0.1,
            'amount_increment' => 0.001,
            'limits' => [
                'amount' => [
                    'min' => 0.001,
                    'max' => null,
                ],
                'price' => [
                    'min' => null,
                    'max' => null,
                ],
                'cost' => [
                    'min' => null,
                    'max' => null
                ],
            ],
            'base_asset' => 'ETH',
            'quote_asset' => 'USDT',
        ]
    ],
    'routes' => [
        [
            ['source_asset' => 'USDT', 'common_symbol' => 'BTC/USDT', 'operation' => 'buy'],
            ['source_asset' => 'BTC', 'common_symbol' => 'ETH/BTC', 'operation' => 'buy'],
            ['source_asset' => 'ETH', 'common_symbol' => 'ETH/USDT', 'operation' => 'sell'],
        ],
        [
            ['source_asset' => 'BTC', 'common_symbol' => 'BTC/USDT', 'operation' => 'sell'],
            ['source_asset' => 'USDT', 'common_symbol' => 'ETH/USDT', 'operation' => 'buy'],
            ['source_asset' => 'ETH', 'common_symbol' => 'ETH/BTC', 'operation' => 'sell'],
        ],
        [
            ['source_asset' => 'ETH', 'common_symbol' => 'ETH/BTC', 'operation' => 'sell'],
            ['source_asset' => 'BTC', 'common_symbol' => 'BTC/USDT', 'operation' => 'sell'],
            ['source_asset' => 'USDT', 'common_symbol' => 'ETH/USDT', 'operation' => 'buy'],
        ],
        [
            ['source_asset' => 'BTC', 'common_symbol' => 'ETH/BTC', 'operation' => 'buy'],
            ['source_asset' => 'ETH', 'common_symbol' => 'ETH/USDT', 'operation' => 'sell'],
            ['source_asset' => 'USDT', 'common_symbol' => 'BTC/USDT', 'operation' => 'buy'],
        ],
        [
            ['source_asset' => 'ETH', 'common_symbol' => 'ETH/USDT', 'operation' => 'sell'],
            ['source_asset' => 'USDT', 'common_symbol' => 'BTC/USDT', 'operation' => 'buy'],
            ['source_asset' => 'BTC', 'common_symbol' => 'ETH/BTC', 'operation' => 'buy'],
        ],
        [
            ['source_asset' => 'USDT', 'common_symbol' => 'ETH/USDT', 'operation' => 'buy'],
            ['source_asset' => 'ETH', 'common_symbol' => 'ETH/BTC', 'operation' => 'sell'],
            ['source_asset' => 'BTC', 'common_symbol' => 'BTC/USDT', 'operation' => 'sell'],
        ],
    ],
    'max_depth' => 10,
    'fees' => [
        'ftx' => 0.1
    ],
];

// балансы, ордербуки и неизвестные данные
$balances = [
    'ftx' => [
        'BTC' => [
            'free' => 0.00169984,
            'used' => 0,
            'total' => 0.00169984,
        ],
        'USDT' => [
            'free' => 58.26909585,
            'used' => 14.283199999999994,
            'total' => 72.55229585,
        ],
        'ETH' => [
            'free' => 0.0269974,
            'used' => 0,
            'total' => 0.0269974,
        ],
    ]
];

/*
$orderbooks = [
    'ETH/BTC' => [
        'ftx' => [
            'bids' => [
                [0.0737625, 0.464],
                [0.07376, 2.592],
                [0.0737575, 0.729]
            ],
            'asks' => [
                [0.07429, 0.653],
                [0.0742925, 0.008],
                [0.074295, 0.013],
            ],
            'symbol' => 'ETH/BTC',
            'timestamp' => 1645184308000,
        ]
    ],
    'BTC/USDT' => [
        'ftx' => [
            'bids' => [
                [39335, 0.0026],
                [39334, 0.0079],
                [39333, 0.1008]
            ],
            'asks' => [
                [39634, 6.9],
                [39637, 0.0074],
                [39639, 0.0001]
            ],
            'symbol' => 'BTC/USDT',
            'timestamp' => 1645184308000,
        ]
    ],
    'ETH/USDT' => [
        'ftx' => [
            'bids' => [
                [2908.3, 0.028],
                [2908.2, 0.002],
                [2908.1, 0.063]
            ],
            'asks' => [
                [2941.2, 0.001],
                [2941.3, 578.715],
                [2941.7, 0.03],
            ],
            'symbol' => 'ETH/USDT',
            'timestamp' => 1645184308000,
        ]
    ]
];*/

$orderbooks = [
    'ETH/BTC' => [
        'ftx' => [
            'bids' => [
                [0.07389, 0.492],
                [0.0738875, 0.112],
                [0.073885, 0.545]
            ],
            'asks' => [
                [0.0744075, 0.044],
                [0.07441, 0.885],
                [0.0744125, 2.324],
            ],
            'symbol' => 'ETH/BTC',
            'timestamp' => 1645184308000,
        ]
    ],
    'BTC/USDT' => [
        'ftx' => [
            'bids' => [
                [39348, 5.3957],
                [39347, 0.0001],
                [39346, 0.0006]
            ],
            'asks' => [
                [39632, 0.0071],
                [39633, 0.0004],
                [39637, 0.0074]
            ],
            'symbol' => 'BTC/USDT',
            'timestamp' => 1645184308000,
        ]
    ],
    'ETH/USDT' => [
        'ftx' => [
            'bids' => [
                [2911.3, 0.003],
                [2911.2, 0.004],
                [2911, 4.447]
            ],
            'asks' => [
                [2946.5, 0.013],
                [2946.6, 0.047],
                [2946.9, 0.071],
            ],
            'symbol' => 'ETH/USDT',
            'timestamp' => 1645184308000,
        ]
    ]
];

$orderbooks = json_decode(file_get_contents(dirname(__DIR__, 2) . '/cache/orderbooks.json'), true);
$balances = json_decode(file_get_contents(dirname(__DIR__, 2) . '/cache/balances.json'), true);

//print_r($balances); die();

$cross_3t = new Cross3T($config);

// если есть все необходимые данные
if (!empty($balances) && !empty($orderbooks) && !empty($config)) {

    // фильтрация баланса в диапазоне минимальном и максимальном
    //$cross_3t->filterBalanceByMinAndMAxDealAmount($balances);

    // запускаем алгоритм и получаем лучший результат
    if ($best_result = $cross_3t->run($balances, $orderbooks)) {

        // для каждого шага, если результат выпал на текущую биржу, отправить сообщение на создание ордера
        foreach (['step_one', 'step_two', 'step_three'] as $step) {

            if ($best_result[$step]['exchange'] == EXCHANGE) {

                echo '[' . date('Y-m-d H:i:s') . '] Send to gate create order. Pair: ' . $best_result[$step]['amountAsset'] . '/' . $best_result[$step]['priceAsset'] . 'Amount: ' . $best_result[$step]['amount'] . 'Price: ' . $best_result[$step]['price'] . PHP_EOL;

            }

        }

    }

}