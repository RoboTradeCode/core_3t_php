<?php

// publisher, который подключается к subscriber в гейте, для посылания команд
const GATE_PUBLISHER = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1001
];

// subscriber, который подключается к publisher в гейте, для принятия данных
const GATE_SUBSCRIBERS_ORDERBOOKS = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1001
];

const GATE_SUBSCRIBERS_BALANCES = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1002
];

const GATE_SUBSCRIBERS_ORDERS = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1003
];
