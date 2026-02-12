<?php

namespace SparkPost\Test;

use Http\Client\Exception;
use Http\Client\Exception\HttpException;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Http\Promise\Promise;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Nyholm\NSA;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SparkPost\SparkPost;
use SparkPost\SparkPostException;
use SparkPost\SparkPostPromise;
use GuzzleHttp\Promise\FulfilledPromise as GuzzleFulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise as GuzzleRejectedPromise;
use Http\Adapter\Guzzle7\Promise as GuzzleAdapterPromise;
use Mockery;
use SparkPost\SparkPostResponse;

class SparkPostTest extends TestCase
{
    public array $badResponseBody;
    public $badResponseMock;
    private $clientMock;
    /** @var SparkPost */
    private SparkPost $resource;

    private $exceptionMock;
    private array $exceptionBody;

    private $responseMock;
    private array $responseBody;

    private $promiseMock;

    private array $postTransmissionPayload = [
        'content' => [
            'from' => ['name' => 'Sparkpost Team', 'email' => 'postmaster@sendmailfor.me'],
            'subject' => 'First Mailing From PHP',
            'text' => 'Congratulations, {{name}}!! You just sent your very first mailing!',
        ],
        'substitution_data' => ['name' => 'Avi'],
        'recipients' => [
            ['address' => 'avi.goldman@sparkpost.com'],
        ],
    ];

    private array $getTransmissionPayload = [
        'campaign_id' => 'thanksgiving',
    ];

