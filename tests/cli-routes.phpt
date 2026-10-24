--TEST--
Routes defined in command line mode are dispatched only by handle()
--FILE--
<?php

$GLOBALS['argv'][1] = 'crystal:routes';

$mf = require_once(__DIR__.'/../crystal.php');

$mf->get('/users', function () {
    echo 'list';
}, array('name' => 'listUsers'));
$mf('/users', function () {
    echo 'create';
}, 'POST');

echo $mf->handle('POST', '/users')->getBody().PHP_EOL;
echo count($mf->routes()).PHP_EOL;

?>
--EXPECT--
create
2
