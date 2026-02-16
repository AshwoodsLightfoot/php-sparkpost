<?php

namespace SparkPost;

use Http\Client\Exception;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Class ResourceBase.
 */
class ResourceBase
{
    /**
     * SparkPost object used to make requests.
     */
    protected SparkPost $sparkpost;

    /**
     * The api endpoint that gets prepended to all requests send through this resource.
     */
    protected string $endpoint;

    /**
     * Sets up the Resource.
     *
     * @param SparkPost $sparkpost - the sparkpost instance that this resource is attached to
     * @param string $endpoint - the endpoint that this resource wraps
     */
    public function __construct(SparkPost $sparkpost, string $endpoint)
    {
        $this->sparkpost = $sparkpost;
        $this->endpoint = $endpoint;
    }

    /**
     * Sends get request to API at the set endpoint.
     *
     * @return SparkPostPromise|SparkPostResponse
     * @throws SparkPostException|Exception|ClientExceptionInterface
     * @see SparkPost->request()
     */
    public function get($uri = '', array $payload = [], array $headers = [])
    {
        return $this->request('GET', $uri, $payload, $headers);
    }

    /**
     * Sends put request to API at the set endpoint.
     *
     * @return SparkPostPromise|SparkPostResponse
     * @throws SparkPostException|Exception|ClientExceptionInterface
     * @see SparkPost->request()
     */
    public function put($uri = '', array $payload = [], array $headers = [])
    {
        return $this->request('PUT', $uri, $payload, $headers);
    }

    /**
     * Sends post request to API at the set endpoint.
     *
     * @return SparkPostPromise|SparkPostResponse
     * @throws SparkPostException
     * @throws Exception|ClientExceptionInterface
     * @see SparkPost->request()
     */
    public function post(array $payload = [], array $headers = [])
    {
        return $this->request('POST', '', $payload, $headers);
    }

    /**
     * Sends delete request to API at the set endpoint.
     *
     * @return SparkPostPromise|SparkPostResponse
     * @throws SparkPostException|Exception|ClientExceptionInterface
     * @see SparkPost->request()
     */
    public function delete($uri = '', array $payload = [], array $headers = [])
    {
        return $this->request('DELETE', $uri, $payload, $headers);
    }

    /**
     * Sends requests to SparkPost object to the resource endpoint.
     *
     * @return SparkPostPromise|SparkPostResponse depending on sync or async request
     * @throws SparkPostException
     * @throws Exception|ClientExceptionInterface
     * @see SparkPost->request()
     *
     */
    public function request(string $method = 'GET', $uri = '', array $payload = [], array $headers = [])
    {
        if (is_array($uri)) {
            $headers = $payload;
            $payload = $uri;
            $uri = '';
        }

        $uri = $this->endpoint . '/' . $uri;

        return $this->sparkpost->request($method, $uri, $payload, $headers);
    }
}
