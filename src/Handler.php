<?php

namespace Akrez\HttpProxy;

use Akrez\HttpProxy\Factories\InbodyFactory;
use Akrez\HttpProxy\Factories\InlineFactory;
use GuzzleHttp\Psr7\ServerRequest;

class Handler
{
    public static function emitInlineCurl(): bool
    {
        $serverRequest = ServerRequest::fromGlobals();
        $factory = new InlineFactory($serverRequest);
        $newRequest = $factory->make();
        if ($newRequest) {
            (new CurlSender)->setDebug($factory->debug())->emit($newRequest);
        }

        return boolval($newRequest);
    }

    public static function emitInbodyCurl(): bool
    {
        $serverRequest = ServerRequest::fromGlobals();
        $factory = new InbodyFactory($serverRequest);
        $newRequest = $factory->make();
        if ($newRequest) {
            (new CurlSender)->setDebug($factory->debug())->emit($newRequest);
        }

        return boolval($newRequest);
    }
}
