<?php

namespace Akrez\HttpProxy\Senders;

use Akrez\HttpProxy\Interfaces\SenderInterface;
use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\RequestInterface;

abstract class Sender implements SenderInterface
{
    protected ?int $timeout = null;

    protected int $bufferSize = 128;

    protected bool $debug = false;

    public function setTimeout(?int $timeout)
    {
        $this->timeout = $timeout;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function setBufferSize(?int $bufferSize)
    {
        $this->bufferSize = $bufferSize;
    }

    public function getBufferSize(): ?int
    {
        return $this->bufferSize;
    }

    public function setDebug(bool $debug)
    {
        $this->debug = $debug;
    }

    public function getDebug(): bool
    {
        return $this->debug;
    }

    public function emit(RequestInterface $newRequest)
    {
        if ($this->getDebug()) {
            $this->emitDebug($newRequest);
        } elseif ($newRequest->getMethod() === 'CONNECT') {
            $this->emitConnect($newRequest);
        } else {
            $this->emitRequest($newRequest);
        }
    }

    public function emitDebug(RequestInterface $newRequest)
    {
        echo nl2br(Message::toString($newRequest));
    }

    public function emitConnect(RequestInterface $newRequest)
    {
        header('Content-Type: application/json; charset=utf-8', true, 200);
        echo json_encode([
            'status' => 200,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
