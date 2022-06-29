<?php

use Src\DiscreteTime;
use Src\M3Maker\Api;
use Src\M3Maker\MemcachedData;
use Src\M3Maker\M3Maker;

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

$api = new Api($config);

// класс для формирования данных, взятых из memcached
$multi_core = new MemcachedData($config['exchanges'], $config['markets'], $config['expired_orderbook_time']);

$discrete_time = new DiscreteTime();

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

        // проходимся по каждой бирже
        foreach ($config['exchanges'] as $exchange) {

            // берем рынок
            $symbol = $config['3m_maker_markets'][$exchange];

            // если есть балансы данной биржи и ордербук данного рынка
            if (isset($balances[$exchange]) && isset($orderbooks[$symbol][$exchange]) && isset($orderbooks['ETH/USDT'][$exchange]) && isset($orderbooks['ETH/BTC'][$exchange])) {

                // берем данные price_increment и amount_increment для данной биржи и рынка
                $market = $m3_maker->getMarket($exchange, $symbol);

                // если существует сетка для данной биржи и рынка, иначе создать эту сетку
                if (isset($grids[$exchange][$symbol])) {

                    // берем base_asset и quote_asset для данного рынка
                    list($base_asset, $quote_asset) = explode('/', $symbol);

                    // считаем profit bid
                    $profit_bid = $orderbooks['ETH/USDT'][$exchange]['bids'][0][0] / $orderbooks['ETH/BTC'][$exchange]['asks'][0][0];

                    // считаем profit ask
                    $profit_ask = $orderbooks['ETH/USDT'][$exchange]['asks'][0][0] / $orderbooks['ETH/BTC'][$exchange]['bids'][0][0];

                    // находим все, что в сетке ниже $best_bid и выше $best_ask
                    [$lower, $higher] = $m3_maker->getLowerAndHigherGrids($grids[$exchange][$symbol], $profit_bid, $profit_ask);

                    // найти количество ордеров на продажу и количество ореров на покупку
                    [$sell_orders, $buy_orders] = $m3_maker->getTheNumberOfSellAndBuyOrders($balances, $exchange, $base_asset, $quote_asset);

                    // если в сумме количество ордеров верно, то делать расчеты дальше
                    if (($sell_orders + $buy_orders) == 2 * $config['order_pairs']) {

                        // получаем массив ордлеров на продажу и покупку
                        $orders = $m3_maker->getOrders($sell_orders, $buy_orders, $symbol, $lower, $higher);

                        // если у нас есть реальные ордера
                        if (isset($real_orders[$exchange])) {

                            // теоретические ордера, которые должны быть поставлены и ордера, которые уже должны быть поставлены в реальности
                            [$must_orders, $must_real_orders] = $m3_maker->getMustOrders($orders, $real_orders[$exchange]);

                            // если массив реальных ордеров, которых не должны быть, не пуст (т. е. есть лишние ордера)
                            if (!empty($must_real_orders)) {

                                // пройтись по каждому элемента массива
                                foreach ($must_real_orders as $must_real_key => $must_real_order) {

                                    // если статус закрыт, отменен, истек или отклонён
                                    if (in_array($must_real_order['status'], ['closed', 'canceled', 'expired', 'rejected'])) {

                                        // удалить его из массива реальных ордеров
                                        unset($real_orders[$exchange][$must_real_key]);

                                    } else {

                                        // отправить по aeron на отмену ордеров
                                        $api->cancelOrder($exchange, $must_real_order['id'], $must_real_order['symbol']);

                                    }

                                }

                                // перезаписать в memcached, только нужные реальные ордера
                                $memcached->set(
                                    $exchange . '_orders',
                                    $real_orders
                                );

                            }

                            // если массив теоретических ордеров, которые должны быть поставлены не пуст
                            if (!empty($must_orders)) {

                                // пройтись по каждому элементу массива
                                foreach ($must_orders as $must_key => $must_order) {

                                    // отправить по aeron на постановку ордеров
                                    $api->createOrder($exchange, $must_order['symbol'], $must_order['type'], $must_order['side'], $must_order['amount'], $must_order['price']);

                                }

                            }

                            // если существут переменная $microtimes для данной биржи, то
                            if (isset($microtimes[$exchange])) {

                                // если прошло по времени более $config['send_command_to_get_status_time'] / 1000000 секунд, то
                                if ((microtime(true) - $microtimes[$exchange]) >= $config['send_command_to_get_status_time'] / 1000000) {

                                    // пройтись по всем реальным ордерам
                                    foreach ($real_orders[$exchange] as $real_order) {

                                        // отправить по aeron на получение статусов ордеров
                                        $api->getOrderStatus($exchange, $real_order['id'], $real_order['symbol']);

                                    }

                                    // обновить время переменной $microtimes для данной биржи
                                    $microtimes[$exchange] = microtime(true);

                                }

                            } else {

                                // зафиксировать первоначальное время переменной $microtimes для данной биржи
                                $microtimes[$exchange] = microtime(true);

                            }

                            // если есть переменная $was_send_create_orders для биржи, то удалить её, чтобы в случае закрытии всех ордеров, они поставились заново
                            if (isset($was_send_create_orders[$exchange]))
                                unset($was_send_create_orders[$exchange]);

                        } else {

                            // если нет переменной $was_send_create_orders для данной биржи, то это означает, что пока нет первой постановки ордеров
                            if (!isset($was_send_create_orders[$exchange])) {

                                // пройтись по всем ордерам
                                foreach ($orders as $order) {

                                    // отправить на постановку ордеров
                                    $api->createOrder($exchange, $order['symbol'], $order['type'], $order['side'], $order['amount'], $order['price']);

                                }

                                // создать переменную $was_send_create_orders для биржи, чтобы понимать, что постановка на первоначальные ордера были выставлены
                                $was_send_create_orders[$exchange] = true;

                            } else {

                                // выводит сообщение, что не может получить ордера от гейтов
                                echo '[' . date('Y-m-d H:i:s') . '] [WARNING] No orders were received from the gates' . PHP_EOL;

                                sleep(1);

                            }

                        }

                    } else {

                        // выводит, что сумма количества ордеров на продажу и покупку не соответствует суммарному количеству ордеров, которые необходимо поставить
                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] ($sell_orders + $buy_orders) != 2 * $config[$order_pairs]' . PHP_EOL;

                        // отправить на лог сервер ошибку
                        $api->sendErrorToLogServer($exchange, '($sell_orders + $buy_orders) != 2 * $config[$order_pairs]');

                        sleep(5);

                    }

                } else {

                    // строит сетку для данной биржи и данного рынка
                    $grids[$exchange][$symbol] = $m3_maker->getGrids($orderbooks[$symbol][$exchange], $market['price_increment']);

                }

            } else {

                // Выводит в консоль сообщения, что нет $balances[$exchange]
                if (!isset($balances[$exchange]))
                    echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($balances[$exchange])' . PHP_EOL;

                // Выводит в консоль сообщения, что нет $orderbooks[$symbol][$exchange]
                if (!isset($orderbooks[$symbol][$exchange]))
                    echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($orderbooks[$symbol][$exchange]' . PHP_EOL;

                // Выводит в консоль сообщения, что нет $orderbooks["ETH/USDT"][$exchange]
                if (!isset($orderbooks['ETH/USDT'][$exchange]))
                    echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($orderbooks["ETH/USDT"][$exchange])' . PHP_EOL;

                // Выводит в консоль сообщения, что нет $orderbooks["ETH/BTC"][$exchange]
                if (!isset($orderbooks['ETH/BTC'][$exchange]))
                    echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Not isset: isset($orderbooks["ETH/BTC"][$exchange])' . PHP_EOL;

                sleep(1);

            }

        }

        // каждые 2 секунды выполняется условие
        if ($discrete_time->proof()) {

            // отправить пинг на лог сервер
            $api->sendPingToLogServer($m3_maker->getInteration());

        }

    } else {

        echo '[' . date('Y-m-d H:i:s') . '] No data in memcached' . PHP_EOL;

        sleep(1);

    }

}
