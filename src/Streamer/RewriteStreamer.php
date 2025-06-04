<?php

namespace Akrez\HttpProxy\Streamer;

use GuzzleHttp\Psr7\Utils;
use Akrez\HttpRunner\SapiEmitter;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Akrez\HttpProxy\Rewriters\TextCssRewriter;
use Akrez\HttpProxy\Rewriters\TextHtmlRewriter;

class RewriteStreamer implements StreamInterface
{
    use StreamDecoratorTrait;

    private $contentType;

    private $rewriter = null;

    private string $filename;

    private string $mode;

    private ?StreamInterface $stream;

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
        $this->rewriter = $this->detectRewriter($response);

        // write cookie
        // rewrite location header

        if ($this->rewriter) {
            $response->withoutHeader('content-length');
            $this->filename = "php://temp";
        }else{
            (new SapiEmitter)->emit($response, true);
        }
    }

    public function detectRewriter(ResponseInterface $response)
    {
        foreach ([
            TextHtmlRewriter::class,
            TextCssRewriter::class,
        ] as $rewriterClass) {
            if ($rewriterClass::isMyContentType($response)) {
                return new $rewriterClass($response);
            }
        }

        return null;
    }
}
