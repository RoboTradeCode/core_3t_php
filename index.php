<?php

require __DIR__ . '/vendor/autoload.php';

function sendHttpLog(string $message): bool|string
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://logger.robotrade.io");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "message=$message");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $output = curl_exec($ch);
    curl_close($ch);

    return $output;
}
