<?php

namespace Akrez\HttpProxy\Senders;

use Akrez\HttpProxy\Interfaces\SenderInterface;
use Psr\Http\Message\RequestInterface;

class ConnectSender implements SenderInterface
{
    public function emit(RequestInterface $newRequest)
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 200,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
