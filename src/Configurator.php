<?php

namespace Src;

class Configurator
{

    public string $configurator_url = 'https://configurator.robotrade.io/';

    public function getConfig(string $exchange, string $instance): array
    {

        $config_from_configurator = json_decode(
            file_get_contents($this->configurator_url . $exchange . '/' . $instance . '?only_new=false'),
            true
        )['data'];

        $cross_3t_php = $config_from_configurator['configs']['core_config']['cross_3t_php'];

        return [
            'exchange' => $cross_3t_php['exchange'],
            'exchanges' => $cross_3t_php['exchanges'],
            'min_profit' => $cross_3t_php['min_profit'],
            'min_deal_amounts' => $cross_3t_php['min_deal_amounts'],
            'rates' => $cross_3t_php['rates'],
            'max_deal_amounts' => $cross_3t_php['max_deal_amounts'],
            'max_depth' => $cross_3t_php['max_depth'],
            'fees' => $cross_3t_php['fees'],
            'aeron' => $config_from_configurator['configs']['core_config']['aeron'],
            'markets' => $config_from_configurator['markets'],
            'assets_labels' => $config_from_configurator['assets_labels'],
            'routes' => $config_from_configurator['routes']
        ];

    }

}