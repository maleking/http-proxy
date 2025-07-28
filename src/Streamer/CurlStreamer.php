<?php

namespace Akrez\HttpProxy\Streamer;

use Psr\Http\Message\ServerRequestInterface;

class CurlStreamer
{
    public $bufferSize = 128;

    public function emit(ServerRequestInterface $newServerRequest, $timeout = null)
    {
        if ($newServerRequest->hasHeader('Accept-Encoding')) {
            $newServerRequest = $newServerRequest->withoutHeader('Accept-Encoding')
                ->withHeader('Accept-Encoding', 'identity');
        }
        $newServerRequest = $newServerRequest->withoutHeader('referer');

        $options = [
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,

            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,

            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,

            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_AUTOREFERER => false,

            CURLOPT_HEADERFUNCTION => [$this, 'headerFunction'],
            CURLOPT_WRITEFUNCTION => [$this, 'writeFunction'],

            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,

            CURLOPT_BUFFERSIZE=> $this->bufferSize,

            CURLOPT_URL => (string) $newServerRequest->getUri(),
            CURLOPT_CUSTOMREQUEST => $newServerRequest->getMethod(),
            CURLOPT_POSTFIELDS => (string) $newServerRequest->getBody(),
        ];

        $headers = [];
        foreach ($newServerRequest->getHeaders() as $name => $values) {
            $headers[] = $name.': '.implode(', ', $values);
        }
        $options[CURLOPT_HTTPHEADER] = $headers;

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        @curl_exec($ch);
        curl_close($ch);

        return true;
    }

    private function headerFunction($ch, $headers)
    {
        $parts = explode(':', $headers, 2);

        if ((count($parts) == 2) && in_array($parts[0], [
            'content-length',
            'transfer-encoding',
        ])) {
            // do nothing
        } else {
            header($headers);
        }

        return strlen($headers);
    }

    private function writeFunction($ch, $str)
    {
        $len = strlen($str);

        echo $str;
        flush();

        return $len;
    }
}
