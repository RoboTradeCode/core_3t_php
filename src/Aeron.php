<?php

namespace Src;

use Aeron\Publisher;
use Exception;

class Aeron
{

    public static function messageEncode(mixed $message): bool|string
    {

        return json_encode($message);

    }

    public static function messageDecode(string $message)
    {

        return json_decode($message, true);

    }

    public static function checkConnection(Publisher $publisher): void
    {

        do {

            try {

                $publisher->offer('ping');

                $do = false;

            } catch (Exception) {

                $do = true;

                echo '[' . date('Y-m-d H:i:s') . '] Try to connect Aeron' . PHP_EOL;

                sleep(1);

            }

        } while ($do);

    }

}