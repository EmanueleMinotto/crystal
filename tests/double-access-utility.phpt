--TEST--
Double access utility
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');
$function = $mf('utils:double-access');

$_GET = array();
$_GET['lorem'] = 'ipsum';

$tmp = $function($_GET);

echo get_class($tmp).PHP_EOL;
echo '-'.$tmp->lorem.'-'.PHP_EOL;
echo '-'.$tmp['lorem'].'-';

?>
--EXPECT--
ArrayObject
-ipsum-
-ipsum-