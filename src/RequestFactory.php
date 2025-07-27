<?php

namespace Akrez\HttpProxy;

use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ServerRequestInterface;

class RequestFactory
{
    const STATE_SIMPLE = 'simple';

    const STATE_DEBUG = 'debug';

    const STATE_REWRITE = 'rewrite';

    protected ?ServerRequestInterface $newServerRequest = null;

    protected ?string $state = null;

    public function getNewServerRequest()
    {
        return $this->newServerRequest;
    }

    public function getState()
    {
        return $this->state;
    }

    public static function makeByServerRequest(ServerRequestInterface $globalServerRequest)
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
            return;
        }

        [
            0 => $configString,
            1 => $hostPathString,
        ] = explode('/', $url, 2) + [0 => null, 1 => null];
        if (empty($configString) || empty($hostPathString)) {
            return;
        }

        return new static($globalServerRequest, $configString, $hostPathString);
    }

    public function __construct(ServerRequestInterface $globalServerRequest, string $configString, string $hostPathString)
    {
        $sanitizedConfigs = static::sanitizeConfig($globalServerRequest, explode('_', $configString));

        $createdUri = static::createUri($globalServerRequest, $sanitizedConfigs, $hostPathString);

        $newServerRequest = clone $globalServerRequest;

        $newServerRequest = $newServerRequest->withUri($createdUri);
        if ($sanitizedConfigs['method']) {
            $newServerRequest = $newServerRequest->withMethod($sanitizedConfigs['method']);
        }
        if ($sanitizedConfigs['protocolVersion']) {
            $newServerRequest = $newServerRequest->withProtocolVersion(strval($sanitizedConfigs['protocolVersion']));
        }
        $newServerRequest = $newServerRequest->withoutHeader('referer');

        $multipartBoundary = static::getMultipartBoundary($globalServerRequest);
        if ($multipartBoundary) {
            $newServerRequest = $newServerRequest->withBody(
                static::getMultipartStream($multipartBoundary, $globalServerRequest)
            );
        }

        $this->newServerRequest = $newServerRequest;
        $this->state = $sanitizedConfigs['state'];
    }

    protected static function sanitizeConfig(ServerRequestInterface $globalServerRequest, array $configs): array
    {
        return [
            'state' => static::findInArray($configs, [static::STATE_SIMPLE, static::STATE_DEBUG], static::STATE_SIMPLE),
            'method' => static::findInArray($configs, ['get', 'post', 'head', 'put', 'delete', 'options', 'trace', 'connect', 'patch'], $globalServerRequest->getMethod()),
            'scheme' => static::findInArray($configs, ['https', 'http'], $globalServerRequest->getUri()->getScheme()),
            'protocolVersion' => static::findInArray([10, 11, 20, 30], $configs, $globalServerRequest->getProtocolVersion() * 10) / 10.0,
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
