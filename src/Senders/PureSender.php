<?php

namespace Akrez\HttpProxy\Senders;

use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;

class PureSender extends Sender
{
    public function emitRequest(RequestInterface $newRequest)
    {
        $clientConfig = [
            'verify' => false,
            'allow_redirects' => false,
            'referer' => false,
            'decode_content' => false,
            'http_errors' => false,
        ];
        if ($this->getTimeout() !== null) {
            $clientConfig += [
                'timeout' => $this->getTimeout(),
                'connect_timeout' => $this->getTimeout(),
                'read_timeout' => $this->getTimeout(),
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
            echo $body->read($this->getBufferSize());
            flush();
        }
    }
}
