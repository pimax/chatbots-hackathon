<?php

require 'vendor/autoload.php';
$config = include 'config.inc.php';

use Telegram\Bot\Api;

$telegram = new Api($config['token']);

$response = $telegram->setWebhook([
    'url' => $config['webhook_url'],
    'certificate' => $config['domain_certificate']
]);

echo '<pre>', print_r($response), '</pre>';