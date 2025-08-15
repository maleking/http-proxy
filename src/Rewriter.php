<?php

namespace Akrez\HttpProxy;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Psr7\ServerRequest;
use League\Uri\Uri as LeagueUri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Rewriter
{
    public string $scriptUrl;

    public string $cookiePrefix;

    public function __construct(protected ServerRequest $serverRequest)
    {
        $serverParams = $this->serverRequest->getServerParams() + [
            'REQUEST_SCHEME' => null,
            'HTTP_HOST' => null,
            'SCRIPT_NAME' => null,
        ];
        $this->scriptUrl = $serverParams['REQUEST_SCHEME'].'://'.$serverParams['HTTP_HOST'].$serverParams['SCRIPT_NAME'];
        //
        $this->cookiePrefix = '__COOKIE_PREFIX__';
    }

    public function setScriptUrl(string $scriptUrl)
    {
        $this->scriptUrl = $scriptUrl;

        return $this;
    }

    public function setCookiePrefix(string $cookiePrefix)
    {
        $this->cookiePrefix = $cookiePrefix;

        return $this;
    }

    public function isTextHtml($response)
    {
        return $this->isContentType('text/html', $response);
    }

    public function isTextCss($response)
    {
        return $this->isContentType('text/css', $response);
    }

    public function isApplicationJson($response)
    {
        return $this->isContentType('application/json', $response);
    }

    public function convertTextHtml($body, $mainPageUrl)
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

    public function convertTextCss($body, $mainPageUrl)
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

    public function cookieBeforeSendRequest(RequestInterface $request)
    {
        $cookieHeaders = $request->getHeader('cookie');
        $request = $request->withoutHeader('cookie');
        //
        $cookieJarArray = [];
        foreach ($cookieHeaders as $cookieHeader) {
            if (preg_match_all(
                '@'.$this->cookiePrefix.'_(.+?)__(.+?)=([^;]+)@',
                $cookieHeader,
                $matches,
                PREG_SET_ORDER
            )) {
                foreach ($matches as $match) {
                    $cookieValue = $match[3];
                    $setCookieArray = json_decode(base64_decode($cookieValue), true);
                    $cookieJarArray[] = new SetCookie($setCookieArray);
                }
            }
        }
        //
        if ($cookieJarArray) {
            $cookieJar = new CookieJar(false, $cookieJarArray);
            $request = $cookieJar->withCookieHeader($request);
        }

        return $request;
    }

    public function cookieAfterReceivedResponse(RequestInterface $request, ResponseInterface $response)
    {
        $setCookieHeaders = $response->getHeader('set-cookie');
        if ($setCookieHeaders) {
            $domain = strval($request->getUri()->getHost());
            $response = $response->withoutHeader('set-cookie');
            foreach ($setCookieHeaders as $setCookieHeader) {
                $targetSetCookie = SetCookie::fromString($setCookieHeader);
                if (! $targetSetCookie->getDomain()) {
                    $targetSetCookie->setDomain($domain);
                }
                $newSetCookieName = sprintf(
                    '%s_%s__%s',
                    $this->cookiePrefix,
                    str_replace('.', '_', $domain),
                    $targetSetCookie->getName()
                );

                $newSetCookie = (clone $targetSetCookie);
                $newSetCookie->setName($newSetCookieName);
                $newSetCookie->setValue(base64_encode(json_encode($targetSetCookie->toArray())));
                $newSetCookie->setDomain($this->serverRequest->getUri()->getHost());
                $newSetCookie->setPath('/');

                $response = $response->withAddedHeader('set-cookie', $newSetCookie->__toString());
            }
        }

        return $response;
    }

    public function encryptUrl(string $urlString, ?string $mainUrlString = null)
    {
        try {

            if ($mainUrlString) {
                $url = LeagueUri::fromBaseUri($urlString, $mainUrlString);
            } else {
                $url = LeagueUri::new($urlString);
            }

            $newUrlString = $url->toString();
            if (strpos($newUrlString, 'https://') === 0) {
                $newUrlString = substr_replace($newUrlString, 'https/', 0, strlen('https://'));
            }
            if (strpos($newUrlString, 'http://') === 0) {
                $newUrlString = substr_replace($newUrlString, 'http/', 0, strlen('http://'));
            }

            return $this->scriptUrl.'/'.$newUrlString;

        } catch (\Throwable $th) {
            return $urlString;
        }

        return $url->toString();
    }

    protected static function isContentType(string $contentType, ResponseInterface $response)
    {
        $contentTypes = (array) $response->getHeader('Content-Type');

        return $contentType === trim(preg_replace('@;.*@', '', reset($contentTypes)));
    }

    protected static function trim(string $url)
    {
        return trim($url, " \n\r\t\v\0/");
    }

    protected static function startsWith($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && stripos($haystack, $needle) === 0) {
                return true;
            }
        }

        return false;
    }
}
