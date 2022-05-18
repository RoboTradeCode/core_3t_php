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
    public function sendWorkCore(int $data, $message = null): bool|string
    {

        return Aeron::messageEncode([
            'event' => 'info', 
            'exchange' => $this->exchange,
            'node' => $this->node,
            'instance' => $this->instance,
            'action' => 'ping', 
            'message' => $message,
            'algo' => $this->algo,
            'timestamp' => $this->robotrade_api->getMicrotime(),
            'data' => $data
        ]);

    }

}