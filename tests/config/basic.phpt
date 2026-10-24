--TEST--
Config load, merge and dot notation access
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $config = $mf('config');

    $config->load(__DIR__.'/fixtures/app.php');
    $config->load(__DIR__.'/fixtures/override.json');
    $config->load(__DIR__.'/fixtures/mail.ini');
    $config->load(array('app' => array('version' => '1.0')));

    var_dump($config->get('app.name'));
    var_dump($config->get('app.debug'));
    var_dump($config->get('app.version'));
    var_dump($config->get('database.host'));
    var_dump($config->get('database.port'));
    var_dump($config->get('hosts'));
    var_dump($config->get('mail.port'));
    var_dump($config->get('mail.secure'));
    var_dump($config->get('app.name.nested', 'default'));
    var_dump($config->get('not.defined', 'default'));

    $config->set('cache.ttl', null);
    $config->set('app.name', 'changed');

    var_dump($config->has('cache.ttl'));
    var_dump($config->has('cache.size'));
    var_dump($config->get('app.name'));
    var_dump(array_keys($config->all()));
});

?>
--EXPECT--
string(7) "crystal"
bool(true)
string(3) "1.0"
string(9) "localhost"
int(3307)
array(1) {
  [0]=>
  string(1) "c"
}
int(587)
bool(true)
string(7) "default"
string(7) "default"
bool(true)
bool(false)
string(7) "changed"
array(5) {
  [0]=>
  string(3) "app"
  [1]=>
  string(8) "database"
  [2]=>
  string(5) "hosts"
  [3]=>
  string(4) "mail"
  [4]=>
  string(5) "cache"
}