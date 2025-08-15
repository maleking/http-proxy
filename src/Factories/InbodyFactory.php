<?php

namespace Akrez\HttpProxy\Factories;

use Exception;
use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

class InbodyFactory
{
    public static function make(ServerRequestInterface $globalServerRequest, string $scheme): ?RequestInterface
    {
        try {
            $newServerRequest = Message::parseRequest((string) $globalServerRequest->getBody());

            $uri = $newServerRequest->getUri();
            $uri = $uri->withScheme($scheme);
            $newServerRequest = $newServerRequest->withUri($uri);

            return $newServerRequest;
        } catch (Exception $e) {
            return null;
        }
    }
}
