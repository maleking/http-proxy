<?php

namespace Akrez\HttpProxy\Streamer;

use Akrez\HttpRunner\SapiEmitter;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;

class SimpleStreamer implements StreamInterface
{
    use StreamDecoratorTrait;

    private string $filename;

    private string $mode;

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

    public function emit($request, $timeout = null): null|Exception|Throwable
    {
        ini_set('output_buffering', 'Off');
        ini_set('output_handler', '');
        ini_set('zlib.output_compression', 0);

        $clientConfig = [
            'verify' => false,
            'allow_redirects' => false,
            'referer' => false,
            'decode_content' => false,
            'http_errors' => false,
            'sink' => $this,
            'on_headers' => fn (ResponseInterface $response) => $this->onHeaders($response),
        ];
        if ($timeout !== null) {
            $clientConfig += [
                'timeout' => $timeout,
                'connect_timeout' => $timeout,
                'read_timeout' => $timeout,
            ];
        }

        $client = new Client($clientConfig);

        try {
            $client->send($request);
        } catch (Throwable $e) {
            return $e;
        } catch (Exception $e) {
            return $e;
        }

        return null;
    }

    public function onHeaders(ResponseInterface $response)
    {
        (new SapiEmitter)->emit($response, true);
    }
}
