<?php

namespace Akrez\HttpProxy\Sender;

use Psr\Http\Message\ServerRequestInterface;

class CurlSender
{
    public $bufferSize = 128;

    public function emit(ServerRequestInterface $newServerRequest, $timeout = null)
    {
        if ($newServerRequest->hasHeader('Accept-Encoding')) {
            $newServerRequest = $newServerRequest->withoutHeader('Accept-Encoding')
                ->withHeader('Accept-Encoding', 'identity');
        }
        $newServerRequest = $newServerRequest->withoutHeader('referer');

        $url = (string) $newServerRequest->getUri();

        $headers = [];
        foreach ($newServerRequest->getHeaders() as $name => $values) {
            $headers[] = $name.': '.implode(', ', $values);
        }

        $options = [
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,

            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,

            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,

            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_AUTOREFERER => false,

            CURLOPT_HEADERFUNCTION => [$this, 'headerCallback'],
            CURLOPT_WRITEFUNCTION => [$this, 'writeCallback'],

            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,

            CURLOPT_BUFFERSIZE => $this->bufferSize,

            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $newServerRequest->getMethod(),
            CURLOPT_POSTFIELDS => (string) $newServerRequest->getBody(),
            CURLOPT_HTTPHEADER => $headers,

            CURLOPT_HTTP_CONTENT_DECODING => false,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        curl_exec($ch);
        curl_close($ch);

        return true;
    }

    protected function headerCallback($ch, $headers)
    {
        $parts = explode(':', $headers, 2);

        if (count($parts) == 2) {
            if (in_array(strtolower($parts[0]), [
                // 'content-length',
                // 'content-encoding',
                'transfer-encoding',
                'keep-alive',
                'connection',
            ])) {
                // do nothing
            } else {
                header($headers, false);
            }
        } elseif (preg_match('/HTTP\/[\d.]+\s*(\d+)/', $headers, $matches)) {
            http_response_code($matches[1]);
        }

        return strlen($headers);
    }

    protected function writeCallback($ch, $str)
    {
        $len = strlen($str);

        echo $str;
        flush();

        return $len;
    }
}
