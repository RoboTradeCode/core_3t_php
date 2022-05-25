<?php

// Режим отладки (при true будут вычисляться debug информация и брать конфиг из файла)
const DEBUG = false;

// Биржа
const EXCHANGE = 'ftx';

// Нода
const NODE = 'core';

// Instance
const INSTANCE = '1';

// Каналы aeron, фактически поле aeron для core_config из конфигуратора
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
    ], //все publishers
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
    ] //все subscribers
];

// Конфиг сформированный от конфигуратора
const CONFIG = [
    'exchange' => EXCHANGE, //название биржи
    'exchanges' => [EXCHANGE], //список всех бирж для взятие всех данных из memcached
    'expired_orderbook_time' => 5, //сколько по времени в секундах считаем ордербуки актуальными
    'min_profit' => [
        'BTC' => 0,
        'ETH' => 0,
        'USDT' => 0
    ], //минимальная прибыль в каждом ассете
    'min_deal_amounts' => [
        'BTC' => 0,
        'ETH' => 0,
        'USDT' => 0
    ], //минимальный размер сделки в каждом ассете
    'rates' => [
        'BTC' => 30000,
        'ETH' => 2000,
        'USDT' => 1
    ], //курсы (в принципе нужны только для сравнения в какой валюте прибыль лучше)
    'max_deal_amounts' => [
        'BTC' => 0.01,
        'ETH' => 0.1,
        'USDT' => 200
    ], //максимальный размер сделки в каждом ассете
    'max_depth' => 10, //максимальная глубина стакана
    'fees' => [
        EXCHANGE => 0.1
    ], //комиссия для каждой биржи
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
    ], //список рынков и их данных для данной биржи
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
    ], //список треугольников
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
    ], //список символов и их названия на бирже
    'aeron' => AERON //настройки aeron
];

// Настройки всех ядров, ключи - это названия ядер
const CORES = [
    'balancer_by_market_order' => [
        'debug' => DEBUG, //debug
        'exchange' => EXCHANGE, //название биржи
        'node' => NODE, //нода
        'instance' => INSTANCE, //instance
        'algorithm' => 'balancer', //название ядра (алгоритма)
        'gate_sleep' => 2, //задержа команд от ядра к гейту (ядро отправило команду и sleep(gate_sleep))
        'config' => CONFIG //конфиг из конфигуратора
    ],
    'cross_3t' => [
        'debug' => DEBUG, //debug
        'exchange' => EXCHANGE, //название биржи
        'node' => NODE, //нода
        'instance' => INSTANCE, //instance
        'algorithm' => 'cross_3t_php', //название ядра (алгоритма)
        'sleep' => 10, //задержа в милисекундах в цикле while
        'made_html_vision_file' => '/var/www/html/test.html', //куда записывать результат расчета gerResults
        'gate_sleep' => 2, //задержа команд от ядра к гейту (ядро отправило команду и sleep(gate_sleep))
        'config' => CONFIG //конфиг из конфигуратора
    ],
    'receive_data' => [
        'debug' => DEBUG, //debug
        'exchange' => EXCHANGE, //название биржи
        'node' => NODE, //нода
        'instance' => INSTANCE, //instance
        'algorithm' => 'receive_data', //название ядра (алгоритма)
        'sleep' => 10, //задержа в милисекундах в цикле while
        'send_ping_to_log_server' => true, //отсылать в лог сервер $i++ для каждой интерации раз в 2 секунды
        'gate_sleep' => 2, //задержа команд от ядра к гейту (ядро отправило команду и sleep(gate_sleep))
        'config' => CONFIG //конфиг из конфигуратора
    ],
    'test_c' => [
        'exchange' => EXCHANGE, //название биржи
        'node' => NODE, //нода
        'instance' => INSTANCE, //instance
        'algorithm' => 'test_c', //название ядра (алгоритма)
        'symbol' => 'BTC/USDT', //рынок для постановки ордера
        'amount' => 0.00055, //количество на покупку в данном рынке
        'sleep' => 10, //задержа в милисекундах в цикле while
        'gate_sleep' => 2, //задержа команд от ядра к гейту (ядро отправило команду и sleep(gate_sleep))
        'config' => CONFIG //конфиг из конфигуратора
    ],
    'test_receive_data_c' => [
        'exchange' => EXCHANGE, //название биржи
        'node' => NODE, //нода
        'instance' => INSTANCE, //instance
        'algorithm' => 'test_receive_data_c', //название ядра (алгоритма)
        'sleep' => 10, //задержа в милисекундах в цикле while
        'gate_sleep' => 2, //задержа команд от ядра к гейту (ядро отправило команду и sleep(gate_sleep))
        'config' => CONFIG //конфиг из конфигуратора
    ]
];

