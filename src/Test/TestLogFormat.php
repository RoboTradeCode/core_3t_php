<?php

namespace Src\Test;

use Src\Api;

class TestLogFormat
{

    private Api $robotrade_api;
    private string $exchange;

    public function __construct(string $exchange, string $algo = 'test', string $node = 'core', string $instance = 'test')
    {

        $this->exchange = $exchange;

        $this->robotrade_api = new Api($exchange, $algo, $node, $instance);

    }

    public function sendLog($message)
    {

        return [
            'event' => 'send',
            'exchange' => $this->exchange,
            'node' => 'core',
            'instance' => '1',
            'action' => 'log',
            'message' => $message,
            'algo' => 'cross_3t_php',
            'timestamp' => $this->robotrade_api->getMicrotime(),
            'data' => [],
        ];

    }

}