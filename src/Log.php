<?php

namespace Src;

use robotrade\Api;

class Log
{

    private Api $robotrade_api;
    private string $exchange;
    private string $algo;
    private string $node;
    private string $instance;

    public function __construct(string $exchange, string $algo, string $node, string $instance)
    {

        $this->exchange = $exchange;

        $this->algo = $algo;

        $this->node = $node;

        $this->instance = $instance;

        $this->robotrade_api = new Api($exchange, $algo, $node, $instance);

    }

    public function sendErrorToLogServer(string $action, null|string $data = "", string $message = null): void
    {

        sendHttpLog($this->robotrade_api->error($action, $data, $message));

    }

    public function sendWorkCore(int $data, string $message = null): bool|string
    {

        return $this->generateMessage('ping', $data, $message, 'data');

    }

    public function send3MMakerTakerFromMaker(array $data): bool|string
    {

        return $this->generateMessage('algo_stats', $data, 'Limit order is taker', 'data');

    }

    public function sendExpectedTriangle(array $data, string $message = null): bool|string
    {

        return $this->generateMessage('expected_triangle', $data, $message, 'data');

    }

    public function sendFullBalances(array $data, string $message = null): bool|string
    {

        return $this->generateMessage('balance_log', $data, $message, 'command');

    }

    private function generateMessage(string $action, mixed $data, string $message = null, string $event = 'info'): bool|string
    {

        return Aeron::messageEncode([
            'event' => $event,
            'exchange' => $this->exchange,
            'node' => $this->node,
            'instance' => $this->instance,
            'algo' => $this->algo,
            'message' => $message,
            'action' => $action,
            'timestamp' => $this->robotrade_api->getMicrotime(),
            'data' => $data
        ]);

    }

}