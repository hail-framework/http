<?php

namespace Hail\Http;

use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface,
    ServerRequestInterface,
    StreamInterface,
    UploadedFileInterface,
    UriInterface
};
use Nyholm\Psr7\{Factory\Psr17Factory, Uri, Request, ServerRequest, Response, Stream, UploadedFile};

final class Factory
{
    private static Psr17Factory $factory;

    private static ServerRequestCreator $creator;

    public static function psr17(): Psr17Factory
    {
        if (self::$factory === null) {
            self::$factory = new Psr17Factory();
        }

        return self::$factory;
    }

    /**
     * @param UriInterface|string $uri
     *
     * @return UriInterface
     */
    public static function uri($uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
            return $uri;
        }

        return new Uri($uri);
    }

    /**
     * @param string $method
     * @param string|UriInterface $uri
     * @param array $headers
     * @param null|string|StreamInterface $body
     * @param string $protocolVersion
     *
     * @return RequestInterface
     */
    public static function request(
        string $method,
        $uri,
        array $headers = [],
        $body = null,
        string $protocolVersion = '1.1'
    ): RequestInterface {
        return new Request($method, $uri, $headers, $body, $protocolVersion);
    }

    public static function response(
        int $statusCode = 200,
        $body = null,
        array $headers = [],
        string $protocolVersion = '1.1',
        string $reasonPhrase = null
    ): ResponseInterface {
        return new Response($statusCode, $headers, $body, $protocolVersion, $reasonPhrase);
    }

    public static function serverRequestCreator(): ServerRequestCreator
    {
        if (self::$creator === null) {
            $factory = self::$factory ?? self::psr17();
            self::$creator = new ServerRequestCreator($factory, $factory, $factory, $factory);
        }

        return self::$creator;
    }

    public static function serverRequest(
        string $method,
        $uri = null,
        array $headers = [],
        $body = null,
        string $version = '1.1',
        array $serverParams = []
    ): ServerRequestInterface {
        return new ServerRequest($method, $uri, $headers, $body, $version, $serverParams);
    }

    public static function serverRequestFromGlobal(): ServerRequestInterface
    {
        $creator = self::$creator ?? self::serverRequestCreator();

        return $creator->fromGlobals();
    }

    public static function serverRequestFromArrays(
        array $server,
        array $cookie = [],
        array $get = [],
        ?array $post = null,
        array $files = [],
        $body = null
    ): ServerRequestInterface {
        $creator = self::$creator ?? self::serverRequestCreator();
        $headers = ServerRequestCreator::getHeadersFromServer($server);

        return $creator->fromArrays($server, $headers, $cookie, $get, $post, $files, $body);
    }

    /**
     * @param StreamInterface|resource|string|null $body
     *
     * @return StreamInterface
     */
    public static function stream($body = null): StreamInterface
    {
        return Stream::create($body);
    }

    public static function streamFromFile(string $file, string $mode = 'rb'): StreamInterface
    {
        $factory = self::$factory ?? self::psr17();

        return $factory->createStreamFromFile($file, $mode);
    }

    /**
     * @param StreamInterface|string|resource $file
     * @param int|null $size
     * @param int $error
     * @param string|null $clientFilename
     * @param string|null $clientMediaType
     *
     * @return UploadedFileInterface
     */
    public static function uploadedFile(
        $file,
        int $size = null,
        int $error = \UPLOAD_ERR_OK,
        string $clientFilename = null,
        string $clientMediaType = null
    ): UploadedFileInterface {
        if ($size === null) {
            if (\is_resource($file)) {
                $file = self::stream($file);
            }

            if ($file instanceof StreamInterface) {
                $size = $file->getSize();
            } else {
                $size = \filesize($file);
            }
        }

        return new UploadedFile($file, $size, $error, $clientFilename, $clientMediaType);
    }
}