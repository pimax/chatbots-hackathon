<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');

require 'vendor/autoload.php';
require 'app/App.php';
require 'app/models/Tour.php';
require 'app/models/Member.php';
require 'app/models/DepartureCity.php';
require 'app/models/Countries.php';
require 'app/models/Session.php';
require 'app/models/Subscription.php';

$app = new app\App();
$app->run();


function writeToLog($data, $title = '') {
    $log = "\n------------------------\n";
    $log .= date("Y.m.d G:i:s") . "\n";
    $log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
    $log .= print_r($data, 1);
    $log .= "\n------------------------\n";
    file_put_contents(__DIR__ . '/imbot.log', $log, FILE_APPEND);
    return true;
}