<?php

use Akrez\HttpProxy\RequestFactory;
use Akrez\HttpProxy\Senders\DebugSender;
use GuzzleHttp\Psr7\ServerRequest;

require_once '../vendor/autoload.php';

$serverRequest = ServerRequest::fromGlobals();
$newServerRequest = RequestFactory::fromServerRequest($serverRequest);
if ($newServerRequest) {
    (new DebugSender)->emit($newServerRequest);
}
