<?php

use Akrez\HttpProxy\Factories\InLineFactory;
use Akrez\HttpProxy\Senders\DebugSender;
use GuzzleHttp\Psr7\ServerRequest;

require_once '../vendor/autoload.php';

$serverRequest = ServerRequest::fromGlobals();
$newServerRequest = InLineFactory::fromServerRequest($serverRequest);
if ($newServerRequest) {
    (new DebugSender)->emit($newServerRequest);
}
