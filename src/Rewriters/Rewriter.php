<?php

namespace Akrez\HttpProxy\Rewriters;

use Akrez\HttpProxy\Streamer\RewriteStreamer;
use Psr\Http\Message\ResponseInterface;

abstract class Rewriter
{
    public function __construct(protected RewriteStreamer $rewriteStreamer) {}

    abstract public function convert($body, $mainPageUrl);

    abstract public function isMine($response);

    public function encryptUrl(string $urlString, ?string $mainUrlString = null)
    {
        return $this->rewriteStreamer->rewriteCrypt->encryptUrl($urlString, $mainUrlString);
    }

    public static function isContentType(string $contentType, ResponseInterface $response)
    {
        $contentTypes = (array) $response->getHeader('Content-Type');

        return $contentType === trim(preg_replace('@;.*@', '', reset($contentTypes)));
    }

    public static function trim(string $url)
    {
        return trim($url, " \n\r\t\v\0/");
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
