<?php

/**
 * Openapi Data Mocker Server Middleware
 * PHP version 7.3
 *
 * @package OpenAPIServer\Mock
 * @link    https://github.com/ybelenko/openapi-data-mocker-server-middleware
 * @author  Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace OpenAPIServer\Mock;

use OpenAPIServer\Mock\OpenApiDataMockerRouteMiddleware;
use OpenAPIServer\Mock\OpenApiDataMocker;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PHPUnit\Framework\TestCase;
use StdClass;
use TypeError;

/**
 * OpenApiDataMockerRouteMiddlewareTest
 *
 * phpcs:disable Squiz.Commenting,Generic.Commenting,PEAR.Commenting
 * @coversDefaultClass \OpenAPIServer\Mock\OpenApiDataMockerRouteMiddleware
 */
class OpenApiDataMockerRouteMiddlewareTest extends TestCase
{
    /**
     * @covers ::__construct
     * @dataProvider provideConstructCorrectArguments
     */
    public function testConstructor(
        $mocker,
        $responses,
        $responseFactory,
        $getMockStatusCodeCallback,
        $afterCallback
    ) {
        $middleware = new OpenApiDataMockerRouteMiddleware($mocker, $responses, $responseFactory, $getMockStatusCodeCallback, $afterCallback);
        $this->assertInstanceOf(OpenApiDataMockerRouteMiddleware::class, $middleware);
        $this->assertNotNull($middleware);
    }

    public function provideConstructCorrectArguments()
    {
        $getMockStatusCodeCallback = function () {
            return false;
        };
        $afterCallback = function () {
            return false;
        };
        return [
            [new OpenApiDataMocker(), [], new Psr17Factory(), null, null],
            [new OpenApiDataMocker(), [], new Psr17Factory(), $getMockStatusCodeCallback, $afterCallback],
        ];
    }

    /**
     * @covers ::__construct
     * @dataProvider provideConstructInvalidArguments
     */
    public function testConstructorWithInvalidArguments(
        $mocker,
        $responses,
        $responseFactory,
        $getMockStatusCodeCallback,
        $afterCallback
    ) {
        $this->expectException(TypeError::class);
        $middleware = new OpenApiDataMockerRouteMiddleware($mocker, $responses, $responseFactory, $getMockStatusCodeCallback, $afterCallback);
    }

    public function provideConstructInvalidArguments()
    {
        return [
            'getMockStatusCodeCallback not callable' => [
                new OpenApiDataMocker(), [], new Psr17Factory(), 'foobar', null,
            ],
            'afterCallback not callable' => [
                new OpenApiDataMocker(), [], new Psr17Factory(), null, 'foobar',
            ],
            'responses not an array or object' => [
                new OpenApiDataMocker(), 'foobar', new Psr17Factory(), null, null,
            ],
        ];
    }

    /**
     * @covers ::process
     * @dataProvider provideProcessArguments
     */
    public function testProcess(
        $mocker,
        $responses,
        $responseFactory,
        $getMockStatusCodeCallback,
        $afterCallback,
        $request,
        $expectedStatusCode,
        $expectedHeaders,
        $notExpectedHeaders,
        $expectedBody
    ) {

        // Create a stub for the RequestHandlerInterface interface.
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willReturn($responseFactory->createResponse());

        $middleware = new OpenApiDataMockerRouteMiddleware(
            $mocker,
            $responses,
            $responseFactory,
            $getMockStatusCodeCallback,
            $afterCallback
        );
        $response = $middleware->process($request, $handler);

        // check status code
        $this->assertSame($expectedStatusCode, $response->getStatusCode());

        // check http headers in request
        foreach ($expectedHeaders as $expectedHeader => $expectedHeaderValue) {
            $this->assertTrue($response->hasHeader($expectedHeader));
            if ($expectedHeaderValue !== '*') {
                $this->assertSame($expectedHeaderValue, $response->getHeader($expectedHeader)[0]);
            }
        }
        foreach ($notExpectedHeaders as $notExpectedHeader) {
            $this->assertFalse($response->hasHeader($notExpectedHeader));
        }

        // check body
        if (is_array($expectedBody)) {
            // random values, check keys only
            foreach ($expectedBody as $attribute => $value) {
                $this->assertObjectHasAttribute($attribute, json_decode((string) $response->getBody(), false));
            }
        } else {
            $this->assertEquals($expectedBody, (string) $response->getBody());
        }
    }

