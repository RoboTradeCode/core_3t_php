<?php

namespace Src;

class Api
{
    public string $exchange;
    public string $algo;
    public string $node;
    public string $instance;

    /**
     * Создает экземпляр Api
     *
     * @param string $exchange Название биржи
     * @param string $algo Название алгоритма
     * @param string $node Нода (core или gate)
     * @param string $instance Экземпляр
     */
    public function __construct(string $exchange, string $algo, string $node, string $instance)
    {
        $this->exchange = $exchange;
        $this->algo = $algo;
        $this->node = $node;
        $this->instance = $instance;
    }

    /**
     * Генерирует сообщение по базовому шаблону
     *
     * @param string $event
     * @param string $action
     * @param string $data
     * @param string|null $message
     * @return string
     */
    private function messageGenerator(string $event, string $action, string $data, string $message = null): string
    {
        return '{"event":"' . $event . '",' .
            '"exchange":"' . $this->exchange . '",' .
            '"node":"' . $this->node . '",' .
            '"instance": "' . $this->instance . '",' .
            '"action":"' . $action . '",' .
            '"message":"' . $message . '",' .
            '"algo":"' . $this->algo . '",' .
            '"timestamp":' . $this->getMicrotime() . ',' .
            '"data":' . $data . '}';
    }

    /**
     * Возвращает текущий timestamp в микросекундах
     *
     * @return int
     */
    public function getMicrotime(): int
    {
        return intval(microtime(true) * 1000000);
    }

    /**
     * Создание ордера
     *
     * @param string $symbol Торговая пара
     * @param string $type Тип (limit или market)
     * @param string $side Направление сделки
     * @param float $amount Количество
     * @param float $price Цена
     * @param string|null $message Сообщение (необязательно)
     * @return string
     */
    public function createOrder(string $symbol, string $type, string $side, float $amount, float $price, string $message = null): string
    {
        $event = "command";
        $action = "create_order";

        $data = '[{"symbol":"' . $symbol . '",' .
            '"type":"' . $type . '",' .
            '"side":"' . $side . '",' .
            '"amount":' . $amount . ',' .
            '"price":' . $price . '}]';

        return $this->messageGenerator($event, $action, $data, $message);
    }

    /**
     * Генерирует ордер для createOrders()
     *
     * @param string $symbol Торговая пара
     * @param string $type Тип (limit или market)
     * @param string $side Направление сделки
     * @param float $amount Количество
     * @param float $price Цена
     * @return string
     */
    public function generateOrder(string $symbol, string $type, string $side, float $amount, float $price): string
    {
        return '{"symbol":"' . $symbol . '",' .
            '"type":"' . $type . '",' .
            '"side":"' . $side . '",' .
            '"amount":' . $amount . ',' .
            '"price":' . $price . '},';
    }

    /**
     * Создание нескольких ордеров.
     * Ордера генерируются функцией generateOrder()
     *
     * @param array $orders Массив ордеров
     * @param string|null $message Сообщение (необязательно)
     * @return string
     */
    public function createOrders(array $orders, string $message = null): string
    {
        $event = "command";
        $action = "create_order";

        $data = '[' . rtrim(implode('', $orders), ',') . ']';

        return $this->messageGenerator($event, $action, $data, $message);
    }

    /**
     * Отмена ордера
     *
     * @param string $id ID ордера
     * @param string $symbol Торговая пара
     * @param string|null $message Сообщение (необязательно)
     * @return string
     */
    public function cancelOrder(string $id, string $symbol, string $message = null): string
    {
        $event = "command";
        $action = "cancel_order";

        $data = '[{"id":"' . $id . '","symbol":"' . $symbol . '"}]';

        return $this->messageGenerator($event, $action, $data, $message);
    }

    /**
     * Отмена нескольких ордеров
     * Структура массива ордеров: [["id1", "symbol1"], ["id2", "symbol2"], ...]
     *
     * @param array $orders Массив ордеров
     * @param string|null $message Сообщение (необязательно)
     * @return string
     */
    public function cancelOrders(array $orders, string $message = null): string
    {

        $event = "command";
        $action = "cancel_order";

        $data = '[';

        foreach ($orders as $order) {
            $data .= '{"id":"' . $order['id'] . '","symbol":"' . $order['symbol'] . '"},';
        }

        $data = rtrim($data, ',') . ']';

        return $this->messageGenerator($event, $action, $data, $message);
    }

