<?php

use robotrade\Api;
use Src\Cross3T;

require dirname(__DIR__) . '/index.php';
require  dirname(__DIR__) . '/config/aeron_config_c.php';

// подключение к memcached
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// API для формирования сообщения для отправки по aeron
$robotrade_api = new Api(EXCHANGE, ALGORITHM, NODE, INSTANCE);

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
        'BTC' => -10,
        'ETH' => -10,
        'USDT' => -10
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

        // фильтрация баланса в диапазоне минимальном и максимальном
        //$cross_3t->filterBalanceByMinAndMAxDealAmount($balances);

        // запускаем алгоритм и получаем лучший результат
        if ($best_result = $cross_3t->run($balances, $orderbooks)) {

            // для каждого шага, если результат выпал на текущую биржу, отправить сообщение на создание ордера
            foreach (['step_one', 'step_two', 'step_three'] as $step) {
                if ($best_result[$step]['exchange'] == EXCHANGE) {
                    $robotrade_api->createOrder(
                        $best_result[$step]['amountAsset'] . '/' . $best_result[$step]['priceAsset'],
                        'market',
                        'buy',
                        $best_result[$step]['amount'],
                        $best_result[$step]['price'],
                        'Create order step_one'
                    );
                }
            }

            print_r($best_result) . PHP_EOL;

        }

    } else {

        echo '[WARNING] $balances or $orderbooks or $configis is empty' . PHP_EOL;

    }

    if (!empty($undefined)) {

        echo '[WARNING] $undefined is not empty' . PHP_EOL;

        print_r($undefined) . PHP_EOL;

    }

}
