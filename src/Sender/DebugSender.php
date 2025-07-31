<?php

namespace Akrez\HttpProxy\Sender;

use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\ServerRequestInterface;

class DebugSender
{
    public function emit(ServerRequestInterface $newServerRequest, $timeout = null)
    {
        exit(nl2br(Message::toString($newServerRequest)));
    }
}
