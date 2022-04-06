<?php

namespace Src\Test;

use Src\Api;

class TestAgentFormatData
{

    private Api $robotrade_api;

    public function __construct(string $exchange, string $algo = 'test', string $node = 'core', string $instance = 'test')
    {

        $this->robotrade_api = new Api($exchange, $algo, $node, $instance);

    }

    public function sendAgentGetFullConfig(): array
    {

        return [
            'event' => 'get',
            'instance' => 'core',
            'action' => 'get_full_config',
            'algo' => 'cross_3t',
            'data' => ['get_full_config' => true],
            'timestamp' => $this->robotrade_api->getMicrotime()
        ];

    }

    public function aeron_configs_destinations(): array
    {

        return [
            'aeron:udp?endpoint=172.31.14.205:40461|control=172.31.14.205:40456',
        ];

    }

}