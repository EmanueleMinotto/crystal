--TEST--
Groups of routes with prefixes and middlewares
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/users/42';
$_SERVER['SCRIPT_NAME'] = '/';

$router = $mf('router');

$log = function ($name) {
    return function ($request, callable $next) use ($name) {
        echo "$name (" . json_encode($request->params()) . ")\n";

        return $next();
    };
};

$router->group('/admin', function ($route) use ($log) {
    $route('/users', function () {
        echo "users list?\n";
    }, 'GET', 0, 'users');

    $route('/users/(?P<id>[0-9]+)', function ($id) {
        echo "admin user $id\n";
    }, 'POST', 0);

    $route->route('users.update', '/users/(?P<id>[0-9]+)', function ($id) {
        echo "update user $id\n";
    }, 'POST', 1);

    $route->group('/users', function ($route) {
        $route('/(?P<user>[0-9]+)', function ($user) {
            echo "nested user $user\n";
        }, 'POST', 2, 'show');
    }, [$log('inner')], 'users.');
}, [$log('outer')], 'admin.');

// routes outside the group don't have its middlewares
$mf('/admin/(?P<section>[a-z]+)/(?P<id>[0-9]+)', function ($section, $id) {
    echo "outside: $section $id\n";
}, 'POST', 3, 'section');

// groups without middlewares and names
$router->group('/admin', function ($route) {
    $route('/users/(?P<id>[0-9]+)', function ($id) {
        echo "plain group $id\n";
    }, 'POST', 4);
});

echo $router->url('admin.users') . "\n";
echo $router->url('admin.users.update', ['id' => 1]) . "\n";
echo $router->url('admin.users.show', ['user' => 2]) . "\n";
echo $router->url('section', ['section' => 'posts', 'id' => 3]) . "\n";

try {
    $router->group('/other', function ($route) {
        $route('/', function () {}, 'GET', 0, 'users');
    }, [], 'admin.');
} catch (InvalidArgumentException $exception) {
    echo $exception->getMessage() . "\n";
}

?>
--EXPECT--
/admin/users
/admin/users/1
/admin/users/2
/admin/posts/3
Route "admin.users" already defined.
outer ({"id":"42"})
admin user 42
outer ({"id":"42"})
update user 42
outer ({"user":"42"})
inner ({"user":"42"})
nested user 42
outside: users 42
plain group 42