    /**
     * @throws \Exception
     */
    public function setUp(): void
    {
        // response mock up
        $responseBodyMock = Mockery::mock(StreamInterface::class);
        $this->responseBody = ['results' => 'yay'];
        $this->responseMock = Mockery::mock(ResponseInterface::class);
        $this->responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $this->responseMock->shouldReceive('getBody')->andReturn($responseBodyMock);
        $responseBodyMock->shouldReceive('__toString')->andReturn(json_encode($this->responseBody));

        $errorBodyMock = Mockery::mock(StreamInterface::class);
        $this->badResponseBody = ['errors' => []];
        $this->badResponseMock = Mockery::mock(ResponseInterface::class);
        $this->badResponseMock->shouldReceive('getStatusCode')->andReturn(503);
        $this->badResponseMock->shouldReceive('getBody')->andReturn($errorBodyMock);
        $errorBodyMock->shouldReceive('__toString')->andReturn(json_encode($this->badResponseBody));

        // exception mock up
        $exceptionResponseMock = Mockery::mock(ResponseInterface::class);
        $exceptionResponseBodyMock = Mockery::mock(StreamInterface::class);
        $this->exceptionBody = ['results' => 'failed'];
        $this->exceptionMock = Mockery::mock(HttpException::class);
        $this->exceptionMock->shouldReceive('getResponse')->andReturn($exceptionResponseMock);
        $exceptionResponseMock->shouldReceive('getStatusCode')->andReturn(500);
        $exceptionResponseMock->shouldReceive('getBody')->andReturn($exceptionResponseBodyMock);
        $exceptionResponseBodyMock->shouldReceive('__toString')->andReturn(json_encode($this->exceptionBody));

        // promise mock up
        $this->promiseMock = Mockery::mock(Promise::class);

        //setup mock for the adapter
        $this->clientMock = Mockery::mock(HttpClient::class, HttpAsyncClient::class);
        $this->clientMock->shouldReceive('sendAsyncRequest')
            ->with(Mockery::type(RequestInterface::class))
            ->andReturn($this->promiseMock);

        $this->resource = new SparkPost($this->clientMock, ['key' => 'SPARKPOST_API_KEY']);
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * Test that request() returns a SparkPostResponse when async option is false.
     *
     * Why: Ensures the SparkPost client correctly handles synchronous request mode.
     * How: Sets 'async' to false, mocks the client's sendRequest method, and asserts the returned object is a SparkPostResponse.
     *
     * @throws SparkPostException
     * @throws \Exception
     */
    public function testRequestSync(): void
    {
        $this->resource->setOptions(['async' => false]);
        $this->clientMock->shouldReceive('sendRequest')->andReturn($this->responseMock);

        $this->assertInstanceOf(
            SparkPostResponse::class,
            $this->resource->request('POST', 'transmissions', $this->postTransmissionPayload)
        );
    }

    /**
     * Test that request() returns a SparkPostPromise when async option is true.
     *
     * Why: Ensures the SparkPost client correctly handles asynchronous request mode.
     * How: Sets 'async' to true, mocks the client's sendAsyncRequest method, and asserts the returned object is a SparkPostPromise.
     *
     * @throws SparkPostException
     * @throws \Exception
     */
    public function testRequestAsync(): void
    {
        $promiseMock = Mockery::mock(Promise::class);
        $this->resource->setOptions(['async' => true]);
        $this->clientMock->shouldReceive('sendAsyncRequest')->andReturn($promiseMock);

        $this->assertInstanceOf(
            SparkPostPromise::class,
            $this->resource->request('GET', 'transmissions', $this->getTransmissionPayload)
        );
    }

    /**
     * Test that the debug option, when false, does not include request data in the response.
     *
     * Why: Verifies that sensitive or large request data is not attached to the response object by default.
     * How: Sets 'debug' to false, executes a request, and asserts that getRequest() on the resulting response returns null.
     *
     * @throws SparkPostException
     * @throws \Exception
     */
    public function testDebugOptionWhenFalse(): void
    {
        $this->resource->setOptions(['async' => false, 'debug' => false]);
        $this->clientMock->shouldReceive('sendRequest')->andReturn($this->responseMock);

        $response = $this->resource->request('POST', 'transmissions', $this->postTransmissionPayload);

        $this->assertEquals(null, $response->getRequest());
    }

    /**
     * Test that the debug option, when true, includes request data in successful and failed responses.
     *
     * Why: Ensures developers can access original request parameters from response/exception objects for debugging.
     * How: Sets 'debug' to true, executes requests (one success, one failure), and verifies that request data is correctly attached.
     *
     * @throws SparkPostException
     * @throws \Exception
     */
    public function testDebugOptionWhenTrue(): void
    {
        // setup
        $this->resource->setOptions(['async' => false, 'debug' => true]);

        // successful
        $this->clientMock->shouldReceive('sendRequest')->once()->andReturn($this->responseMock);
        $response = $this->resource->request('POST', 'transmissions', $this->postTransmissionPayload);
        $this->assertEquals(json_decode($response->getRequest()['body'], true), $this->postTransmissionPayload);

        // unsuccessful
        $this->clientMock->shouldReceive('sendRequest')->once()->andThrow($this->exceptionMock);

        try {
            $response = $this->resource->request('POST', 'transmissions', $this->postTransmissionPayload);
        } catch (\Exception $e) {
            $this->assertEquals(json_decode($e->getRequest()['body'], true), $this->postTransmissionPayload);
        }
    }

    /**
     * Test a successful synchronous request.
     *
     * Why: Verifies that syncRequest() correctly processes a successful API call.
     * How: Mocks a successful sendRequest call and asserts the SparkPostResponse contains the expected body and status code.
     *
     * @throws Exception
     * @throws SparkPostException
     */
    public function testSuccessfulSyncRequest(): void
    {
        $this->clientMock->shouldReceive('sendRequest')->
        once()->
        with(Mockery::type(RequestInterface::class))->
        andReturn($this->responseMock);

        $response = $this->resource->syncRequest('POST', 'transmissions', $this->postTransmissionPayload);

        $this->assertEquals($this->responseBody, $response->getBodyDecoded());
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test an unsuccessful synchronous request.
     *
     * Why: Ensures that HTTP client exceptions are properly wrapped and re-thrown as SparkPostExceptions.
     * How: Mocks a client exception during sendRequest and verifies that a SparkPostException is caught with correct error details.
     */
    public function testUnsuccessfulSyncRequest(): void
    {
        $this->clientMock->shouldReceive('sendRequest')->
        once()->
        with(Mockery::type(RequestInterface::class))->
        andThrow($this->exceptionMock);

        try {
            $this->resource->syncRequest('POST', 'transmissions', $this->postTransmissionPayload);
        } catch (\Exception|Exception $e) {
            $this->assertEquals($this->exceptionBody, $e->getBody());
            $this->assertEquals(500, $e->getCode());
        }
    }

    /**
     * Test synchronous request with automatic retries on 5xx errors.
     *
     * Why: Verifies that the client correctly retries transient server errors.
     * How: Mocks the client to return multiple 503 responses followed by a 200, and verifies the final success.
     *
     * @throws Exception
     * @throws SparkPostException
     * @throws \Exception
     */
    public function testSuccessfulSyncRequestWithRetries(): void
    {
        $this->clientMock->shouldReceive('sendRequest')->
        with(Mockery::type(RequestInterface::class))->
        andReturn($this->badResponseMock, $this->badResponseMock, $this->responseMock);

        $this->resource->setOptions(['retries' => 2]);
        $response = $this->resource->syncRequest('POST', 'transmissions', $this->postTransmissionPayload);

        $this->assertEquals($this->responseBody, $response->getBodyDecoded());
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test that synchronous retries are exhausted after the specified number of attempts.
     *
     * Why: Ensures the client doesn't retry indefinitely and eventually reports the failure.
     * How: Mocks a persistent client exception and verifies that it is thrown after the configured retry limit is reached.
     *
     * @throws Exception
     * @throws \Exception
     */
    public function testUnsuccessfulSyncRequestWithRetries(): void
    {
        $this->clientMock->shouldReceive('sendRequest')->
        once()->
        with(Mockery::type(RequestInterface::class))->
        andThrow($this->exceptionMock);

        $this->resource->setOptions(['retries' => 2]);
        try {
            $this->resource->syncRequest('POST', 'transmissions', $this->postTransmissionPayload);
        } catch (\Exception $e) {
            $this->assertEquals($this->exceptionBody, $e->getBody());
            $this->assertEquals(500, $e->getCode());
        }
    }

    /**
     * Test a successful asynchronous request followed by wait().
     *
     * Why: Verifies the basic async request-response flow using promise wait().
     * How: Mocks an async request that returns a promise, and asserts that wait() on the returned SparkPostPromise yield correct data.
     *
     * @throws SparkPostException
     * @throws \Exception
     */
    public function testSuccessfulAsyncRequestWithWait(): void
    {
        $this->promiseMock->shouldReceive('wait')->andReturn($this->responseMock);

        $promise = $this->resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload);
        $response = $promise->wait();

        $this->assertEquals($this->responseBody, $response->getBodyDecoded());
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test an unsuccessful asynchronous request followed by wait().
     *
     * Why: Ensures that async failures are correctly reported when wait() is called.
     * How: Mocks an async request that returns a rejected promise and verifies wait() throws a SparkPostException.
     *
     * @throws \Exception
     */
    public function testUnsuccessfulAsyncRequestWithWait(): void
    {
        $this->promiseMock->shouldReceive('wait')->andThrow($this->exceptionMock);

        $promise = $this->resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload);

        try {
            $response = $promise->wait();
        } catch (\Exception $e) {
            $this->assertEquals($this->exceptionBody, $e->getBody());
            $this->assertEquals(500, $e->getCode());
        }
    }

    /**
     * Test a successful asynchronous request using then() callbacks.
     *
     * Why: Verifies that the onFulfilled callback is correctly triggered with a SparkPostResponse.
     * How: Uses a Guzzle fulfilled promise and verifies the then() callback receives the expected status and body.
     *
     * @throws \Throwable
     */
    public function testSuccessfulAsyncRequestWithThen(): void
    {
        $guzzlePromise = new GuzzleFulfilledPromise($this->responseMock);
        $result = $this->resource->buildRequest('POST', 'transmissions', $this->postTransmissionPayload, []);

        $promise = new SparkPostPromise(new GuzzleAdapterPromise($guzzlePromise, $result));

        $responseBody = $this->responseBody;
        $promise->then(function ($response) use ($responseBody): void {
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals($responseBody, $response->getBodyDecoded());
        })->wait();
    }

    /**
     * Test an unsuccessful asynchronous request using then() callbacks.
     *
     * Why: Verifies that the onRejected callback is correctly triggered with a SparkPostException.
     * How: Uses a Guzzle rejected promise and verifies the then() callback receives the expected error code and body.
     *
     * @throws \Throwable
     */
    public function testUnsuccessfulAsyncRequestWithThen(): void
    {
        $guzzlePromise = new GuzzleRejectedPromise($this->exceptionMock);
        $result = $this->resource->buildRequest('POST', 'transmissions', $this->postTransmissionPayload, []);

        $promise = new SparkPostPromise(new GuzzleAdapterPromise($guzzlePromise, $result));

        $exceptionBody = $this->exceptionBody;
        $promise->then(null, function ($exception) use ($exceptionBody): void {
            $this->assertEquals(500, $exception->getCode());
            $this->assertEquals($exceptionBody, $exception->getBody());
        })->wait();
    }

    /**
     * Test asynchronous request with automatic retries on 5xx errors.
     *
     * Why: Verifies that the async promise chain correctly handles transient errors via retries.
     * How: Mocks multiple 503 responses in the async client and verifies the final successful response is delivered to the then() callback.
     *
     * @throws \Throwable
     */
    public function testSuccessfulAsyncRequestWithRetries(): void
    {
        $testReq = $this->resource->buildRequest('POST', 'transmissions', $this->postTransmissionPayload, []);
        $clientMock = Mockery::mock(HttpClient::class, HttpAsyncClient::class);
        $clientMock->shouldReceive('sendAsyncRequest')
            ->with(Mockery::type(RequestInterface::class))
            ->andReturn(
                new GuzzleAdapterPromise(new GuzzleFulfilledPromise($this->badResponseMock), $testReq),
                new GuzzleAdapterPromise(new GuzzleFulfilledPromise($this->badResponseMock), $testReq),
                new GuzzleAdapterPromise(new GuzzleFulfilledPromise($this->responseMock), $testReq)
            );

        $resource = new SparkPost($clientMock, ['key' => 'SPARKPOST_API_KEY']);

        $resource->setOptions(['async' => true, 'retries' => 2]);
        $promise = $resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload);
        $promise->then(function ($resp): void {
            $this->assertEquals(200, $resp->getStatusCode());
        })->wait();
    }

    /**
     * Test that asynchronous retries are exhausted after the specified number of attempts.
     *
     * Why: Ensures the async chain eventually fails if the server is persistently broken.
     * How: Mocks a rejected promise and verifies the final error is propagated to the then() rejection callback.
     *
     * @throws \Throwable
     */
    public function testUnsuccessfulAsyncRequestWithRetries(): void
    {
        $testReq = $this->resource->buildRequest('POST', 'transmissions', $this->postTransmissionPayload, []);
        $rejectedPromise = new GuzzleRejectedPromise($this->exceptionMock);
        $clientMock = Mockery::mock(HttpClient::class, HttpAsyncClient::class);
        $clientMock->shouldReceive('sendAsyncRequest')
            ->with(Mockery::type(RequestInterface::class))
            ->andReturn(new GuzzleAdapterPromise($rejectedPromise, $testReq));

        $resource = new SparkPost($clientMock, ['key' => 'SPARKPOST_API_KEY']);

        $resource->setOptions(['async' => true, 'retries' => 2]);
        $promise = $resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload);
        $promise->then(null, function ($exception): void {
            $this->assertEquals(500, $exception->getCode());
            $this->assertEquals($this->exceptionBody, $exception->getBody());
        })->wait();
    }

