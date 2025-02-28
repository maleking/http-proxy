<?php

use Akrez\HttpProxy\AgentFactory;
use GuzzleHttp\Psr7\ServerRequest;

require_once './vendor/autoload.php';

$serverRequest = ServerRequest::fromGlobals();

$agent = AgentFactory::byServerRequest($serverRequest);
if ($agent) {
    $agent->emit(300);
}
