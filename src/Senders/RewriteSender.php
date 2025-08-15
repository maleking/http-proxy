<?php

namespace Akrez\HttpProxy\Senders;

use Akrez\HttpProxy\Rewriters\TextCssRewriter;
use Akrez\HttpProxy\Rewriters\TextHtmlRewriter;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\Uri\Uri as LeagueUri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RewriteSender extends Sender
{
    protected string $scriptUrl = '';

    protected array $rewritersClassName = [];

    protected string $cookiePrefix = '__COOKIE_PREFIX__';

    public function __construct(protected ServerRequest $serverRequest)
    {
        $serverParams = $this->serverRequest->getServerParams() + [
            'REQUEST_SCHEME' => null,
            'HTTP_HOST' => null,
            'SCRIPT_NAME' => null,
        ];
        $this->setScriptUrl($serverParams['REQUEST_SCHEME'].'://'.$serverParams['HTTP_HOST'].$serverParams['SCRIPT_NAME']);
        //
        $this->setRewritersClassName([]);
    }

    public function setScriptUrl(string $scriptUrl)
    {
        $this->scriptUrl = $scriptUrl;

        return $this;
    }

    public function setRewritersClassName(array $rewritersClassName, bool $addDefaults = true)
    {
        if ($addDefaults) {
            array_unshift($rewritersClassName,
                TextHtmlRewriter::class,
                TextCssRewriter::class,
            );
        }

        $this->rewritersClassName = $rewritersClassName;

        return $this;
    }

    public function setCookiePrefix(string $cookiePrefix)
    {
        $this->cookiePrefix = $cookiePrefix;

        return $this;
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

    protected function beforeSendRequest(RequestInterface $newRequest)
    {
        $newRequest = parent::beforeSendRequest($newRequest);

        $newRequest = $this->cookieBeforeSendRequest($newRequest);

        return $newRequest;
    }

    protected function emitRequest(RequestInterface $newRequest)
    {
        $clientConfig = [
            'verify' => false,
            'allow_redirects' => false,
            'referer' => false,
            'decode_content' => false,
            'http_errors' => false,
        ];
        if ($this->timeout !== null) {
            $clientConfig += [
                'timeout' => $this->timeout,
                'connect_timeout' => $this->timeout,
                'read_timeout' => $this->timeout,
            ];
        }

        $client = new Client($clientConfig);

        $response = $client->send($newRequest);

        $response = $this->afterReceivedResponse($newRequest, $response);

        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $headerKey => $headers) {
            header($headerKey.': '.implode(', ', $headers), false);
        }

        $body = $response->getBody();
        while (! $body->eof()) {
            echo $body->read($this->bufferSize);
            flush();
        }
    }

    protected function afterReceivedResponse(RequestInterface $newRequest, ResponseInterface $response)
    {
        $response = $this->locationAfterReceivedResponse($newRequest, $response);

        $response = $this->cookieAfterReceivedResponse($newRequest, $response);

        $response = $this->contentAfterReceivedResponse($newRequest, $response);

        return $response->withoutHeader('transfer-encoding');
    }

    protected function cookieAfterReceivedResponse(RequestInterface $newRequest, ResponseInterface $response)
    {
        $setCookieHeaders = $response->getHeader('set-cookie');
        if ($setCookieHeaders) {
            $domain = strval($newRequest->getUri()->getHost());
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

    protected function locationAfterReceivedResponse(RequestInterface $newRequest, ResponseInterface $response)
    {
        $locationHeaders = $response->getHeader('location');
        if ($locationHeaders) {
            $response = $response->withoutHeader('location');
            foreach ($locationHeaders as $locationHeader) {
                $newLocation = $this->encryptUrl($locationHeader, $newRequest->getUri()->__toString());
                $response = $response->withAddedHeader('location', $newLocation);
            }
        }

        return $response;
    }

    protected function contentAfterReceivedResponse(RequestInterface $newRequest, ResponseInterface $response)
    {
        $newRequestUrl = $newRequest->getUri()->__toString();

        $newContent = null;
        foreach ($this->rewritersClassName as $rewriterClassName) {
            $rewriter = new $rewriterClassName($this);
            if ($rewriter->isMine($newRequest, $response)) {
                $newContent = ($newContent === null ? $response->getBody()->getContents() : $newContent);
                $newContent = $rewriter->convert($newContent, $newRequestUrl);
            }
        }
        if ($newContent) {
            $response = new Response(
                $response->getStatusCode(),
                $response->getHeaders(),
                $newContent,
                $response->getProtocolVersion(),
                $response->getReasonPhrase()
            );
            $response = $response
                ->withoutHeader('Content-Length')
                ->withHeader('Content-Length', $response->getBody()->getSize());
        }

        return $response;
    }

    protected function cookieBeforeSendRequest(RequestInterface $newRequest)
    {
        $cookieHeaders = $newRequest->getHeader('cookie');
        $newRequest = $newRequest->withoutHeader('cookie');
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
            $newRequest = $cookieJar->withCookieHeader($newRequest);
        }

        return $newRequest;
    }
}
