--TEST--
Services injected in the route callbacks by parameter name and class
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/posts/42';
$_SERVER['SCRIPT_NAME'] = '/';

class PostRepository
{
    public function find($id)
    {
        return 'post '.$id;
    }
}

$mf('setting', 'value');
$mf('formatter', function ($value) {
    return strtoupper($value);
});

// named route parameters, services by name and classes made by the container
$mf('/posts/(?P<id>[0-9]+)', function ($id, $request, PostRepository $posts) {
    echo $request->param('id').': '.$posts->find($id)."\n";
}, 'GET', 0);

// positional route parameters, factories and default values
$mf('/posts/([0-9]+)', function ($id, $missing = 'default', $response = null) {
    echo $id.' '.$missing.' '.$response->status()."\n";
}, 'GET', 1);

// route values aren't replaced, settings and callables with arguments aren't injected
$mf('/(?P<request>posts)/[0-9]+', function ($request, $setting, $formatter) {
    var_dump($request, $setting, $formatter);
}, 'GET', 2);

// wrapped callbacks
$mf('/posts/(?P<id>[0-9]+)', $mf('middleware')->wrap(function ($id, $request) {
    echo 'wrapped '.$request->path()."\n";
}), 'GET', 3);

?>
--EXPECT--
42: post 42
42 default 200
string(5) "posts"
NULL
NULL
wrapped /posts/42
