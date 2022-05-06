<?php

namespace Src;

class Configurator
{

    public string $configurator_url = 'https://configurator.robotrade.io/';

    public function getConfig(string $exchange, string $instance)
    {

        return json_decode(
            file_get_contents($this->configurator_url . $exchange . '/' . $instance . '?only_new=false'),
            true
        );

    }

}