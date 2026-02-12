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

    public function testGetProtocolVersion(): void
    {
        $this->responseMock->shouldReceive('getProtocolVersion')->andReturn($this->returnValue);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->getProtocolVersion(), $sparkpostResponse->getProtocolVersion());
    }

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

    public function testGetHeaders(): void
    {
        $this->responseMock->shouldReceive('getHeaders')->andReturn([]);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->getHeaders(), $sparkpostResponse->getHeaders());
    }

    public function testHasHeader(): void
    {
        $param = 'header';

        $this->responseMock->shouldReceive('hasHeader')->andReturn($this->returnValue);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->hasHeader($param), $sparkpostResponse->hasHeader($param));
    }

    public function testGetHeader(): void
    {
        $param = 'header';

        $this->responseMock->shouldReceive('getHeader')->andReturn([]);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->getHeader($param), $sparkpostResponse->getHeader($param));
    }

    public function testGetHeaderLine(): void
    {
        $param = 'header';

        $this->responseMock->shouldReceive('getHeaderLine')->andReturn($this->returnValue);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->getHeaderLine($param), $sparkpostResponse->getHeaderLine($param));
    }

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

    public function testWithoutHeader(): void
    {
        $param = 'header';
        $messageMock = Mockery::mock(MessageInterface::class);

        $this->responseMock->shouldReceive('withoutHeader')->andReturn($messageMock);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->withoutHeader($param), $sparkpostResponse->withoutHeader($param));
    }

    public function testGetRequest(): void
    {
        $request = ['some' => 'request'];
        $this->responseMock->shouldReceive('getRequest')->andReturn($request);
        $sparkpostResponse = new SparkPostResponse($this->responseMock, $request);
        $this->assertEquals($sparkpostResponse->getRequest(), $request);
    }

    public function testWithBody(): void
    {
        $param = Mockery::mock(StreamInterface::class);
        $messageMock = Mockery::mock(MessageInterface::class);

        $this->responseMock->shouldReceive('withBody')->andReturn($messageMock);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->withBody($param), $sparkpostResponse->withBody($param));
    }

    public function testGetStatusCode(): void
    {
        $this->responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->getStatusCode(), $sparkpostResponse->getStatusCode());
    }

    public function testWithStatus(): void
    {
        $param = 200;

        $this->responseMock->shouldReceive('withStatus')->andReturn($this->responseMock);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->withStatus($param), $sparkpostResponse->withStatus($param));
    }

    public function testGetReasonPhrase(): void
    {
        $this->responseMock->shouldReceive('getReasonPhrase')->andReturn($this->returnValue);
        $sparkpostResponse = new SparkPostResponse($this->responseMock);
        $this->assertEquals($this->responseMock->getReasonPhrase(), $sparkpostResponse->getReasonPhrase());
    }
}
