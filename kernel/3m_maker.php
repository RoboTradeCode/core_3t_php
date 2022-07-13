<?php

use Src\M3Maker\Api;
use Src\M3Maker\MemcachedData;
use Src\M3Maker\M3Maker;
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

while (true) {

    // задержка между каждым циклом
    usleep($config['sleep']);

    //DEBUG ONLY
    $m3_maker->sleepDebug(); //DEBUG ONLY

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

        // проходимся по каждой бирже
        foreach ($config['exchanges'] as $exchange) {

            // проходимся по всем рынкам
            foreach ($config['3m_maker_markets'][$exchange] as $symbol => $symbols_for_profit_bid_and_ask) {

                // если есть балансы данной биржи и ордербук данного рынка
                if (
                    count($symbols_for_profit_bid_and_ask) == 2 &&
                    isset($balances[$exchange]) &&
                    isset($orderbooks[$symbol][$exchange]) &&
                    isset($orderbooks[$symbols_for_profit_bid_and_ask[0]][$exchange]) &&
                    isset($orderbooks[$symbols_for_profit_bid_and_ask[1]][$exchange])
                ) {

                    //DEBUG ONLY
                    $m3_maker->printBalances($balances[$exchange], $exchange);//DEBUG ONLY

                    // берем данные price_increment и amount_increment для данной биржи и рынка
                    $market = $m3_maker->getMarket($exchange, $symbol);

                    // если существует сетка для данной биржи и рынка, иначе создать эту сетку
                    if (isset($grids[$exchange][$symbol])) {

                        // берем base_asset и quote_asset для данного рынка
                        list($base_asset, $quote_asset) = explode('/', $symbol);

                        // считаем profit bid и profit ask (profit ask должен быть больше profit bid)
                        [$profit_bid, $profit_ask] = $m3_maker->countProfit($exchange, $orderbooks, $symbols_for_profit_bid_and_ask, $base_asset, $quote_asset);

                        // находим все, что в сетке ниже $profit_bid и выше $profit_ask
                        [$lower, $higher] = $m3_maker->getLowerAndHigherGrids($grids[$exchange][$symbol], $profit_bid, $profit_ask);

                        // найти количество ордеров на продажу и количество ореров на покупку
                        [$sell_orders, $buy_orders] = $m3_maker->getTheNumberOfSellAndBuyOrders($balances, $exchange, $base_asset, $quote_asset, 'free');

                        //DEBUG ONLY
                        $m3_maker->printArray(
                            [
                                'best_ask' => $orderbooks[$symbol][$exchange]['asks'][0][0],
                                'best_bid' => $orderbooks[$symbol][$exchange]['bids'][0][0],
                                'profit_ask' => $profit_ask,
                                'profit_bid' => $profit_bid,
                            ],
                            'Profit bid, ask, Best Orderbook'
                        ); //DEBUG ONLY

                        // если в сумме количество ордеров верно, то делать расчеты дальше
                        if (($sell_orders + $buy_orders) == 2 * $config['order_pairs']) {

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
                            if (isset($real_orders[$exchange]) && !empty($real_orders_for_symbol)) {

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
                                            $api->cancelOrder($exchange, $must_real_order['client_order_id'], $must_real_order['symbol']);

                                        }

                                    }

                                }

                                // если массив теоретических ордеров, которые должны быть поставлены не пуст
                                if (!empty($must_orders)) {

                                    // пройтись по каждому элементу массива
                                    foreach ($must_orders as $must_key => $must_order) {

                                        // отправить по aeron на постановку ордеров
                                        $api->createOrder($exchange, $must_order['symbol'], $must_order['type'], $must_order['side'], $must_order['amount'], $must_order['price']);

                                    }

                                }

                                // если существут переменная $micro-times для данной биржи, то
                                if (isset($microtimes[$exchange])) {

                                    // если прошло по времени более $config['send_command_to_get_status_time'] / 1000000 секунд, то
                                    if ((microtime(true) - $microtimes[$exchange]) >= $config['send_command_to_get_status_time'] / 1000000) {

                                        // пройтись по всем реальным ордерам
                                        foreach ($real_orders_for_symbol as $real_order) {

                                            // отправить по aeron на получение статусов ордеров
                                            $api->getOrderStatus($exchange, $real_order['client_order_id'], $real_order['symbol']);

                                        }

                                        // обновить время переменной $microtimes для данной биржи
                                        $microtimes[$exchange] = microtime(true);

                                    }

                                } else {

                                    // зафиксировать первоначальное время переменной $microtimes для данной биржи
                                    $microtimes[$exchange] = microtime(true);

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
                                        $api->createOrder($exchange, $order['symbol'], $order['type'], $order['side'], $order['amount'], $order['price']);

                                    }

                                    // создать переменную $was_send_create_orders для биржи, чтобы понимать, что постановка на первоначальные ордера были выставлены
                                    $was_send_create_orders[$exchange][$symbol] = true;

                                } else {

                                    // выводит сообщение, что не может получить ордера от гейтов
                                    echo '[' . date('Y-m-d H:i:s') . '] [WARNING] No orders were received from the gates for exchange: ' . $exchange . ' and symbol: ' . $symbol . PHP_EOL;

                                    sleep(1);

                                }

                            }

                        } else {

                            $sell_orders = $config['order_pairs'];

                            $buy_orders = $config['order_pairs'];

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
                            if (isset($real_orders[$exchange]) && !empty($real_orders_for_symbol)) {

                                // теоретические ордера, которые должны быть поставлены и ордера, которые уже должны быть поставлены в реальности
                                [$must_orders, $must_real_orders] = $m3_maker->getMustOrders($orders, $real_orders_for_symbol);

                                //DEBUG ONLY
                                $m3_maker->printOrders($real_orders_for_symbol, 'Real Orders'); //DEBUG ONLY

                                //DEBUG ONLY
                                $m3_maker->printOrders($must_real_orders, 'Real Orders for Cancel'); //DEBUG ONLY

                                // если массив реальных ордеров, которых не должны быть, не пуст (т. е. есть лишние ордера)
                                if (!empty($must_real_orders)) {

                                    // пройтись по каждому элемента массива
                                    foreach ($must_real_orders as $must_real_key => $must_real_order) {

                                        // если статус закрыт, отменен, истек или отклонён
                                        if (!in_array($must_real_order['status'], ['closed', 'canceled', 'expired', 'rejected'])) {

                                            // отправить по aeron на отмену ордеров
                                            $api->cancelOrder($exchange, $must_real_order['client_order_id'], $must_real_order['symbol']);

                                        }

                                    }

                                }

                            }

                            // выводит, что сумма количества ордеров на продажу и покупку не соответствует суммарному количеству ордеров, которые необходимо поставить
                            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] ($sell_orders + $buy_orders) != 2 * $config[$order_pairs]' . PHP_EOL;

                            // отправить на лог сервер ошибку
                            $api->sendErrorToLogServer($exchange, '($sell_orders + $buy_orders) != 2 * $config[$order_pairs]');

                            sleep(2);

                        }

                    } else {

                        // строит сетку для данной биржи и данного рынка
                        $grids[$exchange][$symbol] = $m3_maker->getGrids($orderbooks[$symbol][$exchange], $market['price_increment']);

                    }

                } else {

                    // Выводит в консоль сообщения, что нет $balances[$exchange]
                    if (count($symbols_for_profit_bid_and_ask) != 2)
                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] count($symbols_for_profit_bid_and_ask) != 2' . PHP_EOL;

                    // Выводит в консоль сообщения, что нет $balances[$exchange]
                    if (!isset($balances[$exchange]))
                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($balances[$exchange])' . PHP_EOL;

                    // Выводит в консоль сообщения, что нет $orderbooks[$symbol][$exchange]
                    if (!isset($orderbooks[$symbol][$exchange]))
                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($orderbooks[$symbol][$exchange]' . PHP_EOL;

                    // Выводит в консоль сообщения, что нет $orderbooks["ETH/USDT"][$exchange]
                    if (!isset($orderbooks[$symbols_for_profit_bid_and_ask[0]][$exchange]))
                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($orderbooks[$symbols_for_profit_bid_and_ask[0]][$exchange])' . PHP_EOL;

                    // Выводит в консоль сообщения, что нет $orderbooks["ETH/BTC"][$exchange]
                    if (!isset($orderbooks[$symbols_for_profit_bid_and_ask[1]][$exchange]))
                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($orderbooks[$symbols_for_profit_bid_and_ask[1]][$exchange])' . PHP_EOL;

                    sleep(1);

                }

            }

        }

        // каждые 2 секунды выполняется условие
        if (Time::timeUp(1)) {

            // отправить пинг на лог сервер
            $api->sendPingToLogServer($m3_maker->getInteration());

        }

    } else {

        echo '[' . date('Y-m-d H:i:s') . '] No data in memcached' . PHP_EOL;

        sleep(1);

    }

}
