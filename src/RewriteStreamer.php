<?php

namespace Akrez\HttpProxy;

use Akrez\HttpRunner\SapiEmitter;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class RewriteStreamer implements StreamInterface
{
    use StreamDecoratorTrait;

    private $contentType;

    private $filename;

    private $mode;

    private $stream;

    public function __construct(string $filename, string $mode)
    {
        $this->filename = $filename;
        $this->mode = $mode;

        unset($this->stream);
    }

    protected function createStream(): StreamInterface
    {
        return Utils::streamFor(Utils::tryFopen($this->filename, $this->mode));
    }

    public function emitHeaders(ResponseInterface $response)
    {
        $contentTypes = (array) $response->getHeader('Content-Type');

        $this->contentType = reset($contentTypes);

        if (in_array($this->contentType, ['text/html', 'text/css'])) {
            $this->filename = 'php://temp';
        } else {
            $this->filename = 'php://output';
        }

        (new SapiEmitter)->emit($response, true);
    }
}
