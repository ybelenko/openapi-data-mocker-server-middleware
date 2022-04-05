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

declare(strict_types=1);

namespace OpenAPIServer\Mock;

use OpenAPIServer\Mock\OpenApiDataMockerInterface;
use OpenAPIServer\Mock\OpenApiDataMockerRouteMiddleware;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * This class useful in pair with PHP-DI container.
 * Since middleware should be applied to each route we might need an option
 * to create multiple pre-configured instances of that middleware.
 */
class OpenApiDataMockerRouteMiddlewareFactory
{
    // I don't think class variables requires complete doc blocks
    // phpcs:disable Generic.Commenting.DocComment.MissingShort,Generic.Commenting.DocComment.ContentAfterOpen,Generic.Commenting.DocComment.ContentBeforeClose

    /** @var OpenApiDataMockerInterface */
    private $mocker;

    /** @var ResponseFactoryInterface */
    private $responseFactory;

    /** @var callable|null */
    private $getMockStatusCodeCallback;

    /** @var callable|null */
    private $afterCallback;

    /**
     * Factory constructor.
     *
     * @param OpenApiDataMockerInterface $mocker                    Data mocker.
     * @param ResponseFactoryInterface   $responseFactory           Factory to create new response instance.
     * @param callable|null              $getMockStatusCodeCallback Custom callback to select mocked response.
     *                                                              Mock feature is disabled when this argument is null.
     * @param callable|null              $afterCallback             After callback.
     *                                                              Function must return response instance.
     */
    public function __construct(
        OpenApiDataMockerInterface $mocker,
        ResponseFactoryInterface $responseFactory,
        ?callable $getMockStatusCodeCallback = null,
        ?callable $afterCallback = null
    ) {
        $this->mocker = $mocker;
        $this->responseFactory = $responseFactory;
        $this->getMockStatusCodeCallback = $getMockStatusCodeCallback;
        $this->afterCallback = $afterCallback;
    }

    /**
     * Create new middleware instance.
     *
     * @param array $responses Array or object with responses schemas.
     *
     * @return OpenApiDataMockerRouteMiddleware
     */
    public function create(array $responses): OpenApiDataMockerRouteMiddleware
    {
        return new OpenApiDataMockerRouteMiddleware(
            $this->mocker,
            $responses,
            $this->responseFactory,
            $this->getMockStatusCodeCallback,
            $this->afterCallback
        );
    }
}
