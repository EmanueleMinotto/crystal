--TEST--
Double access utility
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');
$function = $mf('double_access');

$_GET = array();
$_GET['lorem'] = 'ipsum';

echo get_class($function($_GET)).PHP_EOL;
echo '-'.$function($_GET)->lorem.'-'.PHP_EOL;
echo '-'.$function($_GET)['lorem'].'-';

?>
--EXPECT--
ArrayObject
-ipsum-
-ipsum-