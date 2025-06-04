<?php

namespace Akrez\HttpProxy;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class Agent
{
    const STATE_DEBUG = 'debug';

    protected function __construct(
        protected RequestInterface $request,
        protected ?string $state
    ) {}

    public function getRequest()
    {
        return $this->request;
    }

    public function getState()
    {
        return $this->state;
    }

    public static function makeByServerRequest(ServerRequestInterface $serverRequest)
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
            static::findInArray($configs, [static::STATE_DEBUG], null),
            $url,
            static::findInArray($configs, ['get', 'post', 'head', 'put', 'delete', 'options', 'trace', 'connect', 'patch'], $serverRequest->getMethod()),
            static::findInArray($configs, ['https', 'http'], $serverRequest->getUri()->getScheme()),
            static::findInArray([10, 11, 20, 30], $configs, $serverRequest->getProtocolVersion() * 10) / 10.0,
        );
    }

    public function emit($streamer, $timeout = null): null|Exception|Throwable
    {
        ini_set('output_buffering', 'Off');
        ini_set('output_handler', '');
        ini_set('zlib.output_compression', 0);

        $clientConfig = [
            'verify' => false,
            'allow_redirects' => false,
            'referer' => false,
            'decode_content' => false,
            'http_errors' => false,
            'on_headers' => fn (ResponseInterface $response) => $streamer->emitHeaders($response),
            'sink' => $streamer,
        ];
        if ($timeout) {
            $clientConfig += [
                'timeout' => $timeout,
                'connect_timeout' => $timeout,
                'read_timeout' => $timeout,
            ];
        }

        $client = new Client($clientConfig);

        try {
            $client->send($this->request);
        } catch (Throwable $e) {
            return $e;
        } catch (Exception $e) {
            return $e;
        }

        return null;
    }

    protected static function make(RequestInterface $request, ?string $state = null, ?string $url = null, ?string $method = null, ?string $schema = null, ?string $protocolVersion = null)
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

        return new static($request, $state);
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
