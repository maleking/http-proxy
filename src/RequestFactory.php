<?php

namespace Akrez\HttpProxy;

use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class RequestFactory
{
    public static function fromServerRequest(ServerRequestInterface $globalServerRequest): ?ServerRequestInterface
    {
        $serverParams = $globalServerRequest->getServerParams() + ['REQUEST_URI' => null, 'SCRIPT_NAME' => null];

        $requestUri = $serverParams['REQUEST_URI'];
        $scriptNameSlash = $serverParams['SCRIPT_NAME'].'/';

        if (
            strpos($requestUri, $scriptNameSlash) === 0 and
            strlen($scriptNameSlash) < strlen($requestUri)
        ) {
            $url = substr($requestUri, strlen($scriptNameSlash));
        } else {
            return null;
        }

        [
            0 => $configString,
            1 => $hostPathString,
        ] = explode('/', $url, 2) + [0 => null, 1 => null];
        if (empty($configString) || empty($hostPathString)) {
            return null;
        }

        $sanitizedConfigs = static::sanitizeConfig($globalServerRequest, explode('_', $configString));

        $newUri = static::createUri($globalServerRequest, $sanitizedConfigs, $hostPathString);

        return static::toNewServerRequest(
            $globalServerRequest,
            $newUri,
            $sanitizedConfigs['method']
        );
    }

    protected static function toNewServerRequest(
        ServerRequestInterface $globalServerRequest,
        UriInterface $newUri,
        ?string $method
    ) {
        $newServerRequest = clone $globalServerRequest;

        $newServerRequest = $newServerRequest->withUri($newUri);
        if ($method) {
            $newServerRequest = $newServerRequest->withMethod($method);
        }

        $multipartBoundary = static::getMultipartBoundary($globalServerRequest);
        if ($multipartBoundary) {
            $newServerRequest = $newServerRequest->withBody(
                static::getMultipartStream($multipartBoundary, $globalServerRequest)
            );
        }

        return $newServerRequest;
    }

    protected static function sanitizeConfig(ServerRequestInterface $globalServerRequest, array $configs): array
    {
        return [
            'method' => static::findInArray($configs, ['get', 'post', 'head', 'put', 'delete', 'options', 'trace', 'connect', 'patch'], $globalServerRequest->getMethod()),
            'scheme' => static::findInArray($configs, ['https', 'http'], $globalServerRequest->getUri()->getScheme()),
        ];
    }

    protected static function createUri(ServerRequestInterface $serverRequest, array $sanitizedConfigs, string $hostPathString)
    {
        $uri = new Uri($sanitizedConfigs['scheme'].'://'.$hostPathString);
        $uri = $uri->withQuery($serverRequest->getUri()->getQuery());
        $uri = $uri->withFragment($serverRequest->getUri()->getFragment());

        return $uri;
    }

    protected static function getMultipartBoundary(ServerRequestInterface $globalServerRequest): ?string
    {
        $contentType = $globalServerRequest->getHeaderLine('Content-Type');

        if (
            strpos($contentType, 'multipart/form-data') === 0 and
            preg_match('/boundary=(.*)$/', $contentType, $matches)
        ) {
            return trim($matches[1], '"');
        }

        return null;
    }

    protected static function getMultipartStream(string $multipartBoundary, ServerRequestInterface $globalServerRequest)
    {
        $elements = [];

        foreach ($globalServerRequest->getParsedBody() as $key => $value) {
            $elements[] = [
                'name' => $key,
                'contents' => $value,
            ];
        }

        foreach ($globalServerRequest->getUploadedFiles() as $key => $value) {
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
