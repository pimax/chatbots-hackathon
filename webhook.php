<?php

require 'vendor/autoload.php';
require 'app/App.php';
require 'app/models/Tour.php';
require 'app/models/Member.php';
require 'app/models/DepartureCity.php';
require 'app/models/Subscription.php';

$app = new app\App($telegram);
$app->run($telegram->getWebhookUpdates());