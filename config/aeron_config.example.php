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
        'BTC' => 31000,
        'ETH' => 2300,
        'USDT' => 1
    ],
    'max_deal_amounts' => [
        'BTC' => 0.01,
        'ETH' => 0.1,
        'USDT' => 200
    ],
    'max_depth' => 10,
    'fees' => [
        'ftx' => 0.1
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
    'assets_labels' => [
        [
            'exchange' => 'ETH',
            'common' => 'ETH'
        ],
        [
            'exchange' => 'BTC',
            'common' => 'BTC'
        ],
        [
            'exchange' => 'USDT',
            'common' => 'USDT'
        ],
    ],
    'aeron' => [
        'publishers' => [
            'gate' => [
                'channel' => 'aeron:ipc',
                'stream_id' => 1005
            ],
            'log' => [
                'channel' => 'aeron:udp?endpoint=3.66.183.27:44444',
                'stream_id' => 1008
            ],
        ],
        'subscribers' => [
            'balance' => [
                'channel' => 'aeron:ipc',
                'destinations' => [],
                'stream_id' => 1002
            ],
            'orderbooks' => [
                'channel' => 'aeron:ipc',
                'destinations' => [],
                'stream_id' => 1001
            ],
            'orders' => [
                'channel' => 'aeron:ipc',
                'stream_id' => 1003
            ]
        ]
    ]
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

// файл для визуального отображения рассчетов
const MADE_HTML_VISION_FILE = '/var/www/html/test.html';
