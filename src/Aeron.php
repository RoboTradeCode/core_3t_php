<?php

namespace Src;

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

}