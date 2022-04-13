<?php

// publisher, который подключается к subscriber в гейте, для посылания команд
const GATE_PUBLISHER = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1001
];

// subscriber, который подключается к publisher в гейте, для принятия данных
const GATE_SUBSCRIBER = [
    'channel' => 'aeron:ipc',
    'stream_id' => 1001
];
