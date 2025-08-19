<?php

namespace Akrez\HttpProxy;

use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\RequestInterface;

abstract class Sender
{
    protected ?int $timeout = null;

    protected int $bufferSize = 128;

    protected bool $debug = false;

    public function setTimeout(?int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function setBufferSize(?int $bufferSize): self
    {
        $this->bufferSize = $bufferSize;

        return $this;
    }

    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;

        return $this;
    }

    public function emit(RequestInterface $newRequest)
    {
        $newRequest = $newRequest
            ->withoutHeader('Accept-Encoding')
            ->withHeader('Accept-Encoding', 'identity');

        if ($this->debug) {
            echo nl2br(Message::toString($newRequest));
        } elseif ($newRequest->getMethod() === 'CONNECT') {
            header('Content-Type: application/json; charset=utf-8', true, 200);
            echo json_encode([
                'status' => 200,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $this->emitRequest($newRequest);
        }
    }

    abstract protected function emitRequest(RequestInterface $newRequest);
}
