--TEST--
Responses of created, accepted and empty resources, JSON flags
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$show = function ($response) {
    echo $response->status().' '.json_encode($response->headers()).' '.$response->body()."\n";
};

$show($mf('response')->created(['id' => 42], '/users/42'));
$show($mf('response')->created());
$show($mf('response')->accepted(['job' => 'queued']));
$show($mf('response')->accepted());
$show($mf('response')->json(['a' => 1])->noContent());

// flags of json_encode(), from the argument or the json_flags setting
$data = ['name' => 'Zoë', 'url' => 'https://example.com/'];
$show($mf('response')->json($data));
$show($mf('response')->json($data, 200, JSON_UNESCAPED_UNICODE));

$mf('json_flags', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$show($mf('response')->json($data));
$show($mf('response')->created($data));
$show($mf('response')->json($data, null, 0));

?>
--EXPECT--
201 {"Location":"\/users\/42","Content-Type":"application\/json"} {"id":42}
201 [] 
202 {"Content-Type":"application\/json"} {"job":"queued"}
202 [] 
204 [] 
200 {"Content-Type":"application\/json"} {"name":"Zo\u00eb","url":"https:\/\/example.com\/"}
200 {"Content-Type":"application\/json"} {"name":"Zoë","url":"https:\/\/example.com\/"}
200 {"Content-Type":"application\/json"} {"name":"Zoë","url":"https://example.com/"}
201 {"Content-Type":"application\/json"} {"name":"Zoë","url":"https://example.com/"}
200 {"Content-Type":"application\/json"} {"name":"Zo\u00eb","url":"https:\/\/example.com\/"}
