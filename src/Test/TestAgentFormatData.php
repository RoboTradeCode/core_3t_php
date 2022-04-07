<?php

namespace Src\Test;

use Src\Aeron;
use Src\Api;

class TestAgentFormatData
{

    private Api $robotrade_api;
    private string $exchange;

    public function __construct(string $exchange, string $algo = 'test', string $node = 'core', string $instance = 'test')
    {

        $this->exchange = $exchange;

        $this->robotrade_api = new Api($exchange, $algo, $node, $instance);

    }

    public function sendAgentGetFullConfig(): array
    {

        return [
            'event' => 'config',
            'exchange' => $this->exchange,
            'instance' => 'core',
            'action' => 'get_full_config',
            'algo' => 'cross_3t',
            'data' => [],
            'timestamp' => $this->robotrade_api->getMicrotime()
        ];

    }

    public function aeron_configs_destinations(): array
    {

        return [
            'aeron:udp?endpoint=172.31.14.205:40461|control=172.31.14.205:40456',
        ];

    }

    public function configAndMarketInfoFromAgent() : bool|string
    {

        return Aeron::messageEncode([
            'event' => 'config',
            'node' => 'agent',
            'instance' => '1',
            'action' => 'get_full_config',
            'timestamp' => $this->robotrade_api->getMicrotime(),
            'data' => [
                'markets' => TestConfigFormat::marketsConfigurator(),
                //'assets_labels' => TestConfigFormat::assetsLabelConfigurator(),
                'routes' => TestConfigFormat::routeConfigurator(),
                'core_config' => TestConfigFormat::coreConfigConfigurator(),
            ]
        ]);

    }

}