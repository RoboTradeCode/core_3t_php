<?php

namespace Src\Multi;

class MultiConfigurator
{

    /**
     * Возвращает полноценный конфиг для мульти ядра
     *
     * @param string $multi_config_path Путь к файлу конфигу multi_3t.json
     * @return array Полный конфиг
     */
    public static function getConfig(string $multi_config_path): array
    {

        $config = json_decode(file_get_contents($multi_config_path), true);

        foreach ($config['exchanges'] as $exchange) {

            $config_from_configurator = json_decode(
                self::file_get_contents_ssl('https://configurator.robotrade.io/' . $exchange . '/' . $config['instances'][$exchange] . '?only_new=false'),
                true
            )['data'];

            $routes[$exchange] = $config_from_configurator['routes'];

            $assets_labels[$exchange] = $config_from_configurator['assets_labels'];

            $markets[$exchange] = $config_from_configurator['markets'];

        }

        if (empty($routes) || empty($assets_labels) || empty($markets)) {

            echo '[' . date('Y-m-d H:i:s') . '] Die $routes or $assets_labels or $markets empty' . PHP_EOL;

            die();

        }

        $route = self::reformatRoutesToMulti($routes);

        $assets_label = self::reformatAssetLabelsToMulti($assets_labels);

        if (empty($route) || empty($assets_label) ) {

            echo '[' . date('Y-m-d H:i:s') . '] Die $route or $assets_label or $markets empty' . PHP_EOL;

            die();

        }

        $config['routes'] = $route;

        $config['assets_labels'] = $assets_label;

        $config['markets'] = $markets;

        return $config;

    }

    private static function reformatAssetLabelsToMulti($assets_labels): array
    {

        $assets = [];

        foreach ($assets_labels as $as_l) {

            $assets = array_merge($assets, array_column($as_l, 'common'));

        }

        foreach (array_unique($assets) as $item) {

            $assets_label[] = ['exchange' => $item, 'common' => $item];

        }

        return $assets_label ?? [];

    }

    private static function reformatRoutesToMulti($routes): array
    {

        $route = [];

        $exist = false;

        foreach ($routes as $r) {

            foreach ($r as $item) {

                foreach ($route as $it) {

                    if ($it == $item) {

                        $exist = true;

                        break;

                    } else {

                        $exist = false;

                    }

                }

                if (!$exist) {

                    $route[] = $item;

                }

            }

        }

        return $route;

    }

    /**
     * Делает curl запрос
     * @param string $url Url адрес
     * @return bool|string возвращает контент
     */
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