<?php

namespace SparkPost\Test;

use PHPUnit\Framework\TestCase;
use SparkPost\SparkPost;
use Psr\Http\Client\ClientInterface;
use Mockery;

class IssueReproductionTest extends TestCase
{
    public function testJsonEncodeFailure()
    {
        $clientMock = Mockery::mock(ClientInterface::class);
        $sparkpost = new SparkPost($clientMock, ['key' => 'test-key']);

        // Invalid UTF-8 sequence to make json_encode return false
        $payload = ['invalid' => "\xB1\x31"];

        // We expect an Exception because json_encode fails
        try {
            $sparkpost->buildRequest('POST', 'test', $payload, []);
            $this->fail('Expected \Exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('JSON encoding error', $e->getMessage());
        }
    }
}
