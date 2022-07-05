<?php

namespace Src;

class Configurator
{

    public static string $configurator_url = 'https://configurator.robotrade.io/';

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
            !isset($cross_3t_php['exchange']) ||
            !isset($cross_3t_php['exchanges']) ||
            !isset($cross_3t_php['expired_orderbook_time']) ||
            !isset($cross_3t_php['min_profit']) ||
            !isset($cross_3t_php['min_deal_amounts']) ||
            !isset($cross_3t_php['rates']) ||
            !isset($cross_3t_php['max_deal_amounts']) ||
            !isset($cross_3t_php['max_depth']) ||
            !isset($cross_3t_php['fees']) ||
            !isset($config_from_configurator['configs']['core_config']['aeron']) ||
            !isset($config_from_configurator['configs']['core_config']['aeron']['publishers']['gate']) ||
            !isset($config_from_configurator['configs']['core_config']['aeron']['publishers']['log']) ||
            !isset($config_from_configurator['configs']['core_config']['aeron']['subscribers']['balance']['channel']) ||
            !isset($config_from_configurator['configs']['core_config']['aeron']['subscribers']['balance']['destinations']) ||
            !isset($config_from_configurator['configs']['core_config']['aeron']['subscribers']['balance']['stream_id']) ||
            !isset($config_from_configurator['configs']['core_config']['aeron']['subscribers']['orderbooks']['channel']) ||
            !isset($config_from_configurator['configs']['core_config']['aeron']['subscribers']['orderbooks']['destinations']) ||
            !isset($config_from_configurator['configs']['core_config']['aeron']['subscribers']['orderbooks']['stream_id']) ||
            !isset($config_from_configurator['configs']['core_config']['aeron']['subscribers']['orders']['channel']) ||
            !isset($config_from_configurator['configs']['core_config']['aeron']['subscribers']['orders']['destinations']) ||
            !isset($config_from_configurator['configs']['core_config']['aeron']['subscribers']['orders']['stream_id']) ||
            !isset($config_from_configurator['markets']) ||
            !isset($config_from_configurator['assets_labels']) ||
            !isset($config_from_configurator['routes'])
        ) {

            echo 'Wrong config get from configurator' . PHP_EOL;

            echo 'Example config: 
            "core_config":{
                "cross_3t_php":{
                    "exchange":"ftx",
                    "exchanges":[
                        "ftx"
                    ],
                    "expired_orderbook_time":500000,
                    "min_profit":{
                        "BTC":0,
                        "ETH":0,
                        "USDT":0
                    },
                    "min_deal_amounts":{
                        "BTC":0,
                        "ETH":0,
                        "USDT":0
                    },
                    "rates":{
                        "BTC":31000,
                        "ETH":2300,
                        "USDT":1
                    },
                    "max_deal_amounts":{
                        "BTC":0.01,
                        "ETH":0.1,
                        "USDT":200
                    },
                    "max_depth":3,
                    "fees":{
                        "ftx":0.1
                    }
                },
                "aeron":{
                    "publishers":{
                        "gate":{
                            "channel":"aeron:udp?control=172.31.14.205:40456|control-mode=dynamic",
                            "stream_id":1003
                        },
                        "log":{
                            "channel":"aeron:udp?control=172.31.14.205:40456|control-mode=dynamic",
                            "stream_id":1005
                        }
                    },
                    "subscribers":{
                        "balance":{
                            "channel":"aeron:udp?control-mode=manual",
                            "destinations":[
                                "aeron:udp?endpoint=172.31.14.205:40461|control=172.31.14.205:40456"
                            ],
                            "stream_id":1002
                        },
                        "orderbooks":{
                            "channel":"aeron:udp?control-mode=manual",
                            "destinations":[
                                "aeron:udp?endpoint=172.31.14.205:40458|control=172.31.14.205:40456",
                                "aeron:udp?endpoint=172.31.14.205:40459|control=18.159.92.185:40456",
                                "aeron:udp?endpoint=172.31.14.205:40460|control=54.248.171.18:40456"
                            ],
                            "stream_id":1001
                        }
                    }
                }
            }
            ';
            echo PHP_EOL;

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