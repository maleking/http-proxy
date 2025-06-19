<?php

namespace Akrez\HttpProxy\Support;

use Akrez\HttpProxy\RequestFactory;
use League\Uri\Uri;
use Psr\Http\Message\RequestInterface;

class RewriteCrypt
{
    public function __construct(
        protected RequestInterface $serverRequest
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

            return static::suggestBaseUrl().'/'.$newUrlString;

        } catch (\Throwable $th) {
            return $urlString;
        }

        return $url->toString();
    }

    public static function suggestBaseUrl(): string
    {
        return static::trim($_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']);
    }

    public static function trim(string $url)
    {
        return trim($url, " \n\r\t\v\0/");
    }
}
