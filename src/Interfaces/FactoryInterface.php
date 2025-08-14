<?php

namespace Akrez\HttpProxy\Interfaces;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

interface FactoryInterface
{
    public static function make(ServerRequestInterface $globalServerRequest): ?RequestInterface;
}
