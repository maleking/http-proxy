<?php

namespace Akrez\HttpProxy;

use Akrez\HttpRunner\SapiEmitter;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class SimpleStreamer implements StreamInterface
{
    use StreamDecoratorTrait;

    private ?string $filename;

    private ?string $mode;

    private ?StreamInterface $stream;

    public function __construct(string $filename, string $mode)
    {
        $this->filename = $filename;
        $this->mode = $mode;

        // unsetting the property forces the first access to go through
        // __get().
        unset($this->stream);
    }

    protected function createStream(): StreamInterface
    {
        return Utils::streamFor(Utils::tryFopen($this->filename, $this->mode));
    }

    public function emitHeaders(ResponseInterface $response)
    {
        (new SapiEmitter)->emit($response, true);
    }
}
