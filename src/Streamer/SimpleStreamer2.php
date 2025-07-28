<?php

namespace Akrez\HttpProxy\Streamer;

use Akrez\HttpRunner\SapiEmitter;
use Exception;
use GuzzleHttp\Client;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class SimpleStreamer
{
    public function __construct(string $filename, string $mode) {}

    public function emit(ServerRequestInterface $request, $timeout = null): null|Exception|Throwable
    {
        $acceptEncoding = $request->getHeaderLine('Accept-Encoding');
        if ($acceptEncoding) {
            $request = $request
                ->withoutHeader('Accept-Encoding')
                ->withHeader('Accept-Encoding', 'gzip');
        }

        $clientConfig = [
            'verify' => false,
            'allow_redirects' => false,
            'referer' => false,
            'decode_content' => false,
            'http_errors' => false,
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
            $response = $client->send($request);
            $response = $response
                // ->withoutHeader('Content-Length')
                ->withoutHeader('Connection')
                ->withoutHeader('Keep-Alive')
                ->withoutHeader('Transfer-Encoding');
            (new SapiEmitter)->emit($response);
        } catch (Throwable $e) {
            return $e;
        } catch (Exception $e) {
            return $e;
        }

        return null;
    }
}
