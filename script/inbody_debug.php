<?php

use Akrez\HttpProxy\Factories\InBodyFactory;
use Akrez\HttpProxy\Senders\DebugSender;
use GuzzleHttp\Psr7\ServerRequest;

require_once '../vendor/autoload.php';

$serverRequest = ServerRequest::fromGlobals();
$newServerRequest = InBodyFactory::fromServerRequest($serverRequest);
if ($newServerRequest) {
    (new DebugSender)->emit($newServerRequest);
}
