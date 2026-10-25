--TEST--
Middlewares wrapping route callbacks
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/posts/42';
$_SERVER['SCRIPT_NAME'] = '/';

$middleware = $mf('middleware');
$route = '/posts/(?P<id>[0-9]+)';

$log = function ($name) {
    return function ($request, callable $next) use ($name) {
        echo "$name before (" . $request->param('id') . ")\n";
        $response = $next();
        echo "$name after\n";

        return $response;
    };
};

$upper = function ($request, callable $next) {
    $response = $next();

    return $response->setBody(strtoupper($response->body()));
};

// middlewares order, named parameters and the output of the callback
$mf($route, $middleware->wrap(function ($id) {
    echo "callback $id\n";
}, $log('outer'), $log('inner'), $upper), 'GET', 0);

// the output is a response for the middlewares, but next callbacks aren't skipped
$mf($route, $middleware->wrap($middleware->wrap(function ($id) use ($mf) {
    return $mf('response')->setBody("response $id\n");
}, $upper), $log('nested')), 'GET', 1);

$mf($route, function () {
    echo "skipped\n";
}, 'GET', 2);

?>
--EXPECT--
outer before (42)
inner before (42)
inner after
outer after
CALLBACK 42
nested before (42)
nested after
RESPONSE 42
