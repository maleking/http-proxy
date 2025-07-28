<?php

use Akrez\HttpProxy\RequestFactory;
use Akrez\HttpProxy\Streamer\CurlStreamer;
use GuzzleHttp\Psr7\ServerRequest;

require_once '../vendor/autoload.php';

$serverRequest = ServerRequest::fromGlobals();
$requestFactory = RequestFactory::makeByServerRequest($serverRequest);
if ($requestFactory) {
    (new CurlStreamer)->emit($requestFactory->getNewServerRequest());
}
