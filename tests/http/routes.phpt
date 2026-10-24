--TEST--
Routes listing
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf->testing();

$mf->group('/api', function ($mf) {
    $mf->get('/users/{id:\d+}', function () {}, array('name' => 'getUser'));
    $mf->delete('/users/{id}', function () {});
});

$mf('/legacy/(?P<slug>[a-z]+)', function () {}, 'GET|POST', 1);
$mf($mf('router:not-found'), function () {});
$mf($mf('router:method-not-allowed'), function () {});

echo json_encode($mf->routes(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

?>
--EXPECT--
[
    {
        "methods": [
            "GET",
            "HEAD"
        ],
        "path": "/api/users/{id:\\d+}",
        "name": "getUser",
        "priority": 0
    },
    {
        "methods": [
            "DELETE"
        ],
        "path": "/api/users/{id}",
        "name": null,
        "priority": 0
    },
    {
        "methods": [
            "GET",
            "POST"
        ],
        "path": "/legacy/(?P<slug>[a-z]+)",
        "name": null,
        "priority": 1
    }
]
