<?php

namespace Akrez\HttpProxy\Rewriters;

class TextCssRewriter extends Rewriter
{
    public function getMyContentType()
    {
        return 'text/css';
    }

    public function convert($body, $mainPageUrl)
    {
        $body = preg_replace_callback('/@import\s+([\'"])(.*?)\1(?![^;]*url)/ix', function ($matches) use ($mainPageUrl) {
            $url = trim($matches[2]);
            $changed = $this->encryptUrl($url, $mainPageUrl);

            return str_replace($url, $changed, $matches[0]);
        }, $body);

        $body = preg_replace_callback('/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/ix', function ($matches) use ($mainPageUrl) {
            $url = trim($matches[2]);
            $changed = $this->encryptUrl($url, $mainPageUrl);

            return str_replace($url, $changed, $matches[0]);
        }, $body);

        return $body;
    }
}
