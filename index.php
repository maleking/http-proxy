<?php

use GuzzleHttp\Psr7\ServerRequest;

require_once './vendor/autoload.php';

function handle(
    $factory = 'InlineFactory',
    $sender = 'CurlSender',
    $debug = false
) {
    $factoryClassName = "Akrez\\HttpProxy\\Factories\\{$factory}";
    $senderClassName = "Akrez\\HttpProxy\\Senders\\{$sender}";
    //
    $serverRequest = ServerRequest::fromGlobals();
    //
    $newRequest = $factoryClassName::make($serverRequest);
    if ($newRequest) {
        return (new $senderClassName)->setDebug($debug)->emit($newRequest);
    }
}

handle();
