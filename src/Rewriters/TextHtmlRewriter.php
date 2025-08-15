<?php

namespace Akrez\HttpProxy\Rewriters;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class TextHtmlRewriter extends Rewriter
{
    public function isMine(RequestInterface $newRequest, ResponseInterface $response)
    {
        return $this->isContentType('text/html', $response);
    }

    public function convert($content, $mainPageUrl)
    {
        $content = preg_replace_callback('@(?:src|href)\s*=\s*(["|\'])(.*?)\1@is', function ($matches) use ($mainPageUrl) {
            $url = trim($matches[2]);
            $types = ['data:', 'magnet:', 'about:', 'javascript:', 'mailto:', 'tel:', 'ios-app:', 'android-app:'];
            if (static::startsWith($url, $types)) {
                return $matches[0];
            }
            $changed = $this->encryptUrl($url, $mainPageUrl);

            return str_replace($url, $changed, $matches[0]);
        }, $content);

        $content = preg_replace_callback('@<script[^>]*src=(["\'])(.*?)\1[^>]*>@i', function ($matches) use ($mainPageUrl) {
            $action = trim($matches[2]);
            if (! $action) {
                return '';
            }
            $changed = $this->encryptUrl($action, $mainPageUrl);

            return str_replace($action, $changed, $matches[0]);
        }, $content);

        $content = preg_replace_callback('@<form[^>]*action=(["\'])(.*?)\1[^>]*>@i', function ($matches) use ($mainPageUrl) {
            $action = trim($matches[2]);
            if (! $action) {
                return '';
            }
            $changed = $this->encryptUrl($action, $mainPageUrl);

            return str_replace($action, $changed, $matches[0]);
        }, $content);

        $content = preg_replace_callback('/content=(["\'])\d+\s*;\s*url=(.*?)\1/is', function ($matches) use ($mainPageUrl) {
            $url = trim($matches[2]);
            $changed = $this->encryptUrl($url, $mainPageUrl);

            return str_replace($url, $changed, $matches[0]);
        }, $content);

        $content = preg_replace_callback('@[^a-z]{1}url\s*\((?:\'|"|)(.*?)(?:\'|"|)\)@im', function ($matches) use ($mainPageUrl) {
            $url = trim($matches[1]);
            if (static::startsWith($url, 'data:')) {
                return $matches[0];
            }
            $changed = $this->encryptUrl($url, $mainPageUrl);

            return str_replace($url, $changed, $matches[0]);
        }, $content);

        $content = preg_replace_callback('/srcset=\"(.*?)\"/i', function ($matches) use ($mainPageUrl) {
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
        }, $content);

        return $content;
    }
}
