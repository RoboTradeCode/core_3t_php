<?php

require __DIR__ . '/vendor/autoload.php';

function debug(mixed $arr, string $name = '', $die = false): void
{

    if ($name)
        echo '<hr> [' . date('Y-m-d H:i:s') . '] ' . $name . PHP_EOL;

    echo '<pre>' . print_r($arr, true) . '</pre>';

    if($die) die;

}

