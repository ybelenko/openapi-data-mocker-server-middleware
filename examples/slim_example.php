<?php

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
// check get_mock_responses.php file in same folder
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
// $ php -S localhost:8888 slim_example.php

// finally you can check output with curl library
// $ curl http://localhost:8888/mock
// Hello

// $ curl http://localhost:8888/mock -H 'X-OpenAPIServer-Mock: ping'
// {"status_code":999932170,"message":"Lorem ipsum dolor sit amet,"}
