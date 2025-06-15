<?php

namespace Akrez\HttpProxy;

use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

class RequestFactory
{
    const STATE_SIMPLE = 'simple';

    const STATE_DEBUG = 'debug';

    const STATE_REWRITE = 'rewrite';

    protected bool $successful = false;

    protected RequestInterface $request;

    protected ?string $state;

    public function isSuccessful()
    {
        return $this->successful;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getState()
    {
        return $this->state;
    }

    public function __construct(ServerRequestInterface $serverRequest)
    {
        $serverParams = $serverRequest->getServerParams() + ['REQUEST_URI' => null, 'SCRIPT_NAME' => null];

        $requestUri = $serverParams['REQUEST_URI'];
        $scriptNameSlash = $serverParams['SCRIPT_NAME'].'/';

        if (
            strpos($requestUri, $scriptNameSlash) === 0 and
            strlen($scriptNameSlash) < strlen($requestUri)
        ) {
            $url = substr($requestUri, strlen($scriptNameSlash));
        } else {
            return $this->successful = false;
        }
        $parts = explode('/', $url, 2) + [0 => null, 1 => null];

        if (empty($parts[0]) || empty($parts[1])) {
            return $this->successful = false;
        }

        $configs = static::sanitizeConfig($serverRequest, explode('_', $parts[0]));

        $newUrl = $configs['scheme'].'://'.$parts[1];

        $newUri = static::cloneUri($serverRequest->getUri(), $newUrl);

        $this->request = static::cloneRequest($serverRequest, $newUri, $configs['method'], $configs['protocolVersion']);

        $this->state = $configs['state'];

        return $this->successful = true;
    }

    protected static function cloneUri(Uri $uri, string $url): Uri
    {
        $uriUserInfos = explode(':', $uri->getUserInfo()) + [0 => '', 1 => ''];

        $components = array_filter(parse_url($url)) + [
            'scheme' => $uri->getScheme(),
            'user' => $uriUserInfos[0],
            'pass' => $uriUserInfos[1],
            'host' => $uri->getHost(),
            'port' => $uri->getPort(),
            'path' => $uri->getPath(),
            'query' => $uri->getQuery(),
            'fragment' => $uri->getFragment(),
        ];

        return (new Uri)
            ->withScheme($components['scheme'])
            ->withHost($components['host'])
            ->withPort($components['port'])
            ->withPath($components['path'])
            ->withQuery($components['query'])
            ->withFragment($components['fragment'])
            ->withUserInfo($components['user'], $components['pass']);
    }

    protected static function cloneRequest(RequestInterface $serverRequest, Uri $newUri, ?string $method = null, ?string $protocolVersion = null)
    {
        $newRequest = clone $serverRequest;

        $newRequest = $newRequest->withUri($newUri);

        if ($method) {
            $newRequest = $newRequest->withMethod($method);
        }

        if ($protocolVersion) {
            $newRequest = $newRequest->withProtocolVersion(strval($protocolVersion));
        }

        if ($multipartBoundary = static::getMultipartBoundary($serverRequest)) {
            $multipartStream = static::getMultipartStream($multipartBoundary, $serverRequest);
            $newRequest = $newRequest->withBody($multipartStream);
        }

        return $newRequest;
    }

    protected static function sanitizeConfig(RequestInterface $serverRequest, array $configs): array
    {
        return [
            'state' => static::findInArray($configs, [static::STATE_SIMPLE, static::STATE_DEBUG, static::STATE_REWRITE], static::STATE_SIMPLE),
            'method' => static::findInArray($configs, ['get', 'post', 'head', 'put', 'delete', 'options', 'trace', 'connect', 'patch'], $serverRequest->getMethod()),
            'scheme' => static::findInArray($configs, ['https', 'http'], $serverRequest->getUri()->getScheme()),
            'protocolVersion' => static::findInArray([10, 11, 20, 30], $configs, $serverRequest->getProtocolVersion() * 10) / 10.0,
        ];
    }

    protected static function getMultipartBoundary(RequestInterface $serverRequest): ?string
    {
        $contentType = $serverRequest->getHeaderLine('Content-Type');

        if (
            strpos($contentType, 'multipart/form-data') === 0 and
            preg_match('/boundary=(.*)$/', $contentType, $matches)
        ) {
            return trim($matches[1], '"');
        }

        return null;
    }

    protected static function getMultipartStream(string $multipartBoundary, ServerRequestInterface $serverRequest)
    {
        $elements = [];

        foreach ($serverRequest->getParsedBody() as $key => $value) {
            $elements[] = [
                'name' => $key,
                'contents' => $value,
            ];
        }

        foreach ($serverRequest->getUploadedFiles() as $key => $value) {
            if (empty($value->getError())) {
                $elements[] = [
                    'name' => $key,
                    'filename' => $value->getClientFilename(),
                    'contents' => $value->getStream(),
                ];
            }
        }

        return new MultipartStream($elements, $multipartBoundary);
    }

    protected static function findInArray(array $needles, array $haystack, ?string $default = null): ?string
    {
        foreach ($needles as $needle) {
            if (in_array(strtolower($needle), $haystack)) {
                return $needle;
            }
        }

        return $default;
    }
}
