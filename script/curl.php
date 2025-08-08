<?php

use Akrez\HttpProxy\RequestFactory;
use Akrez\HttpProxy\Senders\CurlSender;
use GuzzleHttp\Psr7\ServerRequest;

require_once '../vendor/autoload.php';

$serverRequest = ServerRequest::fromGlobals();
$newServerRequest = RequestFactory::fromServerRequest($serverRequest);
if ($newServerRequest) {
    (new CurlSender)->emit($newServerRequest);
}
