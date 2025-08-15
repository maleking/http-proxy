<?php

use Akrez\HttpProxy\Senders\CurlSender;
use Akrez\HttpProxy\Senders\RewriteSender;
use GuzzleHttp\Psr7\ServerRequest;

require_once './vendor/autoload.php';

function handle($mode, $debug = false)
{
    if ($mode == 'inbody') {
        $factoryClassName = 'InbodyFactory';
        $senderClassName = 'CurlSender';
    } elseif ($mode == 'rewrite') {
        $factoryClassName = 'InlineFactory';
        $senderClassName = 'RewriteSender';
    } else {
        $factoryClassName = 'InlineFactory';
        $senderClassName = 'CurlSender';
    }
    //
    $serverRequest = ServerRequest::fromGlobals();
    //
    $newRequest = "Akrez\\HttpProxy\\Factories\\{$factoryClassName}"::make($serverRequest);
    //
    if ($senderClassName == 'RewriteSender') {
        $sender = new RewriteSender($serverRequest);
    } else {
        $sender = new CurlSender;
    }
    //
    if ($newRequest) {
        return $sender->setDebug($debug)->emit($newRequest);
    }
}

handle('rewrite');
