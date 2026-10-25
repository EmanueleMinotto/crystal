--TEST--
Filename cache driver with unreadable files and permissions
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$dir = sys_get_temp_dir() . '/crystal-cache-' . uniqid();
mkdir($dir);

$cache = $mf('cache');
$cache::setDriver('filename', $dir);

// a file partially written (or removed) by a concurrent request is a missing key
file_put_contents($dir . '/broken.cache', substr(serialize(['value' => 'x', 'expires' => null]), 0, 10));
var_dump($cache->get('broken', 'default'), $cache->has('broken'));

umask(022);
var_dump($cache->set('key', ['a' => 1]), $cache->get('key'), $cache->has('key'));
printf("%o\n", fileperms($dir . '/key.cache') & 0777);

// no temporary files are left
var_dump(count(glob($dir . '/*')));

$cache->clear();
array_map('unlink', glob($dir . '/*'));
rmdir($dir);

?>
--EXPECT--
string(7) "default"
bool(false)
bool(true)
array(1) {
  ["a"]=>
  int(1)
}
bool(true)
644
int(2)
