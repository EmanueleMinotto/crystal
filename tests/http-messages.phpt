--TEST--
Default PSR-7 implementation
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$factory = $mf('http')->getFactory();

function check($label, $value)
{
    echo $label.': '.var_export($value, true)."\n";
}

function throws($label, callable $cb)
{
    try {
        $cb();
        echo $label.": no exception\n";
    } catch (InvalidArgumentException $e) {
        echo $label.": InvalidArgumentException\n";
    } catch (RuntimeException $e) {
        echo $label.": RuntimeException\n";
    }
}

echo "# uri\n";
$uri = $factory->createUri('HTTPS://user:p@ss@Example.COM:443/a b/c?q=1 2#frag ment');
check('scheme', $uri->getScheme());
check('authority', $uri->getAuthority());
check('host', $uri->getHost());
check('port', $uri->getPort());
check('path', $uri->getPath());
check('query', $uri->getQuery());
check('fragment', $uri->getFragment());
check('string', (string) $uri);
check('custom port', (string) $uri->withPort(8443)->withUserInfo('')->withQuery('')->withFragment(''));
check('no double encoding', $uri->withPath('/a%20b')->getPath());
check('relative path with authority', (string) $factory->createUri('http://example.com')->withPath('foo'));
check('immutable', $uri->withHost('foo.com') !== $uri && 'example.com' === $uri->getHost());
throws('invalid port', function () use ($uri) {
    $uri->withPort(70000);
});

echo "# stream\n";
$stream = $factory->createStream('Hello');
check('size', $stream->getSize());
check('content', (string) $stream);
$stream->write(' World');
check('tell', $stream->tell());
$stream->rewind();
check('read', $stream->read(5));
check('contents', $stream->getContents());
check('eof', $stream->eof());
check('metadata', $stream->getMetadata('uri'));
check('detached', is_resource($stream->detach()));
check('detached size', $stream->getSize());
check('detached string', (string) $stream);
throws('detached read', function () use ($stream) {
    $stream->read(1);
});
throws('invalid file', function () use ($factory) {
    $factory->createStreamFromFile(__DIR__.'/missing/file.txt');
});
throws('invalid mode', function () use ($factory) {
    $factory->createStreamFromFile(__FILE__, 'z');
});
throws('read only', function () use ($factory) {
    $factory->createStreamFromFile(__FILE__, 'r')->write('foo');
});

echo "# request\n";
$request = $factory->createServerRequest('GET', 'http://example.com:8080/path?a=b', ['REMOTE_ADDR' => '127.0.0.1']);
check('host header', $request->getHeaderLine('host'));
check('target', $request->getRequestTarget());
check('server params', $request->getServerParams());
$request = $request
    ->withHeader('X-Foo', 'bar')
    ->withAddedHeader('x-foo', ['baz', 1])
    ->withAttribute('id', 42)
    ->withQueryParams(['a' => 'b'])
    ->withParsedBody(['c' => 'd'])
    ->withProtocolVersion('2');
check('headers', $request->getHeaders());
check('header line', $request->getHeaderLine('X-FOO'));
check('has header', $request->hasHeader('x-foo'));
check('without header', $request->withoutHeader('X-FOO')->hasHeader('x-foo'));
check('attribute', $request->getAttribute('id'));
check('attribute default', $request->withoutAttribute('id')->getAttribute('id', 'none'));
check('protocol', $request->getProtocolVersion());
check('preserve host', $request->withUri($factory->createUri('http://other.com'), true)->getHeaderLine('Host'));
check('update host', $request->withUri($factory->createUri('http://other.com'))->getHeaderLine('Host'));
check('empty body', (string) $request->getBody());
throws('invalid header name', function () use ($request) {
    $request->withHeader('Invalid Name', 'foo');
});
throws('header injection', function () use ($request) {
    $request->withHeader('X-Foo', "bar\r\nX-Injected: baz");
});
throws('invalid method', function () use ($request) {
    $request->withMethod('BAD METHOD');
});
throws('invalid parsed body', function () use ($request) {
    $request->withParsedBody('string');
});

echo "# response\n";
$response = $factory->createResponse(404);
check('status', $response->getStatusCode());
check('reason', $response->getReasonPhrase());
check('custom reason', $response->withStatus(299, 'Custom')->getReasonPhrase());
check('immutable', $response->withStatus(200)->getStatusCode() === 200 && $response->getStatusCode() === 404);
throws('invalid status', function () use ($response) {
    $response->withStatus(600);
});

echo "# uploaded file\n";
$file = $factory->createUploadedFile($factory->createStream('uploaded'), null, UPLOAD_ERR_OK, 'file.txt', 'text/plain');
check('size', $file->getSize());
check('client filename', $file->getClientFilename());
$target = tempnam(sys_get_temp_dir(), 'crystal');
$file->moveTo($target);
check('moved', file_get_contents($target));
unlink($target);
throws('moved twice', function () use ($file, $target) {
    $file->moveTo($target);
});
throws('upload error', function () use ($factory) {
    $factory->createUploadedFile($factory->createStream(), 0, UPLOAD_ERR_NO_FILE)->getStream();
});

echo "# interfaces\n";
check('request', $request instanceof Psr\Http\Message\ServerRequestInterface);
check('response', $response instanceof Psr\Http\Message\ResponseInterface);
check('stream', $stream instanceof Psr\Http\Message\StreamInterface);
check('uri', $uri instanceof Psr\Http\Message\UriInterface);
check('uploaded file', $file instanceof Psr\Http\Message\UploadedFileInterface);

?>
--EXPECT--
# uri
scheme: 'https'
authority: 'user:p%40ss@example.com'
host: 'example.com'
port: NULL
path: '/a%20b/c'
query: 'q=1%202'
fragment: 'frag%20ment'
string: 'https://user:p%40ss@example.com/a%20b/c?q=1%202#frag%20ment'
custom port: 'https://example.com:8443/a%20b/c'
no double encoding: '/a%20b'
relative path with authority: 'http://example.com/foo'
immutable: true
invalid port: InvalidArgumentException
# stream
size: 5
content: 'Hello'
tell: 11
read: 'Hello'
contents: ' World'
eof: true
metadata: 'php://temp'
detached: true
detached size: NULL
detached string: ''
detached read: RuntimeException
invalid file: RuntimeException
invalid mode: InvalidArgumentException
read only: RuntimeException
# request
host header: 'example.com:8080'
target: '/path?a=b'
server params: array (
  'REMOTE_ADDR' => '127.0.0.1',
)
headers: array (
  'Host' => 
  array (
    0 => 'example.com:8080',
  ),
  'X-Foo' => 
  array (
    0 => 'bar',
    1 => 'baz',
    2 => '1',
  ),
)
header line: 'bar, baz, 1'
has header: true
without header: false
attribute: 42
attribute default: 'none'
protocol: '2'
preserve host: 'example.com:8080'
update host: 'other.com'
empty body: ''
invalid header name: InvalidArgumentException
header injection: InvalidArgumentException
invalid method: InvalidArgumentException
invalid parsed body: InvalidArgumentException
# response
status: 404
reason: 'Not Found'
custom reason: 'Custom'
immutable: true
invalid status: InvalidArgumentException
# uploaded file
size: 8
client filename: 'file.txt'
moved: 'uploaded'
moved twice: RuntimeException
upload error: RuntimeException
# interfaces
request: true
response: true
stream: true
uri: true
uploaded file: true