    /**
     * Получить статус ордера
     *
     * @param string $id ID ордера
     * @param string $symbol Торговая пара
     * @param string|null $message Сообщение (необязательно)
     * @return string
     */
    public function getOrderStatus(string $id, string $symbol, string $message = null): string
    {
        $event = "command";
        $action = "order_status";

        $data = '{"id":"' . $id . '","symbol":"' . $symbol . '"}';

        return $this->messageGenerator($event, $action, $data, $message);
    }

    /**
     * Информация о балансах активов.
     * Если массив активов пуст, возвращает информацию обо всех активах.
     *
     * @param array|null $assets Массив активов ["BTC", "ETH", ...]
     * @param string|null $message Сообщение (необязательно)
     * @return string
     */
    public function getBalances(array $assets = null, string $message = null): string
    {
        $event = "command";
        $action = "get_balances";

        if (is_array($assets) && count($assets) > 0) {
            $data = '{"assets":[';

            foreach ($assets as $asset) {
                $data .= '"' . $asset . '",';
            }

            $data = rtrim($data, ',') . ']}';
        } else {
            $data = '""';
        }

        return $this->messageGenerator($event, $action, $data, $message);
    }

    /**
     * Информация о создании, закрытие, статусе ордера
     *
     * @param string $action Действие (order_created, order_closed или order_status)
     * @param string $id ID ордера
     * @param int $timestamp Таймстамп создания/закрытия или статуса
     * @param string $status Текущий статус (open, closed или canceled)
     * @param string $symbol Торговая пара
     * @param string $type Тип (limit или market)
     * @param string $side Направление сделки
     * @param float $amount Количество
     * @param float $price Цена
     * @param float $filled Текущая заполненность
     * @param string|null $message Сообщение (необязательно)
     * @return string
     */
    public function orderInfo(string $action, string $id, int $timestamp, string $status, string $symbol, string $type, string $side, float $amount, float $price, float $filled, string $message = null): string
    {
        $event = "data";

        $data = '{"id":"' . $id . '",' .
            '"timestamp":"' . $timestamp . '",' .
            '"status":"' . $status . '",' .
            '"symbol":"' . $symbol . '",' .
            '"type":"' . $type . '",' .
            '"side":"' . $side . '",' .
            '"amount":' . $amount . ',' .
            '"price":' . $price . ',' .
            '"filled":' . $filled . '}';

        return $this->messageGenerator($event, $action, $data, $message);
    }

    /**
     * Информация о балансах (от гейта к ядру).
     * Структура массива балансов: ["BTC" => ["free" => 0.0123, "used" => 0, "total" => 0.0123], ...]
     *
     * @param array $balances Массив балансов
     * @param string|null $message Сообщение (необязательно)
     * @return string
     */
    public function balances(array $balances, string $message = null): string
    {
        $event = "data";
        $action = "balances";

        $data = json_encode($balances);

        return $this->messageGenerator($event, $action, $data, $message);
    }

    /**
     * Ордербук отправляемый гейтом ядру.
     * Структура массива ордербука:
     * ["bids" => [[price, amount], ...],"asks" => [[price, amount], ...]]
     *
     * @param array $orderbook Массив ордербука
     * @param string $symbol Торговая пара
     * @param int|null $timestamp Таймстамп получения
     * @param string|null $message Сообщение (необязательно)
     * @return string
     */
    public function orderbook(array $orderbook, string $symbol, int $timestamp = null, string $message = null): string
    {
        $event = "data";
        $action = "orderbook";

        $orderbook["symbol"] = $symbol;
        $orderbook["timestamp"] = $timestamp;

        $data = json_encode($orderbook);

        return $this->messageGenerator($event, $action, $data, $message);
    }

    /**
     * Сообщения об ошибке
     *
     * @param string $action Действие при котором возникла ошибка
     * @param mixed $data Данные об ошибке
     * @param string|null $message Сообщение об ошибке
     * @return string
     */
    public function error(string $action, $data = "", string $message = null): string
    {
        $event = "error";

        $data = json_encode($data);

        return $this->messageGenerator($event, $action, $data, $message);
    }
}