<?php

namespace Src;

use Aeron\Publisher;
use Exception;
use robotrade\Api;

class Gate
{

    private Publisher $publisher;
    private Api $robotrade_api;
    private array $commands;
    private int $sleep;

    /**
     * Нужно передать объект класса Publisher и Robotrade Api
     *
     * @param Publisher $publisher Gate Publisher
     * @param Api $robotrade_api Api create message to send command to Gate
     * @param int $sleep Sleep between gate commands
     */
    public function __construct(Publisher $publisher, Api $robotrade_api, int $sleep = 0)
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

        try {

            $mes = $this->robotrade_api->cancelAllOrders($message);

            try {

                $code = $this->publisher->offer($mes);

                if ($code <= 0)
                    Storage::recordLog('Aeron to gate server code is: '. $code, ['$mes' => $mes]);

            } catch (Exception $e) {

                Storage::recordLog('Gate.php Aeron made a fatal error', ['$mes' => $mes, '$e->getMessage()' => $e->getMessage()]);

            }


        } catch (Exception $e) {

            echo '[' . date('Y-m-d H:i:s') . '] Gate()->cancelAllOrders() Throw Exception: ' . $e->getMessage() . PHP_EOL;

        }

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

        try {

            $mes = $this->robotrade_api->getBalances($assets);

            try {

                $code = $this->publisher->offer($mes);

                if ($code <= 0)
                    Storage::recordLog('Aeron to gate server code is: '. $code, ['$mes' => $mes]);

            } catch (Exception $e) {

                Storage::recordLog('Gate.php Aeron made a fatal error', ['$mes' => $mes, '$e->getMessage()' => $e->getMessage()]);

            }

        } catch (Exception $e) {

            echo '[' . date('Y-m-d H:i:s') . '] Gate()->getBalances() Throw Exception: ' . $e->getMessage() . PHP_EOL;

        }

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

        $this->commands = [];

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