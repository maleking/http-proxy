<?php

namespace Akrez\HttpProxy\Support;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RewriteCookie
{
    public function __construct(public string $cookiePrefix) {}

    public function onBeforeRequest(RequestInterface $request)
    {
        $cookieHeaders = $request->getHeader('cookie');
        $request = $request->withoutHeader('cookie');
        //
        $domain = strval($request->getUri()->getHost());
        //
        $sendCookies = [];
        foreach ($cookieHeaders as $cookieHeader) {
            if (preg_match_all(
                '@'.$this->cookiePrefix.'_(.+?)__(.+?)=([^;]+)@',
                $cookieHeader,
                $matches,
                PREG_SET_ORDER
            )) {
                foreach ($matches as $match) {
                    $cookieDomain = str_replace('_', '.', $match[1]);
                    $cookieName = $match[2];
                    $cookieValue = $match[3];
                    // what is the domain or our current URL?
                    // does this cookie belong to this domain?
                    // sometimes domain begins with a DOT indicating all subdomains - deprecated but still in use on some servers...
                    if (strpos($domain, $cookieDomain) !== false) {
                        $sendCookies[$cookieName] = $cookieValue;
                    }
                }
            }
        }
        //
        if ($sendCookies) {
            $request = CookieJar::fromArray($sendCookies, $domain)->withCookieHeader($request);
        }

        return $request;
    }

    // cookies received from a target server via set-cookie should be rewritten
    public function onHeadersReceived(RequestInterface $request, ResponseInterface $response)
    {
        $setCookieHeaders = $response->getHeader('set-cookie');
        if ($setCookieHeaders) {
            $response = $response->withoutHeader('set-cookie');
            foreach ($setCookieHeaders as $setCookieHeader) {
                $setCookie = SetCookie::fromString($setCookieHeader);
                $cookieName = sprintf(
                    '%s_%s__%s',
                    $this->cookiePrefix,
                    str_replace('.', '_', strval($request->getUri()->getHost())),
                    $setCookie->getName()
                );
                $setCookie->setName($cookieName);
                $response = $response->withAddedHeader('set-cookie', $setCookie->__toString());
            }
        }

        return $response;
    }
}
