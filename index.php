<?php

use Akrez\HttpProxy\Agent;
use Akrez\HttpProxy\SimpleStreamer;
use Akrez\HttpRunner\SapiEmitter;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;

require_once './vendor/autoload.php';

$serverRequest = ServerRequest::fromGlobals();

$agent = Agent::makeByServerRequest($serverRequest);
if ($agent) {
    if ($agent->getState() === Agent::STATE_DEBUG) {
        (new SapiEmitter)->emit(new Response(200, [], Message::toString($agent->getRequest())));
    } else {
        $streamer = new SimpleStreamer('php://output', 'w+');
        $exception = $agent->emit($streamer, 3);
        if ($exception) {
            (new SapiEmitter)->emit(new Response(500, [], $exception->getMessage()));
        }
    }
}