    public function provideProcessArguments()
    {
        $mocker = new OpenApiDataMocker();
        $responseFactory = new Psr17Factory();
        $isMockResponseRequired = function (ServerRequestInterface $request) {
            $mockHttpHeader = 'X-OpenAPIServer-Mock';
            return $request->hasHeader($mockHttpHeader)
                && $request->getHeader($mockHttpHeader)[0] === 'ping';
        };

        $getMockStatusCodeCallback = function (ServerRequestInterface $request, $responses) use ($isMockResponseRequired) {
            if ($isMockResponseRequired($request)) {
                $responses = (array) $responses;
                if (array_key_exists('default', $responses)) {
                    return 'default';
                }

                // return status code of the first response
                return array_key_first($responses);
            }

            return false;
        };

        $afterCallback = function ($request, $response) use ($isMockResponseRequired) {
            if ($isMockResponseRequired($request)) {
                $response = $response->withHeader('X-OpenAPIServer-Mock', 'pong');
            }

            return $response;
        };

        $responses = [
            '400' => [
                'description' => 'Bad Request Response',
                'content' => new StdClass(),
            ],
            'default' => [
                'description' => 'Success Response',
                'headers' => [
                    'X-Location' => ['schema' => ['type' => 'string']],
                    'X-Created-Id' => ['schema' => ['type' => 'integer']],
                ],
                'content' => [
                    'application/json;encoding=utf-8' => ['schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'className' => ['type' => 'string'], 'declawed' => ['type' => 'boolean']]]],
                ],
            ],
        ];

        $responsesXmlOnly = [
            'default' => [
                'description' => 'Success Response',
                'content' => [
                    'application/xml' => [
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];

        $responsesObj = json_decode(
            '{
                "400": {
                    "description": "Bad Request Response",
                    "content": {}
                },
                "default": {
                    "description": "Success Response",
                    "headers": {
                        "X-Location": {
                            "schema": {
                                "type": "string"
                            }
                        },
                        "X-Created-Id": {
                            "schema": {
                                "type": "integer"
                            }
                        }
                    },
                    "content": {
                        "application/json;encoding=utf-8": {
                            "schema": {
                                "type": "object",
                                "properties": {
                                    "id": {
                                        "type": "integer"
                                    },
                                    "className": {
                                        "type": "string"
                                    },
                                    "declawed": {
                                        "type": "boolean"
                                    }
                                }
                            }
                        }
                    }
                }
            }'
        );

        return [
            'callbacks null' => [
                $mocker,
                $responses,
                $responseFactory,
                null,
                null,
                $responseFactory->createServerRequest('GET', '/phpunit'),
                200,
                [],
                ['X-OpenAPIServer-Mock', 'x-location', 'x-created-id'],
                '',
            ],
            'xml not supported' => [
                $mocker,
                $responsesXmlOnly,
                $responseFactory,
                $getMockStatusCodeCallback,
                $afterCallback,
                $responseFactory->createServerRequest('GET', '/phpunit')
                    ->withHeader('X-OpenAPIServer-Mock', 'ping'),
                200,
                ['X-OpenAPIServer-Mock' => 'pong', 'content-type' => '*/*'],
                ['x-location', 'x-created-id'],
                'Mock feature supports only "application/json" content-type!',
            ],
            'mock response default schema' => [
                $mocker,
                $responses,
                $responseFactory,
                $getMockStatusCodeCallback,
                $afterCallback,
                $responseFactory->createServerRequest('GET', '/phpunit')
                    ->withHeader('X-OpenAPIServer-Mock', 'ping'),
                200,
                ['X-OpenAPIServer-Mock' => 'pong', 'content-type' => 'application/json', 'x-location' => '*', 'x-created-id' => '*'],
                [],
                [
                    'id' => 1,
                    'className' => 'cat',
                    'declawed' => false,
                ],
            ],
            'mock response default schema with responses as object' => [
                $mocker,
                $responses,
                $responseFactory,
                $getMockStatusCodeCallback,
                $afterCallback,
                $responseFactory->createServerRequest('GET', '/phpunit')
                    ->withHeader('X-OpenAPIServer-Mock', 'ping'),
                200,
                ['X-OpenAPIServer-Mock' => 'pong', 'content-type' => 'application/json', 'x-location' => '*', 'x-created-id' => '*'],
                [],
                [
                    'id' => 1,
                    'className' => 'cat',
                    'declawed' => false,
                ],
            ],
        ];
    }

    /**
     * Covers issue @url https://github.com/ybelenko/openapi-data-mocker-server-middleware/issues/1
     *
     * @covers ::process
     * @dataProvider provideIssue1Arguments
     */
    public function testCallbackReturnFalse(
        $mocker,
        $responses,
        $responseFactory,
        $getMockStatusCodeCallback,
        $afterCallback,
        $request,
        $expectedStatusCode,
        $expectedHeaders,
        $notExpectedHeaders
    ) {
        // Create a stub for the RequestHandlerInterface interface.
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willReturn($responseFactory->createResponse());

        $middleware = new OpenApiDataMockerRouteMiddleware(
            $mocker,
            $responses,
            $responseFactory,
            $getMockStatusCodeCallback,
            $afterCallback
        );
        $response = $middleware->process($request, $handler);

        // check status code
        $this->assertSame($expectedStatusCode, $response->getStatusCode());

        // check http headers in request
        foreach ($expectedHeaders as $expectedHeader => $expectedHeaderValue) {
            // var_dump($expectedHeader);
            $this->assertTrue($response->hasHeader($expectedHeader), sprintf('Failed asserting that request contains header %s', $expectedHeader));
            // if ($expectedHeaderValue !== '*') {
            //     $this->assertSame($expectedHeaderValue, $response->getHeader($expectedHeader)[0]);
            // }
        }
        foreach ($notExpectedHeaders as $notExpectedHeader) {
            $this->assertFalse($response->hasHeader($notExpectedHeader));
        }
    }

    public function provideIssue1Arguments()
    {
        $mocker = new OpenApiDataMocker();
        $responseFactory = new Psr17Factory();

        $getMockStatusCodeCallback = function (ServerRequestInterface $request, array $responses) {
            // check if client clearly asks for mocked response
            $pingHeader = 'X-OpenAPIServer-Mock';
            $pingHeaderCode = 'X-OpenAPIServer-Mock';
            if (
                $request->hasHeader($pingHeader)
                && $request->getHeader($pingHeader)[0] === 'ping'
            ) {
                $responses = (array) $responses;
                $requestedResponseCode = ($request->hasHeader($pingHeaderCode)) ? $request->getHeader($pingHeaderCode)[0] : 'default';
                if (array_key_exists($requestedResponseCode, $responses)) {
                    return $requestedResponseCode;
                }

                // return first response key
                reset($responses);
                return key($responses);
            }

            return false;
        };

        $afterCallback = function (ServerRequestInterface $request, ResponseInterface $response) {
            // mark mocked response to distinguish real and fake responses
            return $response->withHeader('X-OpenAPIServer-Mock', 'pong');
        };

        $responses = [
            'default' => [
                'description' => 'Success Response',
                'headers' => [
                    'X-Location' => ['schema' => ['type' => 'string']],
                    'X-Created-Id' => ['schema' => ['type' => 'integer']],
                ],
                'content' => [
                    'application/json;encoding=utf-8' => ['schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'className' => ['type' => 'string'], 'declawed' => ['type' => 'boolean']]]],
                ],
            ],
        ];

        return [
            'issue #1 feature enabled' => [
                $mocker,
                $responses,
                $responseFactory,
                $getMockStatusCodeCallback,
                $afterCallback,
                $responseFactory->createServerRequest('GET', '/phpunit')
                    ->withHeader('X-OpenAPIServer-Mock', 'ping'),
                200,
                ['X-OpenAPIServer-Mock' => 'pong', 'content-type' => 'application/json', 'x-location' => '*', 'x-created-id' => '*'],
                [],
            ],
            'issue #1 without mock header' => [
                $mocker,
                $responses,
                $responseFactory,
                $getMockStatusCodeCallback,
                $afterCallback,
                $responseFactory->createServerRequest('GET', '/phpunit'),
                200,
                [],
                ['X-OpenAPIServer-Mock', 'x-location', 'x-created-id'],
            ],
        ];
    }
}
