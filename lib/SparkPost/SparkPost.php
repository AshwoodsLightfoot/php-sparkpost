<?php

namespace SparkPost;

use Http\Client\Exception;
use Http\Client\HttpAsyncClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class SparkPost
{

    private string $version = '2.3.0';

    /**
     * @var ClientInterface|HttpAsyncClient used to make requests
     */
    private $httpClient;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    private array $options;

    /**
     * Default options for requests that can be overridden with the setOptions function.
     */
    private static array $defaultOptions = [
        'host' => 'api.sparkpost.com',
        'protocol' => 'https',
        'port' => 443,
        'key' => '',
        'version' => 'v1',
        'async' => true,
        'debug' => false,
        'retries' => 0
    ];

    public Transmission $transmissions;

    /**
     * Sets up the SparkPost instance.
     *
     * @param ClientInterface $httpClient - An httplug client or adapter
     * @param array $options - An array to overide default options or a string to be used as an API key
     * @throws \Exception
     */
    public function __construct(ClientInterface $httpClient, array $options)
    {
        $this->setOptions($options);
        $this->setHttpClient($httpClient);
        $this->setupEndpoints();
    }

    /**
     * Sends either sync or async request based on async option.
     *
     * @param string $method
     * @param string $uri
     * @param array $payload - either used as the request body or url query params
     * @param array $headers
     *
     * @return SparkPostPromise|SparkPostResponse Promise or Response depending on sync or async request
     * @throws SparkPostException
     * @throws \Exception|Exception|ClientExceptionInterface
     */
    public function request(string $method = 'GET', string $uri = '', array $payload = [], array $headers = [])
    {
        if ($this->options['async'] === true) {
            return $this->asyncRequest($method, $uri, $payload, $headers);
        } else {
            return $this->syncRequest($method, $uri, $payload, $headers);
        }
    }

    /**
     * Sends sync request to SparkPost API.
     *
     * @param string $method
     * @param string $uri
     * @param array $payload
     * @param array $headers
     *
     * @return SparkPostResponse
     *
     * @throws SparkPostException|Exception|ClientExceptionInterface
     */
    public function syncRequest(string $method = 'GET', string $uri = '', array $payload = [], array $headers = []): SparkPostResponse
    {
        $requestValues = $this->buildRequestValues($method, $uri, $payload, $headers);
        $request = call_user_func_array([$this, 'buildRequestInstance'], $requestValues);

        $retries = $this->options['retries'];
        try {
            $resp = $retries > 0
                ? $this->syncReqWithRetry($request, $retries)
                : $this->httpClient->sendRequest(
                    $request
                );
            return new SparkPostResponse($resp, $this->ifDebug($requestValues));
        } catch (\Exception $exception) {
            throw new SparkPostException($exception, $this->ifDebug($requestValues));
        }
    }

    /**
     * @throws Exception|ClientExceptionInterface
     */
    private function syncReqWithRetry( RequestInterface $request, int $retries): ResponseInterface
    {
        $resp = $this->httpClient->sendRequest($request);
        $status = $resp->getStatusCode();
        if ($status >= 500 && $status <= 599 && $retries > 0) {
            return $this->syncReqWithRetry($request, $retries - 1);
        }
        return $resp;
    }

    /**
     * Sends async request to SparkPost API.
     *
     * @param string $method
     * @param string $uri
     * @param array $payload
     * @param array $headers
     *
     * @return SparkPostPromise
     * @throws \Exception
     */
    public function asyncRequest(
        string $method = 'GET',
        string $uri = '',
        array $payload = [],
        array $headers = []
    ): SparkPostPromise {
        if ($this->httpClient instanceof HttpAsyncClient) {
            $requestValues = $this->buildRequestValues($method, $uri, $payload, $headers);
            $request = call_user_func_array([$this, 'buildRequestInstance'], $requestValues);

            $retries = $this->options['retries'];
            if ($retries > 0) {
                return new SparkPostPromise(
                    $this->asyncReqWithRetry($request, $retries), $this->ifDebug($requestValues)
                );
            } else {
                return new SparkPostPromise(
                    $this->httpClient->sendAsyncRequest($request),
                    $this->ifDebug($requestValues)
                );
            }
        } else {
            throw new \Exception(
                'Your http client does not support asynchronous requests. Please use a different client or use synchronous requests.'
            );
        }
    }

    /**
     * @throws \Exception
     */
    private function asyncReqWithRetry(RequestInterface $request, int $retries)
    {
        return $this->httpClient->sendAsyncRequest($request)->then(function ($response) use ($request, $retries) {
            $status = $response->getStatusCode();
            if ($status >= 500 && $status <= 599 && $retries > 0) {
                return $this->asyncReqWithRetry($request, $retries - 1);
            }
            return $response;
        });
    }

    /**
     * Builds request values from given params.
     *
     * @param string $method
     * @param string $uri
     * @param array $payload
     * @param array $headers
     *
     * @return array $requestValues
     */
    public function buildRequestValues(string $method, string $uri, array $payload, array $headers): array
    {
        $method = trim(strtoupper($method));

        if ($method === 'GET') {
            $params = $payload;
            $body = [];
        } else {
            $params = [];
            $body = $payload;
        }

        $url = $this->getUrl($uri, $params);
        $headers = $this->getHttpHeaders($headers);

        // old form-feed workaround now removed
        $body = json_encode($body);

        if ($body === false) {
            throw new \Exception('JSON encoding error: ' . json_last_error_msg());
        }

        return [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /**
     * Build RequestInterface from given params.
     *
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param array|string $body
     * @return RequestInterface
     */
    public function buildRequestInstance(string $method, string $url, array $headers, $body): RequestInterface
    {
        $request = $this->getRequestFactory()->createRequest($method, $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if (isset($body) && $body !== '' && $body !== []) {
            $request = $request->withBody($this->getStreamFactory()->createStream($body));
        }

        return $request;
    }

    /**
     * Build RequestInterface from given params.
     *
     * @param string $method
     * @param string $uri
     * @param array $payload
     * @param array $headers
     * @return RequestInterface
     */
    public function buildRequest(string $method, string $uri, array $payload, array $headers): RequestInterface
    {
        $requestValues = $this->buildRequestValues($method, $uri, $payload, $headers);
        return call_user_func_array([$this, 'buildRequestInstance'], $requestValues);
    }

    /**
     * Returns an array for the request headers.
     *
     * @param array $headers - any custom headers for the request
     *
     * @return array $headers - headers for the request
     */
    public function getHttpHeaders(array $headers = []): array
    {
        $constantHeaders = [
            'Authorization' => $this->options['key'],
            'Content-Type' => 'application/json',
            'User-Agent' => 'php-sparkpost/' . $this->version,
        ];

        foreach ($constantHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        return $headers;
    }

    /**
     * Builds the request url from the options and given params.
     *
     * @param string $path - the path in the url to hit
     * @param array $params - query parameters to be encoded into the url
     *
     * @return string $url - the url to send the desired request to
     */
    public function getUrl(string $path, array $params = []): string
    {
        $options = $this->options;

        $paramsArray = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }

            $paramsArray[] = $key . '=' . $value;
        }

        $paramsString = implode('&', $paramsArray);

        return $options['protocol'] . '://' . $options['host'] . ($options['port'] ? ':' . $options['port'] : '') . '/api/' . $options['version'] . '/' . $path . ($paramsString !== '' && $paramsString !== '0' ? '?' . $paramsString : '');
    }

    /**
     * Sets $httpClient to be used for request.
     *
     * @param ClientInterface|HttpAsyncClient $httpClient - the client to be used for request
     *
     * @return SparkPost
     */
    public function setHttpClient($httpClient): self
    {
        if (!$httpClient instanceof ClientInterface && !$httpClient instanceof HttpAsyncClient) {
            throw new \LogicException(
                sprintf(
                    'Parameter to SparkPost::setHttpClient must be instance of "%s" or "%s"',
                    ClientInterface::class,
                    HttpAsyncClient::class
                )
            );
        }

        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * Sets the options from the param and defaults for the SparkPost object.
     *
     * @param string|array $options - either an string API key or an array of options
     *
     * @return SparkPost
     * @throws \Exception
     */
    public function setOptions($options): self
    {
        // if the options map is a string we should assume that its an api key
        if (is_string($options)) {
            $options = ['key' => $options];
        }

        if (!isset($this->options)) {
            $this->options = self::$defaultOptions;
        }

        // Validate API key because its required
        $keyToValidate = $options['key'] ?? $this->options['key'] ?? '';
        if (!preg_match('/\S/', $keyToValidate)) {
            throw new \Exception('You must provide an API key');
        }

        // set options, overriding defaults
        foreach ($options as $option => $value) {
            if (array_key_exists($option, $this->options)) {
                $this->options[$option] = $value;
            }
        }

        return $this;
    }

    /**
     * Returns the given value if debugging, an empty instance otherwise.
     *
     * @param array $param
     *
     * @return array|null $param
     */
    private function ifDebug(array $param): ?array
    {
        return $this->options['debug'] ? $param : null;
    }

    /**
     * Sets up any endpoints to custom classes e.g. $this->transmissions.
     */
    private function setupEndpoints(): void
    {
        $this->transmissions = new Transmission($this);
    }

    /**
     * @return RequestFactoryInterface
     */
    private function getRequestFactory(): RequestFactoryInterface
    {
        if (!isset($this->requestFactory)) {
            $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        }

        return $this->requestFactory;
    }

    /**
     * @return StreamFactoryInterface
     */
    private function getStreamFactory(): StreamFactoryInterface
    {
        if (!isset($this->streamFactory)) {
            $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        }

        return $this->streamFactory;
    }

    /**
     * @param RequestFactoryInterface $requestFactory
     *
     * @return SparkPost
     */
    public function setRequestFactory(RequestFactoryInterface $requestFactory): self
    {
        $this->requestFactory = $requestFactory;

        return $this;
    }

    /**
     * @param StreamFactoryInterface $streamFactory
     *
     * @return SparkPost
     */
    public function setStreamFactory(StreamFactoryInterface $streamFactory): self
    {
        $this->streamFactory = $streamFactory;

        return $this;
    }
}
