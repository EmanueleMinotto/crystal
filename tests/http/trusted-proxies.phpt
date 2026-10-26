--TEST--
Client address behind trusted proxies
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$request = $mf('request');

$ip = function ($remote, $forwarded = null) use ($request) {
    $_SERVER['REMOTE_ADDR'] = $remote;
    $_SERVER['HTTP_X_FORWARDED_FOR'] = $forwarded;

    return $request->ip();
};

// without trusted proxies the header is ignored
var_dump($ip('10.0.0.1', '203.0.113.7'));

$request->setTrustedProxies(['10.0.0.0/8', '192.168.1.1', '2001:db8::/32', '172.16.0.0/12']);

var_dump($ip('10.0.0.1', '203.0.113.7'));
// the first addresses can be sent by the client, the closest untrusted one is used
var_dump($ip('10.0.0.1', '1.1.1.1, 203.0.113.7, 192.168.1.1'));
// requests from untrusted addresses
var_dump($ip('198.51.100.1', '203.0.113.7'));
var_dump($ip('192.168.1.2', '203.0.113.7'));
var_dump($ip('172.32.0.1', '203.0.113.7'));
var_dump($ip('172.31.255.255', '203.0.113.7'));
// IPv6
var_dump($ip('2001:db8::1', '2001:db9::5'));
// invalid or missing header
var_dump($ip('10.0.0.1', 'unknown'));
var_dump($ip('10.0.0.1'));
// every address is a proxy
var_dump($ip('10.0.0.1', '10.0.0.2'));

?>
--EXPECT--
string(8) "10.0.0.1"
string(11) "203.0.113.7"
string(11) "203.0.113.7"
string(12) "198.51.100.1"
string(11) "192.168.1.2"
string(10) "172.32.0.1"
string(11) "203.0.113.7"
string(11) "2001:db9::5"
string(8) "10.0.0.1"
string(8) "10.0.0.1"
string(8) "10.0.0.2"
