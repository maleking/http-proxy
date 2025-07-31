<?php

use Akrez\HttpProxy\RequestFactory;
use Akrez\HttpProxy\Sender\RewriteSender;
use Akrez\HttpProxy\Support\RewriteCookie;
use Akrez\HttpProxy\Support\RewriteCrypt;
use GuzzleHttp\Psr7\ServerRequest;

require_once './../agent.core/vendor/autoload.php';

$serverRequest = ServerRequest::fromGlobals();
$requestFactory = RequestFactory::makeByServerRequest($serverRequest);
if ($requestFactory) {
    $rewriteCrypt = new RewriteCrypt($serverRequest);
    $rewriteCookie = new RewriteCookie($serverRequest, 'PC');
    (new RewriteSender($rewriteCrypt, $rewriteCookie, 'php://output', 'w+'))->emit($requestFactory->getNewServerRequest());
}
