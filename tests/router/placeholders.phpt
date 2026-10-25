--TEST--
Placeholders in the routes
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/users/42/posts/hello-world';
$_SERVER['SCRIPT_NAME'] = '/';

$router = $mf('router');

var_dump($router->compile('/users/{id}'));
var_dump($router->compile('/users/{id:[0-9]+}/{slug:[a-z]{2,}}'));
// quantifiers, escaped braces and unclosed placeholders aren't changed
var_dump($router->compile('/codes/[0-9]{2}/x{1,3}/\{id\}/{id:[0-9]+'));

$mf('/users/{id:[0-9]+}/posts/{slug}', function ($slug, $id) {
    echo "$id $slug\n";
});

// named routes and groups
$router->route('post', '/posts/{id:[0-9]{1,3}}', function () {
});

$router->group('/api/{version:v[0-9]+}', function ($route) {
    $route('/users/{id}', function () {
    }, 'GET', 0, 'user');
}, [], 'api.');

echo $router->url('post', ['id' => 7])."\n";
echo $router->url('api.user', ['version' => 'v2', 'id' => 42])."\n";

?>
--EXPECT--
string(20) "/users/(?P<id>[^/]+)"
string(41) "/users/(?P<id>[0-9]+)/(?P<slug>[a-z]{2,})"
string(40) "/codes/[0-9]{2}/x{1,3}/\{id\}/{id:[0-9]+"
/posts/7
/api/v2/users/42
42 hello-world
