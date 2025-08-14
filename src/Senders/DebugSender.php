<?php

namespace Akrez\HttpProxy\Senders;

use Akrez\HttpProxy\Interfaces\SenderInterface;
use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\RequestInterface;

class DebugSender implements SenderInterface
{
    public function emit(RequestInterface $newRequest)
    {
        echo nl2br(Message::toString($newRequest));
    }
}
