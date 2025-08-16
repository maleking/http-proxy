<?php

use Akrez\HttpProxy\CurlSender;
use Akrez\HttpProxy\Factories\InbodyFactory;
use Akrez\HttpProxy\Factories\InlineFactory;
use GuzzleHttp\Psr7\ServerRequest;

require_once './vendor/autoload.php';

function inline()
{
    $serverRequest = ServerRequest::fromGlobals();
    $factory = new InlineFactory($serverRequest);
    $newRequest = $factory->make();
    if ($newRequest) {
        (new CurlSender)->setDebug($factory->debug())->emit($newRequest);
    }
}

function inbody()
{
    $serverRequest = ServerRequest::fromGlobals();
    $factory = new InbodyFactory($serverRequest);
    $newRequest = $factory->make();
    if ($newRequest) {
        (new CurlSender)->setDebug($factory->debug())->emit($newRequest);
    }
}
