--TEST--
CLI commands listing routes and generating the OpenAPI document
--FILE--
<?php

$GLOBALS['argv'][1] = 'crystal:routes';

$mf = require_once(__DIR__.'/../crystal.php');

$mf->get('/users', function () {}, array('name' => 'listUsers'));
$mf('/users', function () {}, 'POST');

register_shutdown_function(function () use ($mf) {
    $GLOBALS['argv'][1] = 'crystal:openapi';

    // run the command again, as when invoked with another argument
    $console = function () {
        $this->console();
    };
    $console->call($mf);
});

?>
--EXPECT--
GET|HEAD                 /users                                   listUsers
POST                     /users
{
    "openapi": "3.0.3",
    "info": {
        "title": "API",
        "version": "1.0.0"
    },
    "paths": {
        "/users": {
            "get": {
                "operationId": "listUsers",
                "responses": {
                    "200": {
                        "description": "OK"
                    }
                }
            },
            "post": {
                "responses": {
                    "200": {
                        "description": "OK"
                    }
                }
            }
        }
    }
}
