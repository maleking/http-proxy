<?php

namespace Akrez\HttpProxy\Support;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RewriteCookie
{
    public function __construct(
        protected ServerRequest $serverRequest,
        public string $cookiePrefix
    ) {}

    public function onBeforeRequest(RequestInterface $request)
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
                    $setCookieArray = json_decode(base64_url_decode($cookieValue), true);
                    $cookieJarArray[] = new SetCookie($setCookieArray);
                }
            }
        }
        //
        if ($cookieJarArray) {
            $request = (new CookieJar(false, $cookieJarArray))->withCookieHeader($request);
        }

        return $request;
    }

    public function onHeadersReceived(RequestInterface $request, ResponseInterface $response)
    {
        $setCookieHeaders = $response->getHeader('set-cookie');
        if ($setCookieHeaders) {
            $response = $response->withoutHeader('set-cookie');
            foreach ($setCookieHeaders as $setCookieHeader) {
                $targetSetCookie = SetCookie::fromString($setCookieHeader);
                $newSetCookieName = sprintf(
                    '%s_%s__%s',
                    $this->cookiePrefix,
                    str_replace('.', '_', strval($request->getUri()->getHost())),
                    $targetSetCookie->getName()
                );

                $newSetCookie = (clone $targetSetCookie);
                $newSetCookie->setName($newSetCookieName);
                $newSetCookie->setValue(base64_url_encode(json_encode($targetSetCookie->toArray())));
                $newSetCookie->setDomain($this->serverRequest->getUri()->getHost());
                $newSetCookie->setPath('/');

                $response = $response->withAddedHeader('set-cookie', $newSetCookie->__toString());
            }
        }

        return $response;
    }
}
