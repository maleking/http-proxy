<?php

use Akrez\HttpProxy\AgentFactory;
use GuzzleHttp\Psr7\ServerRequest;

require_once './vendor/autoload.php';

AgentFactory::byServerRequest(ServerRequest::fromGlobals())?->emit(300);