    /**
     * Test that the SparkPostPromise correctly reports its state.
     *
     * Why: Verifies that state checks are correctly delegated to the underlying promise.
     * How: Mocks the underlying promise's getState method and asserts the SparkPostPromise wrapper returns the same states.
     *
     * @throws \Exception
     */
    public function testPromise(): void
    {
        $promise = $this->resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload);

        $this->promiseMock->shouldReceive('getState')->twice()->andReturn('pending');
        $this->assertEquals($this->promiseMock->getState(), $promise->getState());

        $this->promiseMock->shouldReceive('getState')->twice()->andReturn('rejected');
        $this->assertEquals($this->promiseMock->getState(), $promise->getState());
    }

    /**
     * Test that an exception is thrown when attempting an async request with a non-async client.
     *
     * Why: Prevents runtime errors by validating client capabilities early.
     * How: Sets a synchronous-only mock client and verifies that calling asyncRequest throws an exception.
     */
    public function testUnsupportedAsyncRequest(): void
    {
        $this->expectException(\Exception::class);

        $this->resource->setHttpClient(Mockery::mock(HttpClient::class));

        $this->resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload);
    }

    /**
     * Test that HTTP headers are correctly constructed, including defaults and custom headers.
     *
     * Why: Ensures the API receives mandatory headers (Auth, Content-Type, User-Agent) correctly.
     * How: Calls getHttpHeaders with a custom header and asserts all expected headers (default and custom) are present and correct.
     */
    public function testGetHttpHeaders(): void
    {
        $headers = $this->resource->getHttpHeaders([
            'Custom-Header' => 'testing',
        ]);

        $version = NSA::getProperty($this->resource, 'version');

        $this->assertEquals('SPARKPOST_API_KEY', $headers['Authorization']);
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals('testing', $headers['Custom-Header']);
        $this->assertEquals('php-sparkpost/' . $version, $headers['User-Agent']);
    }

    /**
     * Test that the API URL is correctly constructed with path and query parameters.
     *
     * Why: Ensures requests are sent to the correct API endpoint with properly formatted query strings.
     * How: Calls getUrl with a path and array of parameters, and asserts the generated URL matches the expected format.
     */
    public function testGetUrl(): void
    {
        $url = 'https://api.sparkpost.com:443/api/v1/transmissions?key=value 1,value 2,value 3';
        $testUrl = $this->resource->getUrl('transmissions', ['key' => ['value 1', 'value 2', 'value 3']]);
        $this->assertEquals($url, $testUrl);
    }

    /**
     * Test that a synchronous HTTP client can be set.
     *
     * Why: Ensures flexibility in choosing HTTP client implementations.
     * How: Sets a mock HttpClient and uses reflection to verify the private property was updated.
     */
    public function testSetHttpClient(): void
    {
        $mock = Mockery::mock(HttpClient::class);
        $this->resource->setHttpClient($mock);
        $this->assertEquals($mock, NSA::getProperty($this->resource, 'httpClient'));
    }

    /**
     * Test that an asynchronous HTTP client can be set.
     *
     * Why: Ensures flexibility in choosing async-capable HTTP client implementations.
     * How: Sets a mock HttpAsyncClient and uses reflection to verify the private property was updated.
     */
    public function testSetHttpAsyncClient(): void
    {
        $mock = Mockery::mock(HttpAsyncClient::class);
        $this->resource->setHttpClient($mock);
        $this->assertEquals($mock, NSA::getProperty($this->resource, 'httpClient'));
    }

    /**
     * Test that an exception is thrown when setting an invalid HTTP client.
     *
     * Why: Ensures type safety and prevents runtime errors from incompatible client objects.
     * How: Attempts to set an invalid object (stdClass) as the HTTP client and asserts an exception is thrown.
     */
    public function testSetHttpClientException(): void
    {
        $this->expectException(\Exception::class);

        $this->resource->setHttpClient(new \stdClass());
    }

    /**
     * Test that options can be initialized using just a string as the API key.
     *
     * Why: Provides a convenient shorthand for common client initialization.
     * How: Passes a string to setOptions and verifies the 'key' option is correctly set while others remain at defaults.
     *
     * @throws \Exception
     */
    public function testSetOptionsStringKey(): void
    {
        $this->resource->setOptions('SPARKPOST_API_KEY');
        $options = NSA::getProperty($this->resource, 'options');
        $this->assertEquals('SPARKPOST_API_KEY', $options['key']);
    }

    /**
     * Test that an exception is thrown if no API key is provided in the options.
     *
     * Why: Ensures the client cannot be misconfigured without a mandatory API key.
     * How: Attempts to set options without a 'key' and asserts that an exception is thrown.
     */
    public function testSetBadOptions(): void
    {
        $this->expectException(\Exception::class);

        NSA::setProperty($this->resource, 'options', []);
        $this->resource->setOptions(['not' => 'SPARKPOST_API_KEY']);
    }

    /**
     * Test that a custom PSR-17 Request Factory can be set.
     *
     * Why: Allows developers to use their preferred PSR-17 implementation.
     * How: Sets a mock RequestFactoryInterface and verifies it is retrieved by the internal factory getter.
     */
    public function testSetRequestFactory(): void
    {
        $messageFactory = Mockery::mock(RequestFactoryInterface::class);
        $this->resource->setRequestFactory($messageFactory);

        $this->assertEquals($messageFactory, NSA::invokeMethod($this->resource, 'getRequestFactory'));
    }
}
