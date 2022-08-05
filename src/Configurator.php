<?php

namespace Src;

class Configurator
{

    public static string $configurator_url = 'https://configurator.robotrade.io/';

    public static function getConfigApi(string $file_path): array
    {

        $config = json_decode(file_get_contents($file_path), true);

        return json_decode(
            self::file_get_contents_ssl(self::$configurator_url . $config['exchange'] . '/' . $config['instance'] . '?only_new=false'),
            true
        )['data'];

    }

    public static function getCoreConfigApi(string $file_path): array
    {

        $config = json_decode(file_get_contents($file_path), true);

        return json_decode(
            self::file_get_contents_ssl(self::$configurator_url . $config['exchange'] . '/' . $config['instance'] . '?only_new=false'),
            true
        )['data']['configs']['core_config'];

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