<?php
namespace Aws\Test\Handler\GuzzleV6;

use Aws\Handler\GuzzleV6\GuzzleHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\RejectionException;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * @covers Aws\Handler\GuzzleV6\GuzzleHandler
 */
class HandlerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        if (!class_exists('GuzzleHttp\HandlerStack')) {
            $this->markTestSkipped();
        }
    }

    public function testHandlerWorksWithSuccessfulRequest()
    {
        $mock = new MockHandler([new Response(200, [], Psr7\stream_for('foo'))]);
        $client = new Client(['handler' => $mock]);
        $handler = new GuzzleHandler($client);

        $request = new Request('PUT', 'http://example.com', [], '{}');
        $promise = $handler($request, ['delay' => 500]);
        $this->assertInstanceOf('GuzzleHttp\\Promise\\PromiseInterface', $promise);

        /** @var $response Response */
        $response = $promise->wait();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('foo', $response->getBody()->getContents());
    }

    public function testHandlerWorksWithFailedRequest()
    {
        $wasRejected = false;
        $request = new Request('PUT', 'http://example.com');
        $mock = new MockHandler([new RequestException(
            'message',
            $request,
            new Response('500')
        )]);
        $client = new Client(['handler' => $mock]);
        $handler = new GuzzleHandler($client);

        $promise = $handler(new Request('PUT', 'http://example.com'));
        $promise->then(null, function (array $error) use (&$wasRejected) {
            $wasRejected = true;
        });

        try {
            $promise->wait();
            $this->fail('An exception should have been thrown.');
        } catch (RejectionException $e) {
            $error = $e->getReason();
            $this->assertInstanceOf(RequestException::class, $error['exception']);
            $this->assertFalse($error['connection_error']);
            $this->assertInstanceOf(Response::class, $error['response']);
            $this->assertEquals(500, $error['response']->getStatusCode());
        }

        $this->assertTrue($wasRejected, 'Reject callback was not triggered.');
    }
}
