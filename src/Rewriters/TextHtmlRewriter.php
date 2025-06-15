<?php

namespace Akrez\HttpProxy\Rewriters;

class TextHtmlRewriter extends Rewriter
{
    public function getMyContentType()
    {
        return 'text/html';
    }

    public function convert($body, $mainPageUrl)
    {
        $body = preg_replace_callback('@(?:src|href)\s*=\s*(["|\'])(.*?)\1@is', function ($matches) use ($mainPageUrl) {
            $url = trim($matches[2]);
            $types = ['data:', 'magnet:', 'about:', 'javascript:', 'mailto:', 'tel:', 'ios-app:', 'android-app:'];
            if (static::startsWith($url, $types)) {
                return $matches[0];
            }
            $changed = $this->encryptUrl($url, $mainPageUrl);

            return str_replace($url, $changed, $matches[0]);
        }, $body);

        $body = preg_replace_callback('@<form[^>]*action=(["\'])(.*?)\1[^>]*>@i', function ($matches) use ($mainPageUrl) {
            $action = trim($matches[2]);
            if (! $action) {
                return '';
            }
            $changed = $this->encryptUrl($action, $mainPageUrl);

            return str_replace($action, $changed, $matches[0]);
        }, $body);

        $body = preg_replace_callback('/content=(["\'])\d+\s*;\s*url=(.*?)\1/is', function ($matches) use ($mainPageUrl) {
            $url = trim($matches[2]);
            $changed = $this->encryptUrl($url, $mainPageUrl);

            return str_replace($url, $changed, $matches[0]);
        }, $body);

        $body = preg_replace_callback('@[^a-z]{1}url\s*\((?:\'|"|)(.*?)(?:\'|"|)\)@im', function ($matches) use ($mainPageUrl) {
            $url = trim($matches[1]);
            if (static::startsWith($url, 'data:')) {
                return $matches[0];
            }
            $changed = $this->encryptUrl($url, $mainPageUrl);

            return str_replace($url, $changed, $matches[0]);
        }, $body);

        $body = preg_replace_callback('/srcset=\"(.*?)\"/i', function ($matches) use ($mainPageUrl) {
            $src = trim($matches[1]);
            $urls = preg_split('/\s*,\s*/', $src);
            foreach ($urls as $part) {
                $pos = strpos($part, ' ');
                if ($pos !== false) {
                    $url = substr($part, 0, $pos);

                    $changed = $this->encryptUrl($url, $mainPageUrl);
                    $src = str_replace($url, $changed, $src);
                }
            }

            return 'srcset="'.$src.'"';
        }, $body);

        return $body;
    }
}
