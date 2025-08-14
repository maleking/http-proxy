<?php

namespace Akrez\HttpProxy\Senders;

use Akrez\HttpProxy\Interfaces\SenderInterface;
use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;

class PureSender implements SenderInterface
{
    public $timeout = null;

    public $bufferSize = 128;

    public function emit(RequestInterface $newRequest)
    {
        $newRequest = $newRequest
            ->withoutHeader('Accept-Encoding')
            ->withHeader('Accept-Encoding', 'identity');

        $clientConfig = [
            'verify' => false,
            'allow_redirects' => false,
            'referer' => false,
            'decode_content' => false,
            'http_errors' => false,
        ];
        if ($this->timeout !== null) {
            $clientConfig += [
                'timeout' => $this->timeout,
                'connect_timeout' => $this->timeout,
                'read_timeout' => $this->timeout,
            ];
        }

        $client = new Client($clientConfig);

        $response = $client->send($newRequest);

        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $headerKey => $headers) {
            header($headerKey.': '.implode(', ', $headers), false);
        }

        $body = $response->getBody();
        while (! $body->eof()) {
            echo $body->read($this->bufferSize);
            flush();
        }
    }
}
