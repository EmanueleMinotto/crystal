<?php

/**
 * Polyfill for PSR-7 and PSR-15 interfaces.
 *
 * Interfaces are declared only when requested and not provided by another
 * autoloader: Composer prepends its own, so installed packages always win.
 * Signatures follow psr/http-message 1.0 and psr/http-server-* 1.0.
 *
 * @see https://www.php-fig.org/psr/psr-7/
 * @see https://www.php-fig.org/psr/psr-15/
 */
spl_autoload_register(function ($class) {
    $interfaces = [
        'Psr\Http\Message\MessageInterface' => 'interface MessageInterface
        {
            public function getProtocolVersion();
            public function withProtocolVersion($version);
            public function getHeaders();
            public function hasHeader($name);
            public function getHeader($name);
            public function getHeaderLine($name);
            public function withHeader($name, $value);
            public function withAddedHeader($name, $value);
            public function withoutHeader($name);
            public function getBody();
            public function withBody(StreamInterface $body);
        }',
        'Psr\Http\Message\RequestInterface' => 'interface RequestInterface extends MessageInterface
        {
            public function getRequestTarget();
            public function withRequestTarget($requestTarget);
            public function getMethod();
            public function withMethod($method);
            public function getUri();
            public function withUri(UriInterface $uri, $preserveHost = false);
        }',
        'Psr\Http\Message\ServerRequestInterface' => 'interface ServerRequestInterface extends RequestInterface
        {
            public function getServerParams();
            public function getCookieParams();
            public function withCookieParams(array $cookies);
            public function getQueryParams();
            public function withQueryParams(array $query);
            public function getUploadedFiles();
            public function withUploadedFiles(array $uploadedFiles);
            public function getParsedBody();
            public function withParsedBody($data);
            public function getAttributes();
            public function getAttribute($name, $default = null);
            public function withAttribute($name, $value);
            public function withoutAttribute($name);
        }',
        'Psr\Http\Message\ResponseInterface' => 'interface ResponseInterface extends MessageInterface
        {
            public function getStatusCode();
            public function withStatus($code, $reasonPhrase = \'\');
            public function getReasonPhrase();
        }',
        'Psr\Http\Message\StreamInterface' => 'interface StreamInterface
        {
            public function __toString();
            public function close();
            public function detach();
            public function getSize();
            public function tell();
            public function eof();
            public function isSeekable();
            public function seek($offset, $whence = SEEK_SET);
            public function rewind();
            public function isWritable();
            public function write($string);
            public function isReadable();
            public function read($length);
            public function getContents();
            public function getMetadata($key = null);
        }',
        'Psr\Http\Message\UploadedFileInterface' => 'interface UploadedFileInterface
        {
            public function getStream();
            public function moveTo($targetPath);
            public function getSize();
            public function getError();
            public function getClientFilename();
            public function getClientMediaType();
        }',
        'Psr\Http\Message\UriInterface' => 'interface UriInterface
        {
            public function getScheme();
            public function getAuthority();
            public function getUserInfo();
            public function getHost();
            public function getPort();
            public function getPath();
            public function getQuery();
            public function getFragment();
            public function withScheme($scheme);
            public function withUserInfo($user, $password = null);
            public function withHost($host);
            public function withPort($port);
            public function withPath($path);
            public function withQuery($query);
            public function withFragment($fragment);
            public function __toString();
        }',
        'Psr\Http\Server\RequestHandlerInterface' => 'interface RequestHandlerInterface
        {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface;
        }',
        'Psr\Http\Server\MiddlewareInterface' => 'interface MiddlewareInterface
        {
            public function process(\Psr\Http\Message\ServerRequestInterface $request, RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface;
        }',
    ];

    $class = ltrim($class, chr(92));

    if (!isset($interfaces[$class])) {
        return false;
    }

    // namespaced interfaces can't be declared conditionally in a non-namespaced file
    eval('namespace '.substr($class, 0, strrpos($class, chr(92))).'; '.$interfaces[$class]);

    return true;
});
