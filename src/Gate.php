<?php

namespace Src;

use AeronPublisher;
use robotrade\Api;

class Gate
{

    private AeronPublisher $publisher;
    private Api $robotrade_api;
    private array $commands;
    private int $sleep;

    /**
     * Нужно передать объект класса AeronPublisher и Robotrade Api
     *
     * @param AeronPublisher $publisher Gate AeronPublisher
     * @param Api $robotrade_api Api create message to send command to Gate
     * @param int $sleep Sleep between gate commands
     */
    public function __construct(AeronPublisher $publisher, Api $robotrade_api, int $sleep = 0)
    {

        $this->publisher = $publisher;

        $this->robotrade_api = $robotrade_api;

        $this->sleep = $sleep;

    }

    /**
     * Отправляет гейту команду на закрытие всех ордеров
     *
     * @return $this
     */
    public function cancelAllOrders(): static
    {

        $message = 'Cancel All orders';

        $this->publisher->offer($this->robotrade_api->cancelAllOrders($message));

        return $this->do($message);

    }

    /**
     * Отправляет гейту команду на получение балансов
     *
     * @return $this
     */
    public function getBalances(array $assets = null): static
    {

        $message = 'Get All Balances';

        $this->publisher->offer($this->robotrade_api->getBalances($assets));

        return $this->do($message);

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
            $message .=  $command . '. ';

        $this->echo($message);

    }

    /**
     * @param string $message echo $message to console
     * @return $this
     */
    private function do(string $message): static
    {

        $this->commands[] = $message;

        $this->echo($message);

        sleep($this->sleep);

        return $this;

    }

    /**
     * @param string $message  echo $message to console
     * @return void
     */
    private function echo(string $message): void
    {

        echo $message . PHP_EOL;

    }

}