The crystal microframework offers some utilities, like the [404](https://github.com/EmanueleMinotto/crystal/wiki/CGI-Usage#page-not-found) route.<br />
But there are other utilities offered!

Excluding the utilities described in the [[Home]], other utilities are:

``` php
$server = $mf('server');

var_dump($server['SERVER_NAME']); // localhost

$mf('get') // -> $_GET (with double access)
$mf('post') // -> $_POST (with double access)
$mf('cookie') // -> $_COOKIE (with double access)
$mf('env') // -> $_ENV (with double access)
$mf('request') // -> $_REQUEST (with double access)
$mf('server') // -> $_SERVER (with double access)
```