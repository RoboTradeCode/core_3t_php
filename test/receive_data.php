<?php

use Src\Aeron;
use Src\Test\TestAeronFormatData;
use Src\Test\TestAgentFormatData;

require dirname(__DIR__) . '/index.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$kuna = new TestAeronFormatData('kuna');
$huobi = new TestAeronFormatData('huobi');

$datas = [
    $kuna->orderbook([
        'bids' => [
            [45793.94, 0.007352],
            [45793.93, 0.972659],
            [45793.91, 2.140652],
            [45793.77, 0.000436],
            [45793.76, 0.0261],
        ],
        'asks' => [
            [46286.36, 0.26865],
            [46286.37, 0.102385],
            [46286.38, 0.018932],
            [46354.34, 0.060668],
            [46399.99, 1.503934],
        ],
        'symbol' => 'BTC/USDT',
        'timestamp' => 1649070863000,
    ]),
    $kuna->orderbook([
        'bids' => [
            [3458.95, 0.113382],
            [3458.4, 0.072866],
            [3458.38, 0.00598],
            [3458.37, 0.283166],
            [3450.01, 7.966356],
        ],
        'asks' => [
            [3488.2, 0.061544],
            [3488.29, 0.081731],
            [3489.99, 6.332389],
            [3490, 0.05],
            [3492.39, 2.838225],
        ],
        'symbol' => 'ETH/USDT',
        'timestamp' => 1645184308000,
    ]),
    $kuna->orderbook([
        'bids' => [
            [0.074654, 0.167439],
            [0.074653, 7.313466],
            [0.074652, 2.656536],
            [0.073302, 0.008376],
            [0.0733, 0.004],
        ],
        'asks' => [
            [0.075491, 0.208399],
            [0.075499, 0.049435],
            [0.0755, 0.1],
            [0.075751, 0.081731],
            [0.075752, 1.583097],
        ],
        'symbol' => 'ETH/BTC',
        'timestamp' => 1645184308000,
    ]),
    $kuna->balances([
        'BTC' => [
            'free' => 0.01,
            'used' => 0.001,
            'total' => 0.011,
        ],
        'USDT' => [
            'free' => 400.21,
            'used' => 52,
            'total' => 452.21,
        ]
    ]),
    $huobi->orderbook([
        'bids' => [
            [46139.25, 0.142856],
            [46137.86, 0.0002],
            [46137.75, 0.004335],
            [46136.31, 0.000569],
            [46136.3, 0.007872],
        ],
        'asks' => [
            [46139.26, 6.070242],
            [46139.31, 0.008925],
            [46139.32, 1.114745],
            [46139.36, 0.874494],
            [46139.41, 0.005],
        ],
        'symbol' => 'BTC/USDT',
        'timestamp' => 1649070863000,
    ]),
    $huobi->orderbook([
        'bids' => [
            [3471.2, 0.1676],
            [3471.08, 0.0576],
            [3470.72, 0.5],
            [3470.65, 0.05],
            [3470.62, 0.0027],
        ],
        'asks' => [
            [3471.21, 28.1314],
            [3471.24, 10.585],
            [3471.25, 10.7653],
            [3471.29, 1.4358],
            [3471.3, 8.3304],
        ],
        'symbol' => 'ETH/USDT',
        'timestamp' => 1645184308000,
    ]),
    $huobi->orderbook([
        'bids' => [
            [0.075232, 0.0183],
            [0.07523, 0.0028],
            [0.075229, 4.4],
            [0.075222, 0.1206],
            [0.075221, 0.88],
        ],
        'asks' => [
            [0.075233, 0.0514],
            [0.075244, 0.19],
            [0.075246, 0.0028],
            [0.075247, 0.88],
            [0.075249, 0.0391],
        ],
        'symbol' => 'ETH/BTC',
        'timestamp' => 1645184308000,
    ]),
    $huobi->balances([
        'BTC' => [
            'free' => 0.015,
            'used' => 0,
            'total' => 0.015,
        ],
        'USDT' => [
            'free' => 100.28,
            'used' => 0,
            'total' => 452.28,
        ]
    ]),
    (new TestAgentFormatData('binance'))->configAndMarketInfoFromAgent()
];

while(true) {

    sleep(1);

    foreach ($datas as $data) {

        if ($data = Aeron::messageDecode($data)) {

            if ($data['event'] == 'data' && $data['node'] == 'gate') {

                $memcached->set(
                    $data['exchange'] . '_' . $data['action'] . (($data['action'] == 'orderbook') ? '_' . $data['data']['symbol'] : ''),
                    $data['data']
                );

            } elseif (
                $data['event'] == 'config' && $data['node'] == 'agent' &&
                $data['data']['markets'] && $data['data']['assets_labels'] && $data['data']['routes']
            ) {

                $memcached->set(
                    'config',
                    $data['data']
                );

            } else {

                echo '[ERROR] data broken' . PHP_EOL;

            }

        }

    }

    echo 'data was saved into Memcache' . PHP_EOL;

}
