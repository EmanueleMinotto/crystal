--TEST--
JWT, Bearer token and API key authentication
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf->testing();

$auth = $mf('auth');
$secret = 'test-secret';

// JWT
$token = $auth->encode(array('sub' => '42', 'iat' => 1500000000), $secret, 0);
echo $token.PHP_EOL;
echo json_encode($auth->decode($token, $secret)).PHP_EOL;

foreach (array(
    'tampered' => function () use ($auth, $token, $secret) {
        $parts = explode('.', $token);
        $parts[1] = rtrim(strtr(base64_encode('{"sub":"1"}'), '+/', '-_'), '=');

        return $auth->decode(implode('.', $parts), $secret);
    },
    'wrong secret' => function () use ($auth, $token) {
        return $auth->decode($token, 'other');
    },
    'expired' => function () use ($auth, $secret) {
        return $auth->decode($auth->encode(array('iat' => time() - 100), $secret, 10), $secret);
    },
    'not before' => function () use ($auth, $secret) {
        return $auth->decode($auth->encode(array('nbf' => time() + 100), $secret), $secret);
    },
    'algorithm' => function () use ($auth, $secret) {
        return $auth->decode($auth->encode(array(), $secret, 60, 'HS512'), $secret);
    },
    'segments' => function () use ($auth, $secret) {
        return $auth->decode('abc', $secret);
    },
) as $case => $decode) {
    try {
        $decode();
    } catch (UnexpectedValueException $e) {
        echo $case.': '.$e->getMessage().PHP_EOL;
    }
}

echo json_encode(array_keys($auth->decode($auth->encode(array('a' => 1), $secret, 60, 'HS384'), $secret, array('HS384')))).PHP_EOL;

// Bearer middleware
$mf->get('/me', function () use ($mf) {
    return array('user' => $mf('request')->attribute('user'));
}, array('middleware' => array($auth->bearer($secret))));

$show = function ($response) {
    echo $response->getStatusCode().' '.$response->getHeaderLine('WWW-Authenticate').' '.$response->getBody().PHP_EOL;
};

$show($mf->handle('GET', '/me', array('headers' => array('Authorization' => 'Bearer '.$token))));
$show($mf->handle('GET', '/me'));
$show($mf->handle('GET', '/me', array('headers' => array('Authorization' => 'Bearer nope'))));

// custom verifier
$mf->get('/custom', function () use ($mf) {
    return array('user' => $mf('request')->attribute('user'));
}, array('middleware' => array($auth->bearer(function ($token) {
    return 'opaque' === $token ? 'alice' : null;
}))));

$show($mf->handle('GET', '/custom', array('headers' => array('Authorization' => 'Bearer opaque'))));
$show($mf->handle('GET', '/custom', array('headers' => array('Authorization' => 'Bearer other'))));

// API keys
$mf->get('/reports', function () use ($mf) {
    return array('client' => $mf('request')->attribute('user'));
}, array('middleware' => array($auth->apiKey(array('billing' => 'key-1', 'key-2')))));

$show($mf->handle('GET', '/reports', array('headers' => array('X-API-Key' => 'key-1'))));
$show($mf->handle('GET', '/reports', array('headers' => array('X-API-Key' => 'key-2'))));
$show($mf->handle('GET', '/reports', array('headers' => array('X-API-Key' => 'key-3'))));

?>
--EXPECT--
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiI0MiIsImlhdCI6MTUwMDAwMDAwMH0.lZXo1YK5Xf2AXPrKhVJ5F7HNBYS5iHnYrz20N3SsuhE
{"sub":"42","iat":1500000000}
tampered: Signature verification failed
wrong secret: Signature verification failed
expired: Token expired
not before: Token not valid yet
algorithm: Algorithm not allowed
segments: Wrong number of segments
["a","iat","exp"]
200  {"user":{"sub":"42","iat":1500000000}}
401 Bearer {"type":"about:blank","title":"Unauthorized","status":401,"detail":"Missing bearer token."}
401 Bearer error="invalid_token" {"type":"about:blank","title":"Unauthorized","status":401,"detail":"Wrong number of segments."}
200  {"user":"alice"}
401 Bearer error="invalid_token" {"type":"about:blank","title":"Unauthorized","status":401,"detail":"Invalid bearer token."}
200  {"client":"billing"}
200  {"client":"key-2"}
401 ApiKey header="X-API-Key" {"type":"about:blank","title":"Unauthorized","status":401,"detail":"Invalid API key."}
