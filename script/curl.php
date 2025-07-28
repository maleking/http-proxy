<?php

use Akrez\HttpProxy\RequestFactory;
use Akrez\HttpProxy\Sender\CurlSender;
use GuzzleHttp\Psr7\ServerRequest;

require_once '../vendor/autoload.php';

$serverRequest = ServerRequest::fromGlobals();
$requestFactory = RequestFactory::makeByServerRequest($serverRequest);
if ($requestFactory) {
    (new CurlSender)->emit($requestFactory->getNewServerRequest());
}
