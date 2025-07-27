<?php

use Akrez\HttpProxy\RequestFactory;
use Akrez\HttpProxy\Streamer\RewriteStreamer;
use Akrez\HttpProxy\Streamer\SimpleStreamer;
use Akrez\HttpProxy\Support\RewriteCookie;
use Akrez\HttpProxy\Support\RewriteCrypt;
use Akrez\HttpRunner\SapiEmitter;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;

require_once './vendor/autoload.php';

$serverRequest = ServerRequest::fromGlobals();
$requestFactory = RequestFactory::makeByServerRequest($serverRequest);
if ($requestFactory) {
    if ($requestFactory->getState() === RequestFactory::STATE_DEBUG) {
        $error = new Exception(nl2br(Message::toString($requestFactory->getNewServerRequest())));
    } elseif ($requestFactory->getState() === RequestFactory::STATE_SIMPLE) {
        $streamer = new SimpleStreamer('php://output', 'w+');
        $error = $streamer->emit($requestFactory->getNewServerRequest());
    } elseif ($requestFactory->getState() === RequestFactory::STATE_REWRITE) {
        $rewriteCrypt = new RewriteCrypt($serverRequest);
        $rewriteCookie = new RewriteCookie($serverRequest, 'PC');
        $streamer = new RewriteStreamer($rewriteCrypt, $rewriteCookie, 'php://output', 'w+');
        $error = $streamer->emit($requestFactory->getNewServerRequest());
    } else {
        $error = null;
    }
    //
    if ($error) {
        (new SapiEmitter)->emit(new Response(500, [], $error->getMessage()));
    }
}
