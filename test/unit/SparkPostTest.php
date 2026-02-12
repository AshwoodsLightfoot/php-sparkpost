<?php

namespace SparkPost\Test;

use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Http\Message\MessageFactory;
use Nyholm\NSA;
use PHPUnit\Framework\TestCase;
use SparkPost\SparkPost;
use SparkPost\SparkPostPromise;
use GuzzleHttp\Promise\FulfilledPromise as GuzzleFulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise as GuzzleRejectedPromise;
use Http\Adapter\Guzzle6\Promise as GuzzleAdapterPromise;
use Mockery;

class SparkPostTest extends TestCase
{
    public $badResponseBody;
    public $badResponseMock;
    private $clientMock;
    /** @var SparkPost */
    private \SparkPost\SparkPost $resource;

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

    public function setUp(): void
    {
        // response mock up
        $responseBodyMock = Mockery::mock(\Psr\Http\Message\StreamInterface::class);
        $this->responseBody = ['results' => 'yay'];
        $this->responseMock = Mockery::mock(\Psr\Http\Message\ResponseInterface::class);
        $this->responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $this->responseMock->shouldReceive('getBody')->andReturn($responseBodyMock);
        $responseBodyMock->shouldReceive('__toString')->andReturn(json_encode($this->responseBody));

        $errorBodyMock = Mockery::mock(\Psr\Http\Message\StreamInterface::class);
        $this->badResponseBody = ['errors' => []];
        $this->badResponseMock = Mockery::mock(\Psr\Http\Message\ResponseInterface::class);
        $this->badResponseMock->shouldReceive('getStatusCode')->andReturn(503);
        $this->badResponseMock->shouldReceive('getBody')->andReturn($errorBodyMock);
        $errorBodyMock->shouldReceive('__toString')->andReturn(json_encode($this->badResponseBody));

        // exception mock up
        $exceptionResponseMock = Mockery::mock(\Psr\Http\Message\ResponseInterface::class);
        $exceptionResponseBodyMock = Mockery::mock(\Psr\Http\Message\StreamInterface::class);
        $this->exceptionBody = ['results' => 'failed'];
        $this->exceptionMock = Mockery::mock(\Http\Client\Exception\HttpException::class);
        $this->exceptionMock->shouldReceive('getResponse')->andReturn($exceptionResponseMock);
        $exceptionResponseMock->shouldReceive('getStatusCode')->andReturn(500);
        $exceptionResponseMock->shouldReceive('getBody')->andReturn($exceptionResponseBodyMock);
        $exceptionResponseBodyMock->shouldReceive('__toString')->andReturn(json_encode($this->exceptionBody));

        // promise mock up
        $this->promiseMock = Mockery::mock(\Http\Promise\Promise::class);

        //setup mock for the adapter
        $this->clientMock = Mockery::mock(\Http\Adapter\Guzzle6\Client::class);
        $this->clientMock->shouldReceive('sendAsyncRequest')->
        with(Mockery::type(\GuzzleHttp\Psr7\Request::class))->
        andReturn($this->promiseMock);

        $this->resource = new SparkPost($this->clientMock, ['key' => 'SPARKPOST_API_KEY']);
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testRequestSync(): void
    {
        $this->resource->setOptions(['async' => false]);
        $this->clientMock->shouldReceive('sendRequest')->andReturn($this->responseMock);

        $this->assertInstanceOf(
            \SparkPost\SparkPostResponse::class,
            $this->resource->request('POST', 'transmissions', $this->postTransmissionPayload)
        );
    }

    public function testRequestAsync(): void
    {
        $promiseMock = Mockery::mock(\Http\Promise\Promise::class);
        $this->resource->setOptions(['async' => true]);
        $this->clientMock->shouldReceive('sendAsyncRequest')->andReturn($promiseMock);

        $this->assertInstanceOf(
            \SparkPost\SparkPostPromise::class,
            $this->resource->request('GET', 'transmissions', $this->getTransmissionPayload)
        );
    }

    public function testDebugOptionWhenFalse(): void
    {
        $this->resource->setOptions(['async' => false, 'debug' => false]);
        $this->clientMock->shouldReceive('sendRequest')->andReturn($this->responseMock);

        $response = $this->resource->request('POST', 'transmissions', $this->postTransmissionPayload);

        $this->assertEquals($response->getRequest(), null);
    }

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

    public function testSuccessfulSyncRequest(): void
    {
        $this->clientMock->shouldReceive('sendRequest')->
        once()->
        with(Mockery::type(\GuzzleHttp\Psr7\Request::class))->
        andReturn($this->responseMock);

        $response = $this->resource->syncRequest('POST', 'transmissions', $this->postTransmissionPayload);

        $this->assertEquals($this->responseBody, $response->getBodyDecoded());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUnsuccessfulSyncRequest(): void
    {
        $this->clientMock->shouldReceive('sendRequest')->
        once()->
        with(Mockery::type(\GuzzleHttp\Psr7\Request::class))->
        andThrow($this->exceptionMock);

        try {
            $this->resource->syncRequest('POST', 'transmissions', $this->postTransmissionPayload);
        } catch (\Exception $e) {
            $this->assertEquals($this->exceptionBody, $e->getBody());
            $this->assertEquals(500, $e->getCode());
        }
    }

    public function testSuccessfulSyncRequestWithRetries(): void
    {
        $this->clientMock->shouldReceive('sendRequest')->
        with(Mockery::type(\GuzzleHttp\Psr7\Request::class))->
        andReturn($this->badResponseMock, $this->badResponseMock, $this->responseMock);

        $this->resource->setOptions(['retries' => 2]);
        $response = $this->resource->syncRequest('POST', 'transmissions', $this->postTransmissionPayload);

        $this->assertEquals($this->responseBody, $response->getBodyDecoded());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUnsuccessfulSyncRequestWithRetries(): void
    {
        $this->clientMock->shouldReceive('sendRequest')->
        once()->
        with(Mockery::type(\GuzzleHttp\Psr7\Request::class))->
        andThrow($this->exceptionMock);

        $this->resource->setOptions(['retries' => 2]);
        try {
            $this->resource->syncRequest('POST', 'transmissions', $this->postTransmissionPayload);
        } catch (\Exception $e) {
            $this->assertEquals($this->exceptionBody, $e->getBody());
            $this->assertEquals(500, $e->getCode());
        }
    }

    public function testSuccessfulAsyncRequestWithWait(): void
    {
        $this->promiseMock->shouldReceive('wait')->andReturn($this->responseMock);

        $promise = $this->resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload);
        $response = $promise->wait();

        $this->assertEquals($this->responseBody, $response->getBodyDecoded());
        $this->assertEquals(200, $response->getStatusCode());
    }

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

    public function testSuccessfulAsyncRequestWithThen(): void
    {
        $guzzlePromise = new GuzzleFulfilledPromise($this->responseMock);
        $result = $this->resource->buildRequest('POST', 'transmissions', $this->postTransmissionPayload, []);

        $promise = new SparkPostPromise(new GuzzleAdapterPromise($guzzlePromise, $result));

        $responseBody = $this->responseBody;
        $promise->then(function ($response) use ($responseBody): void {
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals($responseBody, $response->getBodyDecoded());
        }, null)->wait();
    }

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

    public function testSuccessfulAsyncRequestWithRetries(): void
    {
        $testReq = $this->resource->buildRequest('POST', 'transmissions', $this->postTransmissionPayload, []);
        $clientMock = Mockery::mock(\Http\Adapter\Guzzle6\Client::class);
        $clientMock->shouldReceive('sendAsyncRequest')->
        with(Mockery::type(\GuzzleHttp\Psr7\Request::class))->
        andReturn(
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

    public function testUnsuccessfulAsyncRequestWithRetries(): void
    {
        $testReq = $this->resource->buildRequest('POST', 'transmissions', $this->postTransmissionPayload, []);
        $rejectedPromise = new GuzzleRejectedPromise($this->exceptionMock);
        $clientMock = Mockery::mock(\Http\Adapter\Guzzle6\Client::class);
        $clientMock->shouldReceive('sendAsyncRequest')->
        with(Mockery::type(\GuzzleHttp\Psr7\Request::class))->
        andReturn(new GuzzleAdapterPromise($rejectedPromise, $testReq));

        $resource = new SparkPost($clientMock, ['key' => 'SPARKPOST_API_KEY']);

        $resource->setOptions(['async' => true, 'retries' => 2]);
        $promise = $resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload);
        $promise->then(null, function ($exception): void {
            $this->assertEquals(500, $exception->getCode());
            $this->assertEquals($this->exceptionBody, $exception->getBody());
        })->wait();
    }

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

        $this->resource->setHttpClient(Mockery::mock(\Http\Client\HttpClient::class));

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

    public function testSetMessageFactory(): void
    {
        $messageFactory = Mockery::mock(MessageFactory::class);
        $this->resource->setMessageFactory($messageFactory);

        $this->assertEquals($messageFactory, NSA::invokeMethod($this->resource, 'getMessageFactory'));
    }
}
