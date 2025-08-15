<?php

namespace Akrez\HttpProxy\Rewriters;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class TextCssRewriter extends Rewriter
{
    public function isMine(RequestInterface $newRequest, ResponseInterface $response)
    {
        return $this->isContentType('text/css', $response);
    }

    public function convert($content, $mainPageUrl)
    {
        $content = preg_replace_callback('/@import\s+([\'"])(.*?)\1(?![^;]*url)/ix', function ($matches) use ($mainPageUrl) {
            $url = trim($matches[2]);
            $changed = $this->encryptUrl($url, $mainPageUrl);

            return str_replace($url, $changed, $matches[0]);
        }, $content);

        $content = preg_replace_callback('/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/ix', function ($matches) use ($mainPageUrl) {
            $url = trim($matches[2]);
            $changed = $this->encryptUrl($url, $mainPageUrl);

            return str_replace($url, $changed, $matches[0]);
        }, $content);

        return $content;
    }
}
