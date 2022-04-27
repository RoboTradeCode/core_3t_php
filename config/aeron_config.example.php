<?php

// Конфиг, который должен получать от конфигуратора
const CONFIG = [
    'exchange' => 'kuna',
    'exchanges' => ['kuna', 'huobi'],
    'min_profit' => [
        'BTC' => 0,
        'ETH' => 0,
        'USDT' => 0
    ],
    'min_deal_amounts' => [
        'BTC' => 0.001,
        'ETH' => 0.01,
        'USDT' => 20
    ],
    'rates' => [
        'BTC' => 46139,
        'ETH' => 3471,
        'USDT' => 1
    ],
    'max_deal_amounts' => [
        'BTC' => 0.01,
        'ETH' => 0.1,
        'USDT' => 200
    ],
    'markets' => [
        [
            'exchange_symbol' => 'ETH-BTC',
            'common_symbol' => 'ETH/BTC',
            'price_increment' => 0.000001,
            'amount_increment' => 0.0000001,
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
            'assets' => [
                'base' => 'ETH',
                'quote' => 'BTC',
            ]
        ],
        [
            'exchange_symbol' => 'BTC-USDT',
            'common_symbol' => 'BTC/USDT',
            'price_increment' => 0.1,
            'amount_increment' => 0.00000001,
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
            'assets' => [
                'base' => 'BTC',
                'quote' => 'USDT',
            ]
        ],
        [
            'exchange_symbol' => 'ETH-USDT',
            'common_symbol' => 'ETH/USDT',
            'price_increment' => 0.01,
            'amount_increment' => 0.0000001,
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
            'assets' => [
                'base' => 'ETH',
                'quote' => 'USDT',
            ]
        ]
    ],
    'routes' => [
        [
            ['source_asset' => 'BTC', 'common_symbol' => 'ETH/BTC', 'operation' => 'buy'],
            ['source_asset' => 'ETH', 'common_symbol' => 'ETH/USDT', 'operation' => 'sell'],
            ['source_asset' => 'USDT', 'common_symbol' => 'BTC/USDT', 'operation' => 'buy'],
        ],
    ],
    'max_depth' => 10,
    'fees' => [
        'kuna' => 0.1,
        'huobi' => 0.1
    ],
];

// Название алгоритма
const ALGORITHM = 'cross_3t_php';

// Биржа, которая тестируется гейтом
const EXCHANGE = 'ftx';

// Нода
const NODE = 'core';

// Instance
const INSTANCE = '1';

// publisher, который подключается к subscriber в агенте, для посылания команды на получения конфига
const AGENT_PUBLISHER = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1001
];

// subscriber, который подключается к publisher в агенте, для принятия конфига
const AGENT_SUBSCRIBERS_BALANCES = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1001
];

// publisher, который подключается к subscriber в гейте, для посылания команд
const GATE_PUBLISHER = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1001
];

// subscriber, который подключается к publisher в гейте, для принятия данных
const GATE_SUBSCRIBERS_ORDERBOOKS = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1001
];

const GATE_SUBSCRIBERS_BALANCES = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1002
];

const GATE_SUBSCRIBERS_ORDERS = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1003
];
