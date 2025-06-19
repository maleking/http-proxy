<?php

namespace Akrez\HttpProxy\Support;

use Akrez\HttpProxy\RequestFactory;
use GuzzleHttp\Psr7\ServerRequest;
use League\Uri\Uri;

class RewriteCrypt
{
    public function __construct(
        protected ServerRequest $serverRequest
    ) {}

    public function encryptUrl(string $urlString, ?string $mainUrlString = null)
    {
        try {

            if ($mainUrlString) {
                $url = Uri::fromBaseUri($urlString, $mainUrlString);
            } else {
                $url = Uri::new($urlString);
            }

            $newUrlString = $url->toString();
            if (strpos($newUrlString, 'https://') === 0) {
                $newUrlString = substr_replace($newUrlString, RequestFactory::STATE_REWRITE.'_https/', 0, strlen('https://'));
            }
            if (strpos($newUrlString, 'http://') === 0) {
                $newUrlString = substr_replace($newUrlString, RequestFactory::STATE_REWRITE.'_http/', 0, strlen('http://'));
            }

            return $this->suggestBaseUrl().'/'.$newUrlString;

        } catch (\Throwable $th) {
            return $urlString;
        }

        return $url->toString();
    }

    public function suggestBaseUrl(): string
    {
        $serverParams = $this->serverRequest->getServerParams() + [
            'REQUEST_SCHEME' => null,
            'HTTP_HOST' => null,
            'SCRIPT_NAME' => null,
        ];

        return $serverParams['REQUEST_SCHEME'].'://'.$serverParams['HTTP_HOST'].$serverParams['SCRIPT_NAME'];
    }
}
