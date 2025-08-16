<?php

namespace Akrez\HttpProxy\Factories;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class Factory
{
    protected ?string $method;

    protected ?string $scheme;

    protected ?string $hostPath;

    protected bool $debug = false;

    public function __construct(protected ServerRequestInterface $globalServerRequest)
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
            1 => $this->hostPath,
        ] = explode('/', $url, 2) + [0 => null, 1 => null];

        [
            'method' => $this->method,
            'scheme' => $this->scheme,
            'debug' => $this->debug,
        ] = $this->sanitizeConfig($globalServerRequest, explode('_', $configString));
    }

    public function debug(): bool
    {
        return $this->debug;
    }

    protected function sanitizeConfig(ServerRequestInterface $globalServerRequest, array $configs): array
    {
        return [
            'method' => $this->findInArray($configs, ['get', 'post', 'head', 'put', 'delete', 'options', 'trace', 'connect', 'patch'], $globalServerRequest->getMethod()),
            'scheme' => $this->findInArray($configs, ['https', 'http'], $globalServerRequest->getUri()->getScheme()),
            'debug' => $this->findInArray($configs, ['debug']) === 'debug',
        ];
    }

    protected function findInArray(array $needles, array $haystack, ?string $default = null): ?string
    {
        foreach ($needles as $needle) {
            if (in_array(strtolower($needle), $haystack)) {
                return $needle;
            }
        }

        return $default;
    }

    abstract public function make(): ?RequestInterface;
}
