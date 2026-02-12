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

    public function testUnsupportedAsyncRequest(): void
    {
        $this->expectException(\Exception::class);

        $this->resource->setHttpClient(Mockery::mock(HttpClient::class));

        $this->resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload);
    }

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

    public function testGetUrl(): void
    {
        $url = 'https://api.sparkpost.com:443/api/v1/transmissions?key=value 1,value 2,value 3';
        $testUrl = $this->resource->getUrl('transmissions', ['key' => ['value 1', 'value 2', 'value 3']]);
        $this->assertEquals($url, $testUrl);
    }

    public function testSetHttpClient(): void
    {
        $mock = Mockery::mock(HttpClient::class);
        $this->resource->setHttpClient($mock);
        $this->assertEquals($mock, NSA::getProperty($this->resource, 'httpClient'));
    }

    public function testSetHttpAsyncClient(): void
    {
        $mock = Mockery::mock(HttpAsyncClient::class);
        $this->resource->setHttpClient($mock);
        $this->assertEquals($mock, NSA::getProperty($this->resource, 'httpClient'));
    }

    public function testSetHttpClientException(): void
    {
        $this->expectException(\Exception::class);

        $this->resource->setHttpClient(new \stdClass());
    }

    /**
     * @throws \Exception
     */
    public function testSetOptionsStringKey(): void
    {
        $this->resource->setOptions('SPARKPOST_API_KEY');
        $options = NSA::getProperty($this->resource, 'options');
        $this->assertEquals('SPARKPOST_API_KEY', $options['key']);
    }

    public function testSetBadOptions(): void
    {
        $this->expectException(\Exception::class);

        NSA::setProperty($this->resource, 'options', []);
        $this->resource->setOptions(['not' => 'SPARKPOST_API_KEY']);
    }

    public function testSetRequestFactory(): void
    {
        $messageFactory = Mockery::mock(RequestFactoryInterface::class);
        $this->resource->setRequestFactory($messageFactory);

        $this->assertEquals($messageFactory, NSA::invokeMethod($this->resource, 'getRequestFactory'));
    }
}
