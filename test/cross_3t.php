<?php

use Src\Cross3T;

require dirname(__DIR__) . '/vendor/autoload.php';

// подключение к memcached
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// получить конфиг из memcached. Пока не получит конфиг, алгоритм выполняться не будет
while (!isset($config)) {

    sleep(1);

    // берет конфиг из memcached
    $memcached_data = $memcached->get('config');

    // если нашел запись в memcached
    if ($memcached_data) {

        // присвоить конфиг
        $config = $memcached_data;

        // удалить из memcached
        $memcached->delete('config');

        echo '[Ok] Config is set' . PHP_EOL;

    } else
        echo '[WARNING] Config is not set' . PHP_EOL;

}

$config = [
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
            ['source_asset' => 'ETH', 'common_symbol' => 'ETH/USDT', 'operation' => 'sell'],
            ['source_asset' => 'USDT', 'common_symbol' => 'BTC/USDT', 'operation' => 'buy'],
            ['source_asset' => 'BTC', 'common_symbol' => 'ETH/BTC', 'operation' => 'buy'],
        ],
    ],
    'max_depth' => 10,
    'fees' => [
        'kuna' => 0.1,
        'huobi' => 0.1
    ],
];

// создаем класс cross 3t
$cross_3t = new Cross3T($config);

while (true) {

    sleep(1);

    // берем все данные из memcached
    $all_keys = $cross_3t->getAllMemcachedKeys();

    $memcached_data = $memcached->getMulti($all_keys) ?? [];

    print_r($all_keys);

    // проверяем конфиг на обновление, если появился новый конфиг, обновить его, удалить данные конфига из memcached
    if ($cross_3t->proofConfigOnUpdate($config, $memcached_data))
        $memcached->delete('config');

    // отформировать и отделить все данные, полученные из memcached
    $all_data = $cross_3t->reformatAndSeparateData($memcached_data);

    // балансы, ордербуки и неизвестные данные
    $balances = $all_data['balances'];
    $orderbooks = $all_data['orderbooks'];
    $undefined = $all_data['undefined'];

    // если есть все необходимые данные
    if (!empty($balances) && !empty($orderbooks) && !empty($config)) {

        // проверка на минимальный и по максимальному баланс
        $cross_3t->filterBalanceByMinAndMAxDealAmount($balances);

        foreach ($config['routes'] as $route) {

            $step_one = array_shift($route);
            $step_two = array_shift($route);
            $step_three = array_shift($route);

            $combinations = [
                'main_asset_name' => $step_one['source_asset'],
                'main_asset_amount_precision' => 0.00000001,
                'asset_one_name' => $step_two['source_asset'],
                'asset_two_name' => $step_three['source_asset'],
                'step_one_symbol' => $step_one['common_symbol'],
                'step_two_symbol' => $step_two['common_symbol'],
                'step_three_symbol' => $step_three['common_symbol'],
            ];

            $min_profit = $config['min_profit'][$combinations['main_asset_name']];

            $max_deal_amount = $config['max_deal_amounts'][$combinations['main_asset_name']];

            $max_depth = $config['max_depth'];

            print_r($cross_3t->findBestOrderbooks($route, $balances, $orderbooks));

            $orderbook = $cross_3t->getOrderbook(
                $combinations,
                $cross_3t->findBestOrderbooks($route, $balances, $orderbooks)
            );



        }

        print_r($balances) . PHP_EOL;
        //print_r($orderbook) . PHP_EOL;
        //print_r($config) . PHP_EOL;

    } else {

        echo '[WARNING] $balances or $orderbooks or $configis is empty' . PHP_EOL;

    }

    if (!empty($undefined)) {

        echo '[WARNING] $undefined is not empty' . PHP_EOL;

        print_r($undefined) . PHP_EOL;

    }

    //$cross_3t->run($balances, $orderbooks, $rates, $server, $data['symbol']);

}
