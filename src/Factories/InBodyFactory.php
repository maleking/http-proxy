<?php

namespace Akrez\HttpProxy\Factories;

use Akrez\HttpProxy\Interfaces\FactoryInterface;
use Exception;
use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

class InbodyFactory implements FactoryInterface
{
    public static function make(ServerRequestInterface $globalServerRequest): ?RequestInterface
    {
        try {
            return Message::parseRequest((string) $globalServerRequest->getBody());
        } catch (Exception $e) {
            return null;
        }
    }
}
