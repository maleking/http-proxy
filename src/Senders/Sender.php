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

    public function setTimeout(?int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function setBufferSize(?int $bufferSize): self
    {
        $this->bufferSize = $bufferSize;

        return $this;
    }

    public function getBufferSize(): ?int
    {
        return $this->bufferSize;
    }

    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;

        return $this;
    }

    public function getDebug(): bool
    {
        return $this->debug;
    }

    public function beforeSend(RequestInterface $newRequest)
    {
        return $newRequest
            ->withoutHeader('Accept-Encoding')
            ->withHeader('Accept-Encoding', 'identity');
    }

    public function emit(RequestInterface $newRequest)
    {
        $newRequest = $this->beforeSend($newRequest);

        if ($this->getDebug()) {
            $this->emitDebug($newRequest);

            return 'debug';
        }

        if ($newRequest->getMethod() === 'CONNECT') {
            $this->emitConnect($newRequest);

            return 'connect';
        }

        $this->emitRequest($newRequest);

        return 'request';
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
