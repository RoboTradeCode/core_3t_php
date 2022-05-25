<?php

namespace Src;

class Configurator
{

    public static string $configurator_url = 'https://configurator.robotrade.io/';

    public static function getConfig(string $exchange, string $instance): array
    {

        $config_from_configurator = json_decode(
            file_get_contents(self::$configurator_url . $exchange . '/' . $instance . '?only_new=false'),
            true
        )['data'];

        $cross_3t_php = $config_from_configurator['configs']['core_config']['cross_3t_php'];

        // проверяет все конфиги
        self::proofConfig($cross_3t_php, $config_from_configurator);

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

}