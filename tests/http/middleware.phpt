--TEST--
Global, group and route middlewares
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf->testing();

$trace = function ($name) {
    return function ($request, callable $next) use ($name) {
        echo 'before '.$name.PHP_EOL;
        $response = $next($request);
        echo 'after '.$name.PHP_EOL;

        return $response->withAddedHeader('X-Trace', $name);
    };
};

$mf->middleware($trace('global'));

$mf->group('/admin', function ($mf) use ($trace) {
    $mf->get('/dashboard', function () {
        return 'ok';
    }, array('middleware' => array($trace('route'))));
}, array('middleware' => array($trace('group'), function ($request, callable $next) {
    if ('secret' !== $request->header('X-Token')) {
        return array('error' => 'forbidden');
    }

    $request->setAttribute('admin', true);

    return $next($request);
})));

$response = $mf->handle('GET', '/admin/dashboard', array('headers' => array('X-Token' => 'secret')));
echo $response->getBody().' '.$response->getHeaderLine('X-Trace').PHP_EOL;

$response = $mf->handle('GET', '/admin/dashboard');
echo $response->getBody().' '.$response->getHeaderLine('X-Trace').PHP_EOL;

// global middlewares wrap not matched requests too
$response = $mf->handle('GET', '/missing');
echo $response->getStatusCode().' '.$response->getHeaderLine('X-Trace').PHP_EOL;

?>
--EXPECT--
before global
before group
before route
after route
after group
after global
ok route, group, global
before global
before group
after group
after global
{"error":"forbidden"} group, global
before global
after global
404 global
