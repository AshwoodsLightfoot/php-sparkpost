<?php

namespace SparkPost\Test;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SparkPost\SparkPostResponse;
use Mockery;

class SparkPostResponseTest extends TestCase
{
    /** @var Mockery\MockInterface|ResponseInterface */
    private $responseMock;
    /** @var string */
    private string $returnValue;

    public function setUp(): void
    {
        $this->returnValue = 'some_value_to_return';
        $this->responseMock = Mockery::mock(ResponseInterface::class);
    }

    /**
     * Test that getProtocolVersion returns the protocol version from the wrapped response.
     *
     * Why: Ensures that the SparkPostResponse correctly delegates the call to the underlying PSR-7 response object.
     * How: Mocks ResponseInterface, sets expectation for getProtocolVersion, and verifies the wrapper returns the same value.
     */
    public function testGetProtocolVersion(): void
    {
        $this->responseMock->shouldReceive('getProtocolVersion')->andReturn($this->returnValue);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->getProtocolVersion(), $sparkpostResponse->getProtocolVersion());
    }

    /**
     * Test that withProtocolVersion returns a new instance with the specified protocol version.
     *
     * Why: Ensures that the SparkPostResponse correctly delegates the call to the underlying PSR-7 response object.
     * How: Mocks ResponseInterface, sets expectation for withProtocolVersion, and verifies the returned object matches the mock result.
     */
    public function testWithProtocolVersion(): void
    {
        $param = 'protocol version';
        $messageMock = Mockery::mock(MessageInterface::class);

        $this->responseMock->shouldReceive('withProtocolVersion')->andReturn($messageMock);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals(
            $this->responseMock->withProtocolVersion($param),
            $sparkpostResponse->withProtocolVersion($param)
        );
    }

    /**
     * Test that getHeaders returns the headers from the wrapped response.
     *
     * Why: Ensures that the SparkPostResponse correctly delegates the call to the underlying PSR-7 response object.
     * How: Mocks ResponseInterface, sets expectation for getHeaders, and verifies the wrapper returns the same array.
     */
    public function testGetHeaders(): void
    {
        $this->responseMock->shouldReceive('getHeaders')->andReturn([]);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->getHeaders(), $sparkpostResponse->getHeaders());
    }

    /**
     * Test that hasHeader returns whether a header exists in the wrapped response.
     *
     * Why: Ensures that the SparkPostResponse correctly delegates the call to the underlying PSR-7 response object.
     * How: Mocks ResponseInterface, sets expectation for hasHeader, and verifies the wrapper returns the expected boolean/value.
     */
    public function testHasHeader(): void
    {
        $param = 'header';

        $this->responseMock->shouldReceive('hasHeader')->andReturn($this->returnValue);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->hasHeader($param), $sparkpostResponse->hasHeader($param));
    }

    /**
     * Test that getHeader returns the specified header from the wrapped response.
     *
     * Why: Ensures that the SparkPostResponse correctly delegates the call to the underlying PSR-7 response object.
     * How: Mocks ResponseInterface, sets expectation for getHeader, and verifies the wrapper returns the same array.
     */
    public function testGetHeader(): void
    {
        $param = 'header';

        $this->responseMock->shouldReceive('getHeader')->andReturn([]);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->getHeader($param), $sparkpostResponse->getHeader($param));
    }

    /**
     * Test that getHeaderLine returns the specified header line from the wrapped response.
     *
     * Why: Ensures that the SparkPostResponse correctly delegates the call to the underlying PSR-7 response object.
     * How: Mocks ResponseInterface, sets expectation for getHeaderLine, and verifies the wrapper returns the same string.
     */
    public function testGetHeaderLine(): void
    {
        $param = 'header';

        $this->responseMock->shouldReceive('getHeaderLine')->andReturn($this->returnValue);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->getHeaderLine($param), $sparkpostResponse->getHeaderLine($param));
    }

    /**
     * Test that withHeader returns a new instance with the specified header.
     *
     * Why: Ensures that the SparkPostResponse correctly delegates the call to the underlying PSR-7 response object.
     * How: Mocks ResponseInterface, sets expectation for withHeader, and verifies the returned object matches the mock result.
     */
    public function testWithHeader(): void
    {
        $param = 'header';
        $param2 = 'value';
        $messageMock = Mockery::mock(MessageInterface::class);

        $this->responseMock->shouldReceive('withHeader')->andReturn($messageMock);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals(
            $this->responseMock->withHeader($param, $param2),
            $sparkpostResponse->withHeader($param, $param2)
        );
    }

    /**
     * Test that withAddedHeader returns a new instance with the specified header added.
     *
     * Why: Ensures that the SparkPostResponse correctly delegates the call to the underlying PSR-7 response object.
     * How: Mocks ResponseInterface, sets expectation for withAddedHeader, and verifies the returned object matches the mock result.
     */
    public function testWithAddedHeader(): void
    {
        $param = 'header';
        $param2 = 'value';
        $messageMock = Mockery::mock(MessageInterface::class);

        $this->responseMock->shouldReceive('withAddedHeader')->andReturn($messageMock);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals(
            $this->responseMock->withAddedHeader($param, $param2),
            $sparkpostResponse->withAddedHeader($param, $param2)
        );
    }

    /**
     * Test that withoutHeader returns a new instance without the specified header.
     *
     * Why: Ensures that the SparkPostResponse correctly delegates the call to the underlying PSR-7 response object.
     * How: Mocks ResponseInterface, sets expectation for withoutHeader, and verifies the returned object matches the mock result.
     */
    public function testWithoutHeader(): void
    {
        $param = 'header';
        $messageMock = Mockery::mock(MessageInterface::class);

        $this->responseMock->shouldReceive('withoutHeader')->andReturn($messageMock);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->withoutHeader($param), $sparkpostResponse->withoutHeader($param));
    }

    /**
     * Test that getRequest returns the request data passed during construction.
     *
     * Why: Ensures that the SparkPostResponse correctly stores and retrieves the request metadata used for the API call.
     * How: Initializes SparkPostResponse with a request array and verifies getRequest() returns that same array.
     */
    public function testGetRequest(): void
    {
        $request = ['some' => 'request'];
        $this->responseMock->shouldReceive('getRequest')->andReturn($request);
        $sparkpostResponse = new SparkPostResponse($this->responseMock, $request);
        $this->assertEquals($sparkpostResponse->getRequest(), $request);
    }

    /**
     * Test that withBody returns a new instance with the specified body.
     *
     * Why: Ensures that the SparkPostResponse correctly delegates the call to the underlying PSR-7 response object.
     * How: Mocks ResponseInterface, sets expectation for withBody, and verifies the returned object matches the mock result.
     */
    public function testWithBody(): void
    {
        $param = Mockery::mock(StreamInterface::class);
        $messageMock = Mockery::mock(MessageInterface::class);

        $this->responseMock->shouldReceive('withBody')->andReturn($messageMock);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->withBody($param), $sparkpostResponse->withBody($param));
    }

    /**
     * Test that getStatusCode returns the status code from the wrapped response.
     *
     * Why: Ensures that the SparkPostResponse correctly delegates the call to the underlying PSR-7 response object.
     * How: Mocks ResponseInterface, sets expectation for getStatusCode, and verifies the wrapper returns the same integer.
     */
    public function testGetStatusCode(): void
    {
        $this->responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->getStatusCode(), $sparkpostResponse->getStatusCode());
    }

    /**
     * Test that withStatus returns a new instance with the specified status code.
     *
     * Why: Ensures that the SparkPostResponse correctly delegates the call to the underlying PSR-7 response object.
     * How: Mocks ResponseInterface, sets expectation for withStatus, and verifies the returned object matches the mock result.
     */
    public function testWithStatus(): void
    {
        $param = 200;

        $this->responseMock->shouldReceive('withStatus')->andReturn($this->responseMock);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->withStatus($param), $sparkpostResponse->withStatus($param));
    }

    /**
     * Test that getReasonPhrase returns the reason phrase from the wrapped response.
     *
     * Why: Ensures that the SparkPostResponse correctly delegates the call to the underlying PSR-7 response object.
     * How: Mocks ResponseInterface, sets expectation for getReasonPhrase, and verifies the wrapper returns the same string.
     */
    public function testGetReasonPhrase(): void
    {
        $this->responseMock->shouldReceive('getReasonPhrase')->andReturn($this->returnValue);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->getReasonPhrase(), $sparkpostResponse->getReasonPhrase());
    }
}
