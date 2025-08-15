<?php

namespace Akrez\HttpProxy\Factories;

use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

class InlineFactory
{
    public static function make(ServerRequestInterface $globalServerRequest, string $scheme, string $method, string $hostPathString): ?RequestInterface
    {
        $newUri = static::createUri($globalServerRequest, $scheme, $hostPathString);

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

    protected static function createUri(ServerRequestInterface $serverRequest, string $scheme, string $hostPathString)
    {
        $uri = new Uri($scheme.'://'.$hostPathString);
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
}
