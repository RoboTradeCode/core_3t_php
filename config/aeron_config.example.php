<?php

// Создавать ли html файл при каждом просчете
const DEBUG_HTML_VISION = false;

// Конфиг, который должен получать от конфигуратора
const CONFIG = [
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
        'BTC' => 0.01,
        'ETH' => 0.1,
        'USDT' => 200
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

// Задержка алгоритма
const SLEEP = 10;

// Название алгоритма
const ALGORITHM = 'cross_3t_php';

// Биржа, которая тестируется гейтом
const EXCHANGE = 'ftx';

// Нода
const NODE = 'core';

// Instance
const INSTANCE = '1';

// publisher, который подключается к subscriber в гейте, для посылания команд
const GATE_PUBLISHER = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1005
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
