# Openapi Data Mocker Server Middleware

[PSR-15](https://www.php-fig.org/psr/psr-15/) HTTP Server Middleware to create mock responses from [OpenAPI Schemas](https://github.com/OAI/OpenAPI-Specification/blob/master/versions/3.0.3.md)(OAS 3.0). This package was an enhancement of PHP Slim4 server in [OpenAPI Generator](https://github.com/OpenAPITools/openapi-generator) project, but it easier to maintain it in separated repo.

## Requirements

* PHP ^7.2

## Installation via Composer

Run in terminal:

```console
composer require ybelenko/openapi-data-mocker-server-middleware
```

## Constructor Arguments

1. `$mocker: OpenApiDataMockerInterface`
    + is mocker class instance. To create custom data mocker extend `OpenAPIServer\Mock\OpenApiDataMockerInterface`.
2. `$responses: array`
    + Array with OAS3 response definitions of applied route. Check [examples/get_mock_responses.php](examples/get_mock_responses.php) file.
3. `$responseFactory: ResponseFactoryInterface`
    + Any PSR-17 compliant response factory. [PSR-17: HTTP Factories - 2.2 ResponseFactoryInterface](https://www.php-fig.org/psr/psr-17/#22-responsefactoryinterface)
4. `$getMockStatusCodeCallback: callable|null = null`
    + is callback before mock data generation. Below example shows how to enable mock feature for only requests with `X-OpenAPIServer-Mock: ping` HTTP header. Adjust requests filtering to fit your project requirements. This function must return single response schema from `$responses` array parameter. **Mock feature is disabled when callback returns anything beside existent key from `$responses` array, eg `'default'` or `200`.**
5. `$afterCallback: callable|null = null`
    + is callback executed after mock data generation. Most obvious use case is append specific HTTP headers to distinguish real and fake responses. **This function must always return response instance.**

## Usage Example

```php
require_once __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use OpenAPIServer\Mock\OpenApiDataMockerRouteMiddleware;
use OpenAPIServer\Mock\OpenApiDataMocker;

// initiate Slim app or any other PSR-15 compliant framework
$app = AppFactory::create();

// create new mocker instance
$mocker = new OpenApiDataMocker();

// responses OAS3.0 definition of GET /mock operation
// check examples/get_mock_responses.php file
$responsesForGetMockRoute = require_once(__DIR__ . '/get_mock_responses.php');

// initiate any PSR-17 compliant response factory
$responseFactory = AppFactory::determineResponseFactory();

// optional response schema selector
$getMockStatusCodeCallback = function (ServerRequestInterface $request, $responses) {
    // check if client clearly asks for mocked response
    if (
        $request->hasHeader('X-OpenAPIServer-Mock')
        && $request->getHeader('X-OpenAPIServer-Mock')[0] === 'ping'
    ) {
        $responses = (array) $responses;
        if (array_key_exists('default', $responses)) {
            return 'default';
        }

        // return first response key
        reset($responses);
        return key($responses);
    }
    return false;
};

// optional after middleware callback
$afterCallback = function (ServerRequestInterface $request, ResponseInterface $response) {
    // mark mocked response to distinguish real and fake responses
    return $response->withHeader('X-OpenAPIServer-Mock', 'pong');
};

// create middleware itself
$mw = new OpenApiDataMockerRouteMiddleware(
    $mocker,
    $responsesForGetMockRoute,
    $responseFactory,
    $getMockStatusCodeCallback,
    $afterCallback
);

// this package is route middleware, apply it to route as described in Slim docs:
// https://www.slimframework.com/docs/v4/concepts/middleware.html#route-middleware
$app->get('/mock', function (ServerRequestInterface $request, ResponseInterface $response) {
    $response->getBody()->write('Hello ');

    return $response;
})->add($mw);

$app->run();

// let's assume you started builtin PHP server
// which you MUST NOT use on production:
// $ php -S localhost:8888 examples/slim_example.php

// finally you can check output with curl library
// $ curl http://localhost:8888/mock
// Hello

// $ curl http://localhost:8888/mock -H 'X-OpenAPIServer-Mock: ping'
```
