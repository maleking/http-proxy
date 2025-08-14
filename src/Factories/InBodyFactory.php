<?php

namespace Akrez\HttpProxy\Factories;

use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

class InBodyFactory
{
    public static function fromServerRequest(ServerRequestInterface $globalServerRequest): ?RequestInterface
    {
        return Message::parseRequest((string) $globalServerRequest->getBody());
    }
}
