<?php

// Название алгоритма
const ALGORITHM = 'test_php';

// Биржа, которая тестируется гейтом
const EXCHANGE = 'ftx';

// Тестируемая пара для проверки корректности постановки оредров и их отмены (Важно: пока только BTC/USDT)
const SYMBOL = 'BTC/USDT';

// publisher, который подключается к subscriber в гейте, для посылания команд
const GATE_PUBLISHER = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1001
];

// subscriber, который подключается к publisher в гейте, для принятия ордербуков
const GATE_SUBSCRIBERS_ORDERBOOKS = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1001
];

// subscriber, который подключается к publisher в гейте, для принятия балансов
const GATE_SUBSCRIBERS_BALANCES = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1002
];

// subscriber, который подключается к publisher в гейте, для принятия ордеров
const GATE_SUBSCRIBERS_ORDERS = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1003
];
