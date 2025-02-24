<?php

namespace Akrez\HttpProxy;

use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

class AgentFactory
{
    public static function byServerRequest(ServerRequestInterface $serverRequest)
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
            return null;
        }
        $parts = explode('/', $url, 2) + [0 => null, 1 => null];

        $configs = [];
        if (strpos($parts[0], '.') === false) {
            $configs = explode('_', $parts[0]);
            $url = $parts[1];
        }

        if (empty($url)) {
            return null;
        }

        return static::make(
            $serverRequest,
            $url,
            static::findInArray($configs, ['get', 'post', 'head', 'put', 'delete', 'options', 'trace', 'connect', 'patch'], $serverRequest->getMethod()),
            static::findInArray($configs, ['https', 'http'], $serverRequest->getUri()->getScheme()),
            static::findInArray([10, 11, 20, 30], $configs, $serverRequest->getProtocolVersion() * 10) / 10.0,
            static::findInArray($configs, ['debug'], false)
        );
    }

    protected static function make(RequestInterface $request, ?string $url = null, ?string $method = null, ?string $schema = null, ?string $protocolVersion = null, bool $debug = false)
    {
        $defaultUri = $request->getUri();

        $newSchema = ($schema ?: $defaultUri->getScheme());
        $newUri = ($url ? new Uri($newSchema.'://'.$url) : $defaultUri)->withScheme($newSchema);

        $request = $request->withUri($newUri);

        if ($method) {
            $request = $request->withMethod($method);
        }

        if ($protocolVersion) {
            $request = $request->withProtocolVersion(strval($protocolVersion));
        }

        if ($multipartBoundary = static::getMultipartBoundary($request)) {
            $multipartStream = static::getMultipartStream($multipartBoundary, $request);
            $request = $request->withBody($multipartStream);
        }

        return new Agent($request, $debug);
    }

    protected static function getMultipartBoundary(RequestInterface $request): ?string
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (
            strpos($contentType, 'multipart/form-data') === 0 and
            preg_match('/boundary=(.*)$/', $contentType, $matches)
        ) {
            return trim($matches[1], '"');
        }

        return null;
    }

    protected static function getMultipartStream(string $multipartBoundary, ServerRequestInterface $request)
    {
        $elements = [];

        foreach ($request->getParsedBody() as $key => $value) {
            $elements[] = [
                'name' => $key,
                'contents' => $value,
            ];
        }

        foreach ($request->getUploadedFiles() as $key => $value) {
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
