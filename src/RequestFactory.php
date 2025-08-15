<?php

namespace Akrez\HttpProxy;

use Akrez\HttpProxy\Factories\InbodyFactory;
use Akrez\HttpProxy\Factories\InlineFactory;
use Psr\Http\Message\ServerRequestInterface;

class RequestFactory
{
    const STATE_INLINE = 'inline';

    const STATE_INBODY = 'inbody';

    const DEBUG = 'debug';

    protected ?string $method;

    protected ?string $scheme;

    protected ?string $hostPath;

    protected ?string $state;

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
            'state' => $this->state,
            'debug' => $this->debug,
        ] = static::sanitizeConfig($globalServerRequest, explode('_', $configString), $this->hostPath);
    }

    public function make()
    {
        if ($this->state === static::STATE_INBODY) {
            return InbodyFactory::make($this->globalServerRequest, $this->scheme);
        } elseif ($this->hostPath) {
            return InlineFactory::make($this->globalServerRequest, $this->scheme, $this->method, $this->hostPath);
        }
    }

    protected static function sanitizeConfig(ServerRequestInterface $globalServerRequest, array $configs, ?string $hostPath): array
    {
        return [
            'method' => static::findInArray($configs, ['get', 'post', 'head', 'put', 'delete', 'options', 'trace', 'connect', 'patch'], $globalServerRequest->getMethod()),
            'scheme' => static::findInArray($configs, ['https', 'http'], $globalServerRequest->getUri()->getScheme()),
            'state' => static::findInArray($configs, [static::STATE_INLINE, static::STATE_INBODY], $hostPath ? static::STATE_INLINE : static::STATE_INBODY),
            'debug' => static::findInArray($configs, [static::DEBUG]) === static::DEBUG,
        ];
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
