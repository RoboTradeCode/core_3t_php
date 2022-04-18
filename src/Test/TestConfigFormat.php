<?php

namespace Src\Test;

class TestConfigFormat
{

    public static function coreConfigConfigurator(): array
    {

        return [
            'aeron' => [
                'publishers' => [
                    'gateway' => [
                        'channel' => 'aeron:udp?control=172.31.14.205:40456|control-mode=dynamic',
                        'stream_id' => 1003
                    ],
                    'metrics' => [
                        'channel' => 'aeron:udp?endpoint=3.66.183.27:44444',
                        'stream_id' => 1001
                    ],
                    'log' => [
                        'channel' => 'aeron:udp?control=172.31.14.205:40456|control-mode=dynamic',
                        'stream_id' => 1005
                    ]
                ],
                'subscribers' => [
                    'balance' => [
                        'channel' => 'aeron:udp?control-mode=manual',
                        'stream_id' => 1002,
                        'destinations' => [
                            'aeron:udp?endpoint=172.31.14.205:40461|control=172.31.14.205:40456'
                        ]
                    ],
                    'orderbooks' => [
                        'channel' => 'aeron:udp?control-mode=manual',
                        'stream_id' => 1001,
                        'destinations' => [
                            'aeron:udp?endpoint=172.31.14.205:40458|control=172.31.14.205:40456',
                            'aeron:udp?endpoint=172.31.14.205:40459|control=18.159.92.185:40456',
                            'aeron:udp?endpoint=172.31.14.205:40460|control=54.248.171.18:40456',
                        ]
                    ]
                ]
            ]
        ];

    }

    public static function marketsConfigurator(): array
    {

        return [
            [
                'exchange_symbol' => 'BTC-USDT',
                'common_symbol' => 'BTC/USDT',
                'price_increment' => 0.1,
                'amount_increment' => 0.00000001,
                'min_amount' => 0.00001,
                'max_amount' => 9000,
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
                'min_amount' => 0.0001,
                'max_amount' => 9000,
                'assets' => [
                    'base' => 'ETH',
                    'quote' => 'USDT',
                ]
            ],
            [
                'exchange_symbol' => 'ETH-BTC',
                'common_symbol' => 'ETH/BTC',
                'price_increment' => 0.000001,
                'amount_increment' => 0.0000001,
                'min_amount' => 0.0001,
                'max_amount' => 100000,
                'assets' => [
                    'base' => 'ETH',
                    'quote' => 'BTC',
                ]
            ]
        ];

    }

    public static function assetsLabelConfigurator(): array
    {

        return [
            ['exchange' => 'BTC', 'common' => 'BTC'],
            ['exchange' => 'ETH', 'common' => 'ETH'],
            ['exchange' => 'USDT', 'common' => 'USDT'],
        ];

    }

    public static function routeConfigurator(): array
    {

        return [
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
            [
                ['source_asset' => 'BTC', 'common_symbol' => 'BTC/USDT', 'operation' => 'sell'],
                ['source_asset' => 'USDT', 'common_symbol' => 'ETH/USDT', 'operation' => 'buy'],
                ['source_asset' => 'ETH', 'common_symbol' => 'ETH/BTC', 'operation' => 'sell'],
            ],
            [
                ['source_asset' => 'USDT', 'common_symbol' => 'BTC/USDT', 'operation' => 'buy'],
                ['source_asset' => 'BTC', 'common_symbol' => 'ETH/BTC', 'operation' => 'buy'],
                ['source_asset' => 'ETH', 'common_symbol' => 'ETH/USDT', 'operation' => 'sell'],
            ],
            [
                ['source_asset' => 'BTC', 'common_symbol' => 'ETH/BTC', 'operation' => 'buy'],
                ['source_asset' => 'ETH', 'common_symbol' => 'ETH/USDT', 'operation' => 'sell'],
                ['source_asset' => 'USDT', 'common_symbol' => 'BTC/USDT', 'operation' => 'buy'],
            ],
            [
                ['source_asset' => 'ETH', 'common_symbol' => 'ETH/BTC', 'operation' => 'sell'],
                ['source_asset' => 'BTC', 'common_symbol' => 'BTC/USDT', 'operation' => 'sell'],
                ['source_asset' => 'USDT', 'common_symbol' => 'ETH/USDT', 'operation' => 'buy'],
            ],
        ];

    }

}