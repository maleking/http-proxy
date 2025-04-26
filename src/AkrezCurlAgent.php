<?php

namespace Akrez\HttpProxy;

use Psr\Http\Message\RequestInterface;

class AkrezCurlAgent
{
    protected RequestInterface $request;

    protected bool $debug;

    public function __construct(RequestInterface $request, $debug = false)
    {
        $this->request = $request;
        $this->debug = boolval($debug);
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getDebug()
    {
        return $this->debug;
    }

    public function emit($timeout = 60, $clientConfig = [])
    {
        $options = [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 0,

            // don't return anything - we have other functions for that
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,

            // don't bother with ssl
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,

            // we will take care of redirects
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_AUTOREFERER => false,

            CURLOPT_HEADERFUNCTION => [$this, 'header_callback'],
            CURLOPT_WRITEFUNCTION => [$this, 'write_callback'],
            CURLOPT_URL => $this->request->getUri()->__toString(),
            CURLOPT_CUSTOMREQUEST => $this->request->getMethod(),
            CURLOPT_HTTPHEADER => $this->prepareHeader(),
            CURLOPT_POSTFIELDS => $this->prepareBody(),
        ];

        $options = array_merge_custom($options, $clientConfig);

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        @curl_exec($ch);
    }

    public function prepareBody()
    {
        // code here
    }

    public function prepareHeader()
    {
        // code here
    }
}
