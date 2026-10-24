--TEST--
Routes listing and OpenAPI document
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf->testing();

$mf->group('/api', function ($mf) {
    $mf->get('/users', function () {}, array(
        'name' => 'listUsers',
        'openapi' => array('summary' => 'List users'),
    ));
    $mf->get('/users/{id:\d+}', function () {}, array('name' => 'getUser'));
    $mf->delete('/users/{id}', function () {}, array(
        'openapi' => array('responses' => array('204' => array('description' => 'Deleted'))),
    ));
}, array('openapi' => array('tags' => array('users'))));

$mf('/legacy/(?P<slug>[a-z]+)\.json', function () {}, 'GET|POST');
$mf('/regex/(.*)', function () {});
$mf($mf('router:not-found'), function () {});

echo json_encode($mf->routes(), JSON_UNESCAPED_SLASHES).PHP_EOL;
echo json_encode($mf->openapi(array('title' => 'Users API', 'version' => '2.0.0'), array(
    'servers' => array(array('url' => 'https://api.example.com')),
)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

?>
--EXPECT--
[{"methods":["GET","HEAD"],"path":"/api/users","name":"listUsers","priority":0},{"methods":["GET","HEAD"],"path":"/api/users/{id:\\d+}","name":"getUser","priority":0},{"methods":["DELETE"],"path":"/api/users/{id}","name":null,"priority":0},{"methods":["GET","POST"],"path":"/legacy/(?P<slug>[a-z]+)\\.json","name":null,"priority":0},{"methods":["GET"],"path":"/regex/(.*)","name":null,"priority":0}]
{
    "openapi": "3.0.3",
    "info": {
        "title": "Users API",
        "version": "2.0.0"
    },
    "paths": {
        "/api/users": {
            "get": {
                "tags": [
                    "users"
                ],
                "summary": "List users",
                "operationId": "listUsers",
                "responses": {
                    "200": {
                        "description": "OK"
                    }
                }
            }
        },
        "/api/users/{id}": {
            "get": {
                "tags": [
                    "users"
                ],
                "operationId": "getUser",
                "parameters": [
                    {
                        "name": "id",
                        "in": "path",
                        "required": true,
                        "schema": {
                            "type": "integer"
                        }
                    }
                ],
                "responses": {
                    "200": {
                        "description": "OK"
                    }
                }
            },
            "delete": {
                "tags": [
                    "users"
                ],
                "responses": {
                    "204": {
                        "description": "Deleted"
                    }
                },
                "parameters": [
                    {
                        "name": "id",
                        "in": "path",
                        "required": true,
                        "schema": {
                            "type": "string"
                        }
                    }
                ]
            }
        },
        "/legacy/{slug}.json": {
            "get": {
                "parameters": [
                    {
                        "name": "slug",
                        "in": "path",
                        "required": true,
                        "schema": {
                            "type": "string"
                        }
                    }
                ],
                "responses": {
                    "200": {
                        "description": "OK"
                    }
                }
            },
            "post": {
                "parameters": [
                    {
                        "name": "slug",
                        "in": "path",
                        "required": true,
                        "schema": {
                            "type": "string"
                        }
                    }
                ],
                "responses": {
                    "200": {
                        "description": "OK"
                    }
                }
            }
        }
    },
    "servers": [
        {
            "url": "https://api.example.com"
        }
    ]
}
