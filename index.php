<?php

use Akrez\HttpProxy\CurlSender;
use GuzzleHttp\Psr7\ServerRequest;

require_once './vendor/autoload.php';

function handle($mode, $debug = false)
{
    if ($mode == 'inbody') {
        $factoryClassName = 'InbodyFactory';
    } else {
        $factoryClassName = 'InlineFactory';
    }
    //
    $serverRequest = ServerRequest::fromGlobals();
    //
    $newRequest = "Akrez\\HttpProxy\\Factories\\{$factoryClassName}"::make($serverRequest);
    //
    $sender = new CurlSender;
    //
    if ($newRequest) {
        return $sender->setDebug($debug)->emit($newRequest);
    }
}
