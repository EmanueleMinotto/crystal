--TEST--
URLs generated from named routes
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/posts/42';
$_SERVER['SCRIPT_NAME'] = '/app/index.php';

$router = $mf('router');

$mf('/', function () {}, 'GET', 0, 'home');
$mf('/posts/(?P<id>[0-9]+)', function ($id) use ($router) {
    echo "matched $id: " . $router->url('posts.show', ['id' => $id]) . "\n";
}, 'GET', 0, 'posts.show');
$router->route('files', '/files/(?<path>[^/]+)\.(?\'ext\'(?:txt|md))', function () {});
$router->route('complex', '/archive/(?P<year>[0-9]{4})/(?P<slug>[a-z\-]+)?', function () {});
$router->name('any', '/search(.*)');
$router->name('alternative', '/(a|b)');
$router->name('class', '/user/\d+');
$router->name('unbalanced', '/(?P<id>[0-9]+');
$router->name('brackets', '/tag/(?P<tag>[^)(]+)');

var_dump($router->has('home'), $router->has('missing'));
echo $router->url('home') . "\n";
echo $router->url('posts.show', ['id' => 7]) . "\n";
echo $router->url('posts.show', ['id' => 7, 'page' => 2, 'q' => 'a b']) . "\n";
echo $router->url('files', ['path' => 'my notes', 'ext' => 'md']) . "\n";
echo $router->url('brackets', ['tag' => 'php']) . "\n";

$errors = [
    ['missing', []],
    ['posts.show', []],
    ['posts.show', ['id' => 'abc']],
    ['files', ['path' => 'a', 'ext' => 'pdf']],
    ['complex', ['year' => 2026, 'slug' => 'test']],
    ['any', []],
    ['alternative', []],
    ['class', []],
    ['unbalanced', ['id' => 1]],
];

foreach ($errors as $error) {
    try {
        $router->url($error[0], $error[1]);
    } catch (Exception $exception) {
        echo get_class($exception) . ': ' . $exception->getMessage() . "\n";
    }
}

try {
    $mf('/other', function () {}, 'GET', 0, 'home');
} catch (InvalidArgumentException $exception) {
    echo $exception->getMessage() . "\n";
}

// in the command line there's no base path
$_SERVER['REQUEST_URI'] = '';
echo $router->url('posts.show', ['id' => 1]) . "\n";
$_SERVER['REQUEST_URI'] = '/app/posts/42';

?>
--EXPECT--
bool(true)
bool(false)
/app/
/app/posts/7
/app/posts/7?page=2&q=a%20b
/app/files/my%20notes.md
/app/tag/php
InvalidArgumentException: Route "missing" not defined.
InvalidArgumentException: Missing parameter "id" for route "posts.show".
InvalidArgumentException: Parameter "id" of route "posts.show" must match "[0-9]+", "abc" given.
InvalidArgumentException: Parameter "ext" of route "files" must match "(?:txt|md)", "pdf" given.
LogicException: The URL of route "complex" (/archive/(?P<year>[0-9]{4})/(?P<slug>[a-z\-]+)?) can't be generated: quantifiers aren't allowed after named subpatterns.
LogicException: The URL of route "any" (/search(.*)) can't be generated: only named subpatterns are allowed.
LogicException: The URL of route "alternative" (/(a|b)) can't be generated: only named subpatterns are allowed.
LogicException: The URL of route "class" (/user/\d+) can't be generated: character classes are allowed only in named subpatterns.
LogicException: The URL of route "unbalanced" (/(?P<id>[0-9]+) can't be generated: unbalanced parentheses.
Route "home" already defined.
/posts/1
matched 42: /app/posts/42
