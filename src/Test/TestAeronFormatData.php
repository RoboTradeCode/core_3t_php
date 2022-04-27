<?php

namespace Src\Test;

use Src\Aeron;
use robotrade\Api;

class TestAeronFormatData
{

    private string $exchange;
    private string $algo;
    private Api $robotrade_api;

    public function __construct(string $exchange, string $algo = 'test', string $node = 'core', string $instance = 'test')
    {

        $this->exchange = $exchange;

        $this->algo = $algo;

        $this->robotrade_api = new Api($exchange, $algo, $node, $instance);

    }

    public function orderBook(array $data = []): bool|string
    {

        if (empty($data))
            $data = [
                'bids' => [
                    [40640.07, 0.100003],
                    [40506.27, 0.00087],
                    [40506.24, 0.010105],
                    [40505.05, 0.709313],
                    [40505.03, 0.445368],
                ],
                'asks' => [
                    [40774.12, 0.316596],
                    [40774.13, 0.0618],
                    [40774.18, 0.175997],
                    [40774.19, 0.26467],
                    [40774.2, 0.03113],
                ],
                'symbol' => 'BTC/USDT',
                'timestamp' => 1645184308000,
            ];

        return Aeron::messageEncode(array_merge($this->commonPart('orderbook'), ['data' => $data]));

    }

    public function balances(array $data = []): bool|string
    {

        if (empty($data))
            $data = [
                'BTC' => [
                    'free' => 0.1,
                    'used' => 0.01,
                    'total' => 0.11,
                ],
                'USDT' => [
                    'free' => 400.21,
                    'used' => 52,
                    'total' => 452.21,
                ]
            ];

        return Aeron::messageEncode(array_merge($this->commonPart('balances'), ['data' => $data]));

    }

    private function commonPart(string $action) :array
    {

        return [
            'event' => 'data',
            'exchange' => $this->exchange,
            'node' => 'gate',
            'instance' => '1',
            'action' => $action,
            'message' => null,
            'algo' => $this->algo,
            'timestamp' => $this->robotrade_api->getMicrotime(),
        ];

    }

}