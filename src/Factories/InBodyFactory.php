<?php

namespace Akrez\HttpProxy\Factories;

use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class InBodyFactory
{
    public static function fromServerRequest(ServerRequestInterface $globalServerRequest): ?ServerRequestInterface
    {
        return Message::parseRequest((string) $globalServerRequest->getBody());
    }
}
