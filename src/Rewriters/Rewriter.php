<?php

namespace Akrez\HttpProxy\Rewriters;

use League\Uri\Uri;
use Psr\Http\Message\ResponseInterface;

abstract class Rewriter
{
    public function __construct() {}

    abstract public function convert($body, $mainPageUrl);

    abstract public function getMyContentType();

    public function isMyContentType(ResponseInterface $response)
    {
        $contentTypes = (array) $response->getHeader('Content-Type');

        return $this->getMyContentType() === trim(preg_replace('@;.*@', '', reset($contentTypes)));
    }

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
                $newUrlString = substr_replace($newUrlString, 'https/', 0, strlen('https://'));
            }
            if (strpos($newUrlString, 'http://') === 0) {
                $newUrlString = substr_replace($newUrlString, 'http/', 0, strlen('http://'));
            }

            return static::suggestBaseUrl().'/'.$newUrlString;

        } catch (\Throwable $th) {
            return $urlString;
        }

        return $url->toString();
    }

    public static function trim(string $url)
    {
        return trim($url, " \n\r\t\v\0/");
    }

    public static function suggestBaseUrl(): string
    {
        return static::trim($_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']);
    }

    public static function startsWith($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && stripos($haystack, $needle) === 0) {
                return true;
            }
        }

        return false;
    }
}
