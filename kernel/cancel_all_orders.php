<?php

use Src\ApiV2;
use Src\Configurator;

require dirname(__DIR__) . '/index.php';

$config = Configurator::getConfigApiByFile('3m_best_place');

$core_config = $config['configs']['core_config'];
$node = $core_config['node'];
$exchange = $core_config['exchange'];
$algorithm = $core_config['algorithm'];
$instance = $core_config['instance'];
$publishers = $core_config['aeron']['publishers'];

$api = new ApiV2($exchange, $algorithm, $node, $instance, $publishers);

$api->cancelAllOrders();

$api->getBalances();

echo '[' . date('Y-m-d H:i:s') . '] All Orders Send To Cancel' . PHP_EOL;
