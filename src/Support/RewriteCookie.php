<?php

namespace Akrez\HttpProxy\Support;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RewriteCookie
{
    public function __construct(public string $cookiePrefix) {}

    public function onBeforeRequest(RequestInterface &$request)
    {
        // cookie sent by the browser to the server
        $cookieHeaders = $request->getHeader('cookie') + [0 => null];
        $cookieHeader = $cookieHeaders[0];
        // remove old cookie header and rewrite it
        $request = $request->withoutHeader('cookie');
        // When the user agent generates an HTTP request, the user agent MUST NOT attach more than one Cookie header field. http://tools.ietf.org/html/rfc6265#section-5.4
        $sendCookies = [];
        // extract "proxy cookies" only, A Proxy Cookie would have  the following name: cookiePrefix_domain-it-belongs-to__cookie-name
        if (preg_match_all('@'.$this->cookiePrefix.'_(.+?)__(.+?)=([^;]+)@', $cookieHeader, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $cookieName = $match[2];
                $cookieValue = $match[3];
                $cookieDomain = str_replace('_', '.', $match[1]);
                // what is the domain or our current URL?
                $host = parse_url($request->getUri(), PHP_URL_HOST);
                // does this cookie belong to this domain?, sometimes domain begins with a DOT indicating all subdomains - deprecated but still in use on some servers...
                if (strpos($host, $cookieDomain) !== false) {
                    $sendCookies[] = $cookieName.'='.$cookieValue;
                }
            }
        }
        // do we have any cookies to send?
        if ($sendCookies) {
            $request = $request->withHeader('cookie', implode('; ', $sendCookies));
        }
    }

    // cookies received from a target server via set-cookie should be rewritten
    public function onHeadersReceived(RequestInterface $request, ResponseInterface $response)
    {
        // does the response send any cookies?
        $setCookies = $response->getHeader('set-cookie');
        if ($setCookies) {
            // remove set-cookie header and reconstruct it differently
            $response = $response->withoutHeader('set-cookie');
            // loop through each set-cookie line
            foreach ($setCookies as $setCookie) {
                // parse cookie data as array from header line
                $cookie = $this->parseCookie($setCookie, $request->getUri()->__toString());
                // construct a "proxy cookie" whose name includes the domain to which this cookie belongs to replace dots with underscores as cookie name can only contain alphanumeric and underscore
                $cookieName = sprintf(
                    '%s_%s__%s',
                    $this->cookiePrefix,
                    str_replace('.', '_', $cookie['domain']),
                    $cookie['name']
                );
                // append a simple name=value cookie to the header - no expiration date means that the cookie will be a session cookie
                $response->withAddedHeader('set-cookie', $cookieName.'='.$cookie['value']);
            }
        }
    }

    // adapted from browserkit
    private function parseCookie($line, $url)
    {
        $host = parse_url($url, PHP_URL_HOST);

        $data = [
            'name' => '',
            'value' => '',
            'domain' => $host,
            'path' => '/',
            'expires' => 0,
            'secure' => false,
            'httpOnly' => true,
        ];

        $line = preg_replace('/^Set-Cookie2?: /i', '', trim($line));

        // there should be at least one name=value pair
        $pairs = array_filter(array_map('trim', explode(';', $line)));

        foreach ($pairs as $index => $comp) {
            $parts = explode('=', $comp, 2);
            $key = trim($parts[0]);
            if (count($parts) == 1) {
                // secure; HttpOnly; == 1
                $data[$key] = true;
            } else {
                $value = trim($parts[1]);
                if ($index == 0) {
                    $data['name'] = $key;
                    $data['value'] = $value;
                } else {
                    $data[$key] = $value;
                }
            }
        }

        return $data;
    }
}
