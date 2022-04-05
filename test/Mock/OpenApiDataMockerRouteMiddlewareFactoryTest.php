<?php

/**
 * Openapi Data Mocker Server Middleware
 * PHP version 7.4
 *
 * @package OpenAPIServer\Mock
 * @link    https://github.com/ybelenko/openapi-data-mocker-server-middleware
 * @author  Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace OpenAPIServer\Mock;

use OpenAPIServer\Mock\OpenApiDataMockerRouteMiddleware;
use OpenAPIServer\Mock\OpenApiDataMockerRouteMiddlewareFactory;
use OpenAPIServer\Mock\OpenApiDataMocker;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

/**
 * OpenApiDataMockerRouteMiddlewareFactoryTest
 *
 * phpcs:disable Squiz.Commenting,Generic.Commenting,PEAR.Commenting
 * @coversDefaultClass \OpenAPIServer\Mock\OpenApiDataMockerRouteMiddlewareFactory
 */
class OpenApiDataMockerRouteMiddlewareFactoryTest extends TestCase
{
    /**
     * @covers ::__construct
     * @dataProvider provideConstructCorrectArguments
     */
    public function testConstructor(
        $mocker,
        $responseFactory,
        $getMockStatusCodeCallback,
        $afterCallback
    ) {
        $factory = new OpenApiDataMockerRouteMiddlewareFactory($mocker, $responseFactory, $getMockStatusCodeCallback, $afterCallback);
        $this->assertInstanceOf(OpenApiDataMockerRouteMiddlewareFactory::class, $factory);
        $this->assertNotNull($factory);
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
            [new OpenApiDataMocker(), new Psr17Factory(), null, null],
            [new OpenApiDataMocker(), new Psr17Factory(), $getMockStatusCodeCallback, $afterCallback],
        ];
    }

    /**
     * @covers ::create
     * @dataProvider provideCreateCorrectArguments
     */
    public function testCreate($responses)
    {
        $getMockStatusCodeCallback = function () {
            return false;
        };
        $afterCallback = function () {
            return false;
        };
        $factory = new OpenApiDataMockerRouteMiddlewareFactory(new OpenApiDataMocker(), new Psr17Factory(), $getMockStatusCodeCallback, $afterCallback);
        $middleware = $factory->create($responses);
        $this->assertInstanceOf(OpenApiDataMockerRouteMiddleware::class, $middleware);
    }

    public function provideCreateCorrectArguments()
    {
        return [
            'responses as empty array' => [
                [],
            ],
        ];
    }
}
