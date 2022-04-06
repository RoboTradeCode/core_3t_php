<?php

use Src\Aeron;

require dirname(__DIR__) . '/index.php';

$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

function handler(string $message)
{

    global $memcached;

    $data = Aeron::messageDecode($message);

    if ($data && $data['event'] == 'data') {

        $memcached->set(
            $data['key'],
            $data['data'],
            0,
            0
        );

        echo  $data['timestamp'] . ' [OK] Data was saved to memcache with key ' . $data['key'] . PHP_EOL;

    } else
        echo '[ERROR] data broken' . PHP_EOL;

}

$local_ip = getHostByName(getHostName());

$publisher_port = AERON_PUBLISHER_PORT;
$subscriber_port = AERON_SUBSCRIBER_PORT;

$db = (new DB())->connectToDB(MYSQL_HOST, MYSQL_PORT, MYSQL_DB, MYSQL_USER, MYSQL_PASSWORD);

/* Select servers list from DB */
$sth = $db->prepare("SELECT * FROM `servers_list` WHERE `role` = 'trade_server'");
$sth->execute();

$servers_list = [];

while ($row = $sth->fetch(PDO::FETCH_ASSOC)) $servers_list[] = $row;

$subscriber = new AeronSubscriber('handler', 'aeron:udp?control-mode=manual');

$subscriber->addDestination("aeron:udp?endpoint=$local_ip:$subscriber_port|control=$local_ip:$publisher_port");

foreach ($servers_list as $server) {
    if ($server['private_ip'] !== $local_ip) {
        $subscriber_port++;
        $subscriber->addDestination("aeron:udp?endpoint=$local_ip:$subscriber_port|control={$server['external_ip']}:$publisher_port");
    }
}

while (true) {
    $subscriber->poll();
    usleep(10);
}