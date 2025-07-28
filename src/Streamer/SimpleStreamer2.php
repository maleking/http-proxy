<?php

namespace Akrez\HttpProxy\Streamer;

use Exception;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SimpleStreamer
{
    public function emit(ServerRequestInterface $newServerRequest, $timeout = null): ResponseInterface|Exception
    {
        if ($newServerRequest->hasHeader('Accept-Encoding')) {
            $newServerRequest = $newServerRequest
                ->withoutHeader('Accept-Encoding')
                ->withHeader('Accept-Encoding', 'gzip');
        }
        $newServerRequest = $newServerRequest->withoutHeader('referer');

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
            $response = $client->send($newServerRequest);

            return $response
                // ->withoutHeader('Content-Length')
                ->withoutHeader('Connection')
                ->withoutHeader('Content-Encoding')
                ->withoutHeader('Keep-Alive')
                ->withoutHeader('Transfer-Encoding');
        } catch (Exception $e) {
            return $e;
        }
    }
}
