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
$requestFactory = new RequestFactory($serverRequest);
if ($requestFactory->isSuccessful()) {
    if ($requestFactory->getState() === RequestFactory::STATE_DEBUG) {
        $error = new Exception(nl2br(Message::toString($requestFactory->getRequest())));
    } elseif ($requestFactory->getState() === RequestFactory::STATE_SIMPLE) {
        $streamer = new SimpleStreamer('php://output', 'w+');
        $error = $streamer->emit($requestFactory->getRequest());
    } elseif ($requestFactory->getState() === RequestFactory::STATE_REWRITE) {
        $rewriteCrypt = new RewriteCrypt($serverRequest);
        $rewriteCookie = new RewriteCookie('PC');
        $streamer = new RewriteStreamer($rewriteCrypt,$rewriteCookie, 'php://output', 'w+');
        $error = $streamer->emit($requestFactory->getRequest());
    } else {
        $error = null;
    }
    //
    if ($error) {
        (new SapiEmitter)->emit(new Response(500, [], $error->getMessage()));
    }
}
