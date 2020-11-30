<?php

namespace Hail\Http;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\{
    MiddlewareInterface,
    RequestHandlerInterface
};
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

/**
 * PSR-15 HTTP Server Request Handlers
 */
class RequestHandler implements RequestHandlerInterface
{
    private ?ContainerInterface $container;

    private array $middleware;

    private int $index = 0;

    /**
     * @param callable[]|MiddlewareInterface[]|mixed[] $middleware middleware stack (with at least one middleware component)
     * @param ContainerInterface|null $container optional middleware resolver:
     *                                           $container->get(string $name): MiddlewareInterface
     *
     * @throws \InvalidArgumentException if an empty middleware stack was given
     */
    public function __construct(array $middleware, ContainerInterface $container = null)
    {
        if (empty($middleware)) {
            throw new \InvalidArgumentException('Empty middleware queue');
        }

        $this->middleware = $middleware;
        $this->container = $container;
    }

    /**
     * Dispatch the request, return a response.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        return $this->first()->process($request, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = $this->next();
        if ($middleware === null) {
            throw new \RuntimeException('Middleware queue exhausted, with no response returned.');
        }

        return $middleware->process($request, $this);
    }

    protected function first(): MiddlewareInterface
    {
        return $this->middleware($this->middleware[$this->index = 0]);
    }

    protected function next(): ?MiddlewareInterface
    {
        $index = ++$this->index;

        if (!isset($this->middleware[$index])) {
            return null;
        }

        return $this->middleware($this->middleware[$index]);
    }

    /**
     * @param MiddlewareInterface|callable|string $middleware
     *
     * @return MiddlewareInterface
     */
    protected function middleware($middleware): MiddlewareInterface
    {
        if (\is_callable($middleware)) {
            return new Middleware\CallableWrapper($middleware);
        }

        if (\is_string($middleware)) {
            if ($this->container === null) {
                throw new \RuntimeException("No valid middleware provided: $middleware");
            }

            $middleware = $this->container->get($middleware);
        }

        if (!$middleware instanceof MiddlewareInterface) {
            throw new \RuntimeException('The middleware must be an instance of MiddlewareInterface');
        }

        return $middleware;
    }
}
