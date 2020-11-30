<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Hail\Http;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\{
    ResponseInterface, ServerRequestInterface
};
use Hail\Http\Emitter\EmitterInterface;

/**
 * "Serve" incoming HTTP requests
 *
 * Given a callback, takes an incoming request, dispatches it to the
 * callback, and then sends a response.
 */
class Server
{
    private array $middleware;

    private ?EmitterInterface $emitter;

    private ServerRequestInterface $request;

    private EmitterInterface $defaultEmitter;

    /**
     * Constructor
     *
     * Given a callback, a request, and a response, we can create a server.
     *
     * @param array                  $middleware
     * @param ServerRequestInterface $request
     */
    public function __construct(array $middleware, ServerRequestInterface $request)
    {
        $this->middleware = $middleware;
        $this->request = $request;
        $this->defaultEmitter = new Emitter\SapiEmitter();
    }

    public function reset(): void
    {
        $this->emitter = null;
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Set alternate response emitter to use.
     */
    public function setEmitter(EmitterInterface $emitter): void
    {
        $this->emitter = $emitter;
    }

    public static function createFromGlobal(array $middleware): self {
        $request = Factory::serverRequestFromGlobal();

        return new static($middleware, $request);
    }

    /**
     * Create a Server instance
     *
     * Creates a server instance from the callback and the following
     * PHP environmental values:
     *
     * - server; typically this will be the $_SERVER superglobal
     * - query; typically this will be the $_GET superglobal
     * - body; typically this will be the $_POST superglobal
     * - cookies; typically this will be the $_COOKIE superglobal
     * - files; typically this will be the $_FILES superglobal
     *
     * @param array $middleware
     * @param array|null $server
     * @param array|null $query
     * @param array|null $body
     * @param array|null $cookies
     * @param array|null $files
     *
     * @return static
     */
    public static function createFromArrays(
        array $middleware,
        array $server = null,
        array $query = null,
        array $body = null,
        array $cookies = null,
        array $files = null
    ): self {
        $request = Factory::serverRequestFromArrays(
            $server, $cookies, $query, $body, $files
        );

        return new static($middleware, $request);
    }

    /**
     * Create a Server instance from an existing request object
     *
     * Provided a callback, an existing request object, and optionally an
     * existing response object, create and return the Server instance.
     *
     * @param array                  $middleware
     * @param ServerRequestInterface $request
     *
     * @return static
     */
    public static function createFromRequest(
        array $middleware,
        ServerRequestInterface $request
    ): self {
        return new static($middleware, $request);
    }

    public function handler(ContainerInterface $container = null): ResponseInterface
    {
        $dispatcher = new RequestHandler($this->middleware, $container);

        return $dispatcher->dispatch($this->request);
    }

    public function emit(ResponseInterface $response): void
    {
        $emitter = $this->emitter ?? $this->defaultEmitter;
        $emitter->emit($response);
    }

    public function listen(ContainerInterface $container = null): void
    {
        $this->emit(
            $this->handler($container)
        );
    }
}