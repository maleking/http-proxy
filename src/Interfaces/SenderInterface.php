<?php

namespace Akrez\HttpProxy\Interfaces;

use Psr\Http\Message\RequestInterface;

interface SenderInterface
{
    public function emitRequest(RequestInterface $requestInterface);
}
