<?php

namespace Akrez\HttpProxy\Senders;

use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\RequestInterface;

class DebugSender
{
    public function emit(RequestInterface $newServerRequest, $timeout = null)
    {
        echo nl2br(Message::toString($newServerRequest));
    }
}
