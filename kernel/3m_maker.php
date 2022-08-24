<?php

use Src\Api;
use Src\M3Maker\M3Maker;
use Src\M3Maker\MemcachedData;
use Src\Time;

require dirname(__DIR__) . '/index.php';

// подключение к memcached
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// очистить все, что есть в memcached
$memcached->flush();

// создание класса для m3 maker
$m3_maker = new M3Maker(dirname(__DIR__) . '/config/3m_maker.json');

// получение конфигов
$config = $m3_maker->getConfig();

// класс Api для работы с гейтами и лог сервером
$api = new Api($config);

// класс для формирования данных, взятых из memcached
$multi_core = new MemcachedData($config['exchanges'], $config['markets'], $config['expired_orderbook_time']);

// биржа
$exchange = $config['exchange'];

while (true) {

    // задержка между каждым циклом
    usleep($config['sleep']);

    // берем данные из memcached
    if ($memcached_data = $memcached->getMulti($multi_core->keys)) {

        // получаем все данные из memcached
        $all_data = $multi_core->reformatAndSeparateData($memcached_data);

        // балансы
        $balances = $all_data['balances'];

        // ордербуки
        $orderbooks = $all_data['orderbooks'];

        // реальные ордера
        $real_orders = $all_data['orders'];

        if (isset($balances[$exchange])) {

            //DEBUG ONLY
            $m3_maker->printBalances($balances[$exchange]);//DEBUG ONLY

            // найти количество ордеров на продажу и количество ореров на покупку
            [$sell_orders_all_markets, $buy_orders_all_markets] = $m3_maker->getTheNumberOfSellAndBuyOrdersByFullBalanceOnAllMarkets($balances[$exchange], array_keys($config['3m_maker_markets'][$exchange]));

            // проходимся по всем рынкам
            foreach ($config['3m_maker_markets'][$exchange] as $symbol => $symbols_for_profit_bid_and_ask) {

                // если есть балансы данной биржи и ордербук данного рынка
                if (
                    count($symbols_for_profit_bid_and_ask) == 2 &&
                    isset($orderbooks[$symbol][$exchange]) &&
                    isset($orderbooks[$symbols_for_profit_bid_and_ask[0]]) &&
                    isset($orderbooks[$symbols_for_profit_bid_and_ask[1]])
                ) {

                    echo PHP_EOL . $exchange . ' ' . $symbol . ' [START][START][START][START][START][START][START][START][START][START][START][START][START][START]-------------------------------------------' . PHP_EOL;

                    // берем данные price_increment и amount_increment для данной биржи и рынка
                    $market = $m3_maker->getMarket($exchange, $symbol);

                    // если существует сетка для данной биржи и рынка, иначе создать эту сетку
                    if (isset($grids[$symbol])) {

                        // берем base_asset и quote_asset для данного рынка
                        list($base_asset, $quote_asset) = explode('/', $symbol);

                        // считаем profit bid и profit ask (profit ask должен быть больше profit bid)
                        [$profit_bid, $profit_ask] = $m3_maker->countProfit($orderbooks, $symbols_for_profit_bid_and_ask, $base_asset, $quote_asset, $config['fee_maker']);

                        if ($orderbooks[$symbol][$exchange]['bids'][0][0] >= $profit_ask) {

                            echo "\033[31m" . '[' . date('Y-m-d H:i:s') . '] [WARNING] bid high than profit_ask. Use taker fee. Old: ' . $profit_bid . "\033[0m" . PHP_EOL;

                            [, $profit_ask] = $m3_maker->countProfit($orderbooks, $symbols_for_profit_bid_and_ask, $base_asset, $quote_asset, $config['fee_taker']);

                        }

                        if ($orderbooks[$symbol][$exchange]['asks'][0][0] <= $profit_bid) {

                            echo "\033[31m" . '[' . date('Y-m-d H:i:s') . '] [WARNING] ask less than profit_bid. Use taker fee. Old: ' . $profit_ask . "\033[0m" . PHP_EOL;

                            [$profit_bid, ] = $m3_maker->countProfit($orderbooks, $symbols_for_profit_bid_and_ask, $base_asset, $quote_asset, $config['fee_taker']);

                        }

                        // находим все, что в сетке ниже $profit_bid и выше $profit_ask+
                        [$lower, $higher] = $m3_maker->getLowerAndHigherGrids($grids[$symbol], $profit_bid, $profit_ask);

                        // найти количество ордеров на продажу и количество ореров на покупку
                        [$sell_orders, $buy_orders] = [$sell_orders_all_markets[$symbol], $buy_orders_all_markets[$symbol]];

                        // если мы не вышли засетку, то идти по алгоритму, иначе удалить сетку
                        if ($sell_orders <= count($lower) || $buy_orders <= count($higher)) {

                            //DEBUG ONLY
                            $m3_maker->printArray(
                                [
                                    'best_bid' => $orderbooks[$symbol][$exchange]['bids'][0][0],
                                    'best_ask' => $orderbooks[$symbol][$exchange]['asks'][0][0],
                                    'profit_bid' => $profit_bid,
                                    'profit_ask' => $profit_ask,
                                ],
                                'Profit bid, ask, Best Orderbook'
                            ); //DEBUG ONLY

                            // получаем массив ордеров на продажу и покупку
                            $orders = $m3_maker->getOrders($sell_orders, $buy_orders, $symbol, $lower, $higher, $orderbooks[$symbol][$exchange]['asks'][0][0], $market['amount_increment']);

                            //DEBUG ONLY
                            $m3_maker->printOrders($orders, 'Theoretical Orders'); //DEBUG ONLY

                            // фильтруем ордера только для одного символа
                            if (isset($real_orders[$exchange]))
                                $real_orders_for_symbol = array_filter(
                                    $real_orders[$exchange],
                                    fn($real_order_for_symbol) => $real_order_for_symbol['symbol'] == $symbol
                                );

                            // если у нас есть реальные ордера
                            if (!empty($real_orders_for_symbol)) {

                                // теоретические ордера, которые должны быть поставлены и ордера, которые уже должны быть поставлены в реальности
                                [$must_orders, $must_real_orders] = $m3_maker->getMustOrders($orders, $real_orders_for_symbol);

                                //DEBUG ONLY
                                $m3_maker->printOrders($real_orders_for_symbol, 'Real Orders'); //DEBUG ONLY

                                //DEBUG ONLY
                                $m3_maker->printOrders($must_real_orders, 'Real Orders for Cancel'); //DEBUG ONLY

                                //DEBUG ONLY
                                $m3_maker->printOrders($must_orders, 'Must Create Orders'); //DEBUG ONLY

                                // если массив реальных ордеров, которых не должны быть, не пуст (т. е. есть лишние ордера)
                                if (!empty($must_real_orders)) {

                                    // пройтись по каждому элемента массива
                                    foreach ($must_real_orders as $must_real_key => $must_real_order) {

                                        // если статус закрыт, отменен, истек или отклонён
                                        if (in_array($must_real_order['status'], ['closed', 'canceled', 'expired', 'rejected'])) {

                                            // удалить его из массива реальных ордеров
                                            unset($real_orders_for_symbol[$must_real_key]);

                                        } else {

                                            // отправить по aeron на отмену ордеров
                                            $api->cancelOrder($must_real_order['client_order_id'], $must_real_order['symbol']);

                                        }

                                    }

                                }

                                // если массив теоретических ордеров, которые должны быть поставлены не пуст
                                if (!empty($must_orders)) {

                                    if (!isset($real_orders_for_symbols_backup[$exchange][$symbol]) || count($real_orders_for_symbol) != $real_orders_for_symbols_backup[$exchange][$symbol]) {

                                        // пройтись по каждому элементу массива
                                        foreach ($must_orders as $must_key => $must_order) {

                                            // отправить по aeron на постановку ордеров
                                            $api->createOrder($must_order['symbol'], $must_order['type'], $must_order['side'], $must_order['amount'], $must_order['price']);

                                            break;

                                        }

                                        $real_orders_for_symbols_backup[$exchange][$symbol] = count($real_orders_for_symbol);

                                    } else {

                                        if (isset($microtimes_for_real_orders_for_symbols_backup[$exchange][$symbol])) {

                                            if ((microtime(true) - $microtimes_for_real_orders_for_symbols_backup[$exchange][$symbol]) >= $config['expired_command_to_create_order'] / 1000000) {

                                                unset($real_orders_for_symbols_backup[$exchange][$symbol]);

                                            }

                                        } else {

                                            $microtimes_for_real_orders_for_symbols_backup[$exchange][$symbol] = microtime(true);

                                        }

                                    }

                                }

                                // если есть переменная $was_send_create_orders для биржи, то удалить её, чтобы в случае закрытии всех ордеров, они поставились заново
                                if (isset($was_send_create_orders[$exchange][$symbol]))
                                    unset($was_send_create_orders[$exchange][$symbol]);

                            } else {

                                // если нет переменной $was_send_create_orders для данной биржи, то это означает, что пока нет первой постановки ордеров
                                if (!isset($was_send_create_orders[$exchange][$symbol])) {

                                    // пройтись по всем ордерам
                                    foreach ($orders as $order) {

                                        // отправить на постановку ордеров
                                        $api->createOrder($order['symbol'], $order['type'], $order['side'], $order['amount'], $order['price']);

                                        break;

                                    }

                                    // создать переменную $was_send_create_orders для биржи, чтобы понимать, что постановка на первоначальные ордера были выставлены
                                    $was_send_create_orders[$exchange][$symbol] = true;

                                } else {

                                    if (isset($microtimes_for_was_send_create_orders[$exchange][$symbol])) {

                                        if ((microtime(true) - $microtimes_for_was_send_create_orders[$exchange][$symbol]) >= $config['expired_command_to_create_order'] / 1000000) {

                                            unset($was_send_create_orders[$exchange][$symbol]);

                                        }

                                    } else {

                                        $microtimes_for_was_send_create_orders[$exchange][$symbol] = microtime(true);

                                    }

                                    // выводит сообщение, что не может получить ордера от гейтов
                                    echo '[' . date('Y-m-d H:i:s') . '] [WARNING] No orders were received from the gates for exchange: ' . $exchange . ' and symbol: ' . $symbol . PHP_EOL;

                                    sleep(1);

                                }

                            }

                        } else {

                            echo '[' . date('Y-m-d H:i:s') . '] Delete grid for ' . $symbol . PHP_EOL;

                            // удалить сетку
                            unset($grids[$symbol]);

                        }

                    } else {

                        // строит сетку для данной биржи и данного рынка
                        $grids[$symbol] = $m3_maker->getGrids($orderbooks[$symbol][$exchange], $market['price_increment']);

                    }

                    echo PHP_EOL . $exchange . ' ' . $symbol . ' [END][END][END][END][END][END][END][END][END][END][END][END][END][END]-------------------------------------------' . PHP_EOL;

                } else {

                    // Выводит в консоль сообщения, что нет $balances[$exchange]
                    if (count($symbols_for_profit_bid_and_ask) != 2)
                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] count($symbols_for_profit_bid_and_ask) != 2' . PHP_EOL;

                    // Выводит в консоль сообщения, что нет $orderbooks[$symbol][$exchange]
                    if (!isset($orderbooks[$symbol][$exchange]))
                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($orderbooks[$symbol][$exchange]' . PHP_EOL;

                    // Выводит в консоль сообщения, что нет $orderbooks["ETH/USDT"][$exchange]
                    if (!isset($orderbooks[$symbols_for_profit_bid_and_ask[0]]))
                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($orderbooks[$symbols_for_profit_bid_and_ask[0]])' . PHP_EOL;

                    // Выводит в консоль сообщения, что нет $orderbooks["ETH/BTC"][$exchange]
                    if (!isset($orderbooks[$symbols_for_profit_bid_and_ask[1]]))
                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($orderbooks[$symbols_for_profit_bid_and_ask[1]])' . PHP_EOL;

                    sleep(1);

                }

            }

        } else {

            // Выводит в консоль сообщения, что нет $balances[$exchange]
            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($balances[$exchange])' . PHP_EOL;

            sleep(1);

        }

        // каждую секунду выполняется условие
        if (Time::timeUp(1)) {

            // отправить пинг на лог сервер
            $api->sendPingToLogServer($m3_maker->getInteration());

        }

    } else {

        echo '[' . date('Y-m-d H:i:s') . '] No data in memcached' . PHP_EOL;

        sleep(1);

    }

}
