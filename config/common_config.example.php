<?php

// Режим отладки
const DEBUG = false;

// Биржа
const EXCHANGE = 'ftx';

// Нода
const NODE = 'core';

// Instance
const INSTANCE = '1';

// Каналы aeron
const AERON = [
    'publishers' => [
        'gate' => [
            'channel' => 'aeron:ipc',
            'stream_id' => 1004
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
            'stream_id' => 1005
        ],
        'orderbooks' => [
            'channel' => 'aeron:ipc',
            'destinations' => [],
            'stream_id' => 1006
        ],
        'orders' => [
            'channel' => 'aeron:ipc',
            'stream_id' => 1007
        ]
    ]
];

// Конфиг сформированный от конфигуратора
const CONFIG = [
    'exchange' => EXCHANGE,
    'exchanges' => [EXCHANGE],
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
        'BTC' => 30000,
        'ETH' => 2000,
        'USDT' => 1
    ],
    'max_deal_amounts' => [
        'BTC' => 0.01,
        'ETH' => 0.1,
        'USDT' => 200
    ],
    'max_depth' => 10,
    'fees' => [
        EXCHANGE => 0.1
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
    'aeron' => AERON
];

// Настройки всех ядров
const CORES = [
    'balancer_by_market_order' => [
        'debug' => DEBUG,
        'exchange' => EXCHANGE,
        'node' => NODE,
        'instance' => INSTANCE,
        'algorithm' => 'balancer',
        'gate_sleep' => 5,
        'config' => CONFIG
    ],
    'cross_3t' => [
        'debug' => DEBUG,
        'exchange' => EXCHANGE,
        'node' => NODE,
        'instance' => INSTANCE,
        'algorithm' => 'cross_3t_php',
        'sleep' => 10,
        'made_html_vision_file' => '/var/www/html/test.html',
        'gate_sleep' => 5,
        'config' => CONFIG
    ],
    'receive_data' => [
        'debug' => DEBUG,
        'exchange' => EXCHANGE,
        'node' => NODE,
        'instance' => INSTANCE,
        'algorithm' => 'receive_data',
        'sleep' => 10,
        'send_ping_to_log_server' => true,
        'gate_sleep' => 5,
        'config' => CONFIG
    ]
];

