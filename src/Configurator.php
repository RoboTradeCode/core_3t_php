<?php

namespace Src;

class Configurator
{

    public static string $configurator_url = 'https://configurator.robotrade.io/';

    public static function getConfigForReceiveData(string $file_path): array
    {

        $config = json_decode(file_get_contents($file_path), true);

        return json_decode(
            self::file_get_contents_ssl(self::$configurator_url . $config['exchange'] . '/' . $config['instance'] . '?only_new=false'),
            true
        )['data']['configs']['core_config'];

    }

    public static function getConfig(string $exchange, string $instance): array
    {

        $config_from_configurator = json_decode(
            self::file_get_contents_ssl(self::$configurator_url . $exchange . '/' . $instance . '?only_new=false'),
            true
        )['data'];

        $cross_3t_php = $config_from_configurator['configs']['core_config']['cross_3t_php'];

        // проверяет все конфиги
        self::proofConfig($cross_3t_php, $config_from_configurator);

        return [
            'exchange' => $cross_3t_php['exchange'],
            'exchanges' => $cross_3t_php['exchanges'],
            'expired_orderbook_time' => $cross_3t_php['expired_orderbook_time'],
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

    private static function proofConfig($cross_3t_php, $config_from_configurator): void
    {

        if (
            !isset($config_from_configurator['markets']) ||
            !isset($config_from_configurator['assets_labels']) ||
            !isset($config_from_configurator['routes'])
        ) {

            echo 'Wrong config get from configurator' . PHP_EOL;

            print_r($cross_3t_php);
            print_r($config_from_configurator);
            echo PHP_EOL;

            die('Dead');

        }

    }

    private static function file_get_contents_ssl(string $url): bool|string
    {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        curl_setopt($ch, CURLOPT_HEADER, false);

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_REFERER, $url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3000); // 3 sec.

        curl_setopt($ch, CURLOPT_TIMEOUT, 10000); // 10 sec.

        $result = curl_exec($ch);

        curl_close($ch);

        return $result;

    }

}