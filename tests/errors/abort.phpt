--TEST--
HTTP errors thrown with abort and custom renderers
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';
$_SERVER['HTTP_ACCEPT'] = 'application/json';

$errors = $mf('errors');
$errors->enable();
$errors->setReporter(function ($exception) {
    echo 'reported: ' . $exception->getMessage() . "\n";
});

$catch = function (callable $callback) use ($errors) {
    try {
        $callback();
    } catch (Exception $exception) {
        return $errors->handle($exception);
    }
};

// messages of 4xx errors are shown, they aren't reported
$response = $catch(function () use ($errors) {
    $errors->abort(403, 'Not your post');
});
var_dump($response->status(), $response->body());

$response = $catch(function () use ($errors) {
    $errors->abort(429, '', ['Retry-After' => 60]);
});
var_dump($response->status(), $response->header('Retry-After'), $response->body());

// 5xx errors are reported, their messages aren't shown
$response = $catch(function () use ($errors) {
    $errors->abort(503, 'Database down');
});
var_dump($response->status(), $response->body());

// unknown status codes
var_dump($errors->response(418)->body(), $errors->response(599)->body());

// custom renderers, the default one is used when they return null or fail
$errors->setRenderer(function ($status, $exception, $request) use ($mf) {
    if (404 === $status) {
        return $mf('response')->setStatus(404)->setBody('custom ' . $request->path());
    }

    if (500 === $status) {
        throw new RuntimeException('renderer failed');
    }
});

var_dump($errors->response(404)->body(), $errors->response(400)->status());
var_dump($errors->response(500)->body());

?>
--EXPECT--
int(403)
string(70) "{"error":{"status":403,"title":"Forbidden","message":"Not your post"}}"
int(429)
string(2) "60"
string(52) "{"error":{"status":429,"title":"Too Many Requests"}}"
reported: Database down
int(503)
string(54) "{"error":{"status":503,"title":"Service Unavailable"}}"
string(40) "{"error":{"status":418,"title":"Error"}}"
string(47) "{"error":{"status":599,"title":"Server Error"}}"
string(8) "custom /"
int(400)
reported: renderer failed
string(56) "{"error":{"status":500,"title":"Internal Server Error"}}"
