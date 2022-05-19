<?php

namespace Src;

use AeronPublisher;
use robotrade\Api;

class Core
{

    private AeronPublisher $publisher;
    private Api $robotrade_api;
    private array $commands;

    /**
     * Нужно передать объект класса AeronPublisher и Robotrade Api
     *
     * @param AeronPublisher $publisher Gate AeronPublisher
     * @param Api $robotrade_api Api create message to send command to Gate
     */
    public function __construct(AeronPublisher $publisher, Api $robotrade_api)
    {

        $this->publisher = $publisher;
        $this->robotrade_api = $robotrade_api;

    }

    /**
     * Отправляет гейту команду на закрытие всех ордеров
     *
     * @return $this
     */
    public function cancelAllOrders(): static
    {

        $this->publisher->offer($this->robotrade_api->cancelAllOrders('Cancel All orders'));

        $this->commands[] = 'Cancel All Orders.';

        sleep(1);

        return $this;

    }

    /**
     * Отправляет гейту команду на получение балансов
     *
     * @return $this
     */
    public function getBalances(array $assets = null): static
    {

        $this->publisher->offer($this->robotrade_api->getBalances($assets));

        $this->commands[] = 'Get All Balances.';

        sleep(1);

        return $this;

    }

    /**
     * Выводит сообщение на экран какие команды были отправлены
     *
     * @return void
     */
    public function send(): void
    {

        $message = 'Send commands: ';

        foreach ($this->commands as $command)
            $message .=  $command . ' ';

        echo $message . PHP_EOL;

    }

}