<?php

/**
 * Openapi Data Mocker Server Middleware
 * PHP version 7.1
 *
 * @package OpenAPIServer\Mock
 * @link    https://github.com/ybelenko/openapi-data-mocker-server-middleware
 * @author  Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace OpenAPIServer\Mock;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use OpenAPIServer\Mock\OpenApiDataMockerInterface;
use InvalidArgumentException;

/**
 * OpenApiDataMockerRouteMiddleware
 */
final class OpenApiDataMockerRouteMiddleware implements MiddlewareInterface
{
    // I don't think class variables requires complete doc blocks
    // phpcs:disable Generic.Commenting.DocComment.MissingShort,Generic.Commenting.DocComment.ContentAfterOpen,Generic.Commenting.DocComment.ContentBeforeClose

    /** @var OpenApiDataMockerInterface DataMocker. */
    private $mocker;

    /** @var array|object Array or object with responses schemas. */
    private $responses;

    /** @var ResponseFactoryInterface Factory to create new response instance. */
    private $responseFactory;

    /** @var callable|null Custom callback to select mocked response. */
    private $getMockStatusCodeCallback;

    /** @var callable|null Custom after callback. */
    private $afterCallback;

    // turn sniffs back on
    // phpcs:enable

    /**
     * Class constructor.
     *
     * @param OpenApiDataMockerInterface $mocker                    DataMocker.
     * @param array|object               $responses                 Array or object with responses schemas.
     * @param ResponseFactoryInterface   $responseFactory           Factory to create new response instance.
     * @param callable|null              $getMockStatusCodeCallback Custom callback to select mocked response.
     *                                                              Mock feature is disabled when this argument is null.
     * @param callable|null              $afterCallback             After callback.
     *                                                              Function must return response instance.
     *
     * @example https://github.com/ybelenko/openapi-data-mocker-server-middleware/blob/master/examples/slim_example.php
     *
     * @throws \InvalidArgumentException When invalid arguments provided.
     */
    public function __construct(
        OpenApiDataMockerInterface $mocker,
        $responses,
        ResponseFactoryInterface $responseFactory,
        $getMockStatusCodeCallback = null,
        $afterCallback = null
    ) {
        $this->mocker = $mocker;
        if (is_object($responses) || is_array($responses)) {
            $this->responses = (array) $responses;
        } else {
            throw new InvalidArgumentException('\$responses must be array or object');
        }

        $this->responseFactory = $responseFactory;
        if (is_callable($getMockStatusCodeCallback)) {
            $this->getMockStatusCodeCallback = $getMockStatusCodeCallback;
        } elseif ($getMockStatusCodeCallback !== null) {
            // wrong argument type
            throw new InvalidArgumentException('\$getMockStatusCodeCallback must be closure or null');
        }

        if (is_callable($afterCallback)) {
            $this->afterCallback = $afterCallback;
        } elseif ($afterCallback !== null) {
            // wrong argument type
            throw new InvalidArgumentException('\$afterCallback must be closure or null');
        }
    }

    /**
     * Executes middleware logic.
     *
     * @param ServerRequestInterface  $request HTTP request.
     * @param RequestHandlerInterface $handler Request handler.
     *
     * @return ResponseInterface HTTP response
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $customCallback = $this->getMockStatusCodeCallback;
        $customAfterCallback = $this->afterCallback;
        $mockedStatusCode = (is_callable($customCallback)) ? $customCallback($request, $this->responses) : null;
        if (
            is_string($mockedStatusCode)
            && array_key_exists($mockedStatusCode, $this->responses)
        ) {
            // response schema succesfully selected, we can mock it now
            $statusCode = ($mockedStatusCode === 'default') ? 200 : (int) $mockedStatusCode;
            $mockedResponse = (array) $this->responses[$mockedStatusCode];
            $contentType = '*/*';
            $response = $this->responseFactory->createResponse($statusCode);
            if (is_array($mockedResponse) && array_key_exists('headers', $mockedResponse)) {
                // response schema contains headers definitions, apply them one by one
                foreach ($mockedResponse['headers'] as $headerName => $headerDefinition) {
                    $headerDefinition = (array) $headerDefinition;
                    $response = $response->withHeader($headerName, $this->mocker->mockFromSchema($headerDefinition['schema']));
                }
            }

            if (
                is_array($mockedResponse)
                && array_key_exists('content', $mockedResponse)
                && !empty($mockedResponse['content'])
            ) {
                // response schema contains body definition
                $responseContentSchema = null;
                foreach ($mockedResponse['content'] as $schemaContentType => $schemaDefinition) {
                    // we can respond in JSON format when any(*/*) content-type allowed
                    // or JSON(application/json) content-type specifically defined
                    $schemaDefinition = (array) $schemaDefinition;
                    if (
                        $schemaContentType === '*/*'
                        || strtolower(substr($schemaContentType, 0, 16)) === 'application/json'
                    ) {
                        $contentType = 'application/json';
                        $responseContentSchema = $schemaDefinition['schema'];
                    }
                }

                if ($contentType === 'application/json') {
                    $responseBody = $this->mocker->mockFromSchema($responseContentSchema);
                    $response->getBody()->write(json_encode($responseBody));
                } else {
                    // notify developer that only application/json response supported so far
                    $response->getBody()->write('Mock feature supports only "application/json" content-type!');
                }
            }

            // after callback applied only when mocked response schema has been selected
            if (is_callable($customAfterCallback)) {
                $response = $customAfterCallback($request, $response);
            }

            // no reason to execute following middlewares (auth, validation etc.)
            // return mocked response and end connection
            return $response
                ->withHeader('Content-Type', $contentType);
        }

        // no response selected, mock feature disabled
        // execute following middlewares
        return $handler->handle($request);
    }
}
