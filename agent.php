<?php

use Akrez\HttpRunner\Agent;
use GuzzleHttp\Psr7\ServerRequest;

require_once './vendor/autoload.php';

Agent::new(ServerRequest::fromGlobals())?->emit(300);
