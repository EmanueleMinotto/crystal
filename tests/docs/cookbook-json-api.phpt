--TEST--
Wiki: the JSON API of the Cookbook
--SKIPIF--
<?php if (!extension_loaded('pdo_sqlite')) die('skip pdo_sqlite is required'); ?>
--FILE--
<?php

require __DIR__.'/helpers.php';

$app = docs_app('Cookbook-JSON-API');
$json = ['Content-Type' => 'application/json'];

$requests = [
    ['POST', '/books', '{"title":"Il nome della rosa","author":"Umberto Eco","year":1980}'],
    ['POST', '/books', '{"year":"soon"}'],
    ['GET', '/books/1', null],
    ['GET', '/books/99', null],
    ['PATCH', '/books/1', '{"year":1981}'],
    ['GET', '/books?author=Umberto%20Eco', null],
    ['DELETE', '/books/1', null],
    ['GET', '/books/1', null],
];

foreach ($requests as $request) {
    $options = is_null($request[2]) ? [] : ['headers' => $json, 'body' => $request[2]];
    $result = docs_request($app, $request[0], $request[1], $options);

    echo rtrim($result['status'].' '.$result['body'])."\n";
}

?>
--EXPECT--
201 {"title":"Il nome della rosa","author":"Umberto Eco","year":1980,"id":1}
422 {"type":"about:blank","title":"Unprocessable Content","status":422,"detail":"The given data is invalid.","errors":{"title":["The title field is required."],"author":["The author field is required."],"year":["The year field must be an integer.","The year field must be between 1450 and 2100."]}}
200 {"id":"1","title":"Il nome della rosa","author":"Umberto Eco","year":"1980"}
404 {"type":"about:blank","title":"Not Found","status":404,"detail":"Book not found"}
200 {"id":"1","title":"Il nome della rosa","author":"Umberto Eco","year":"1981"}
200 {"data":[{"id":"1","title":"Il nome della rosa","author":"Umberto Eco","year":"1981"}],"meta":{"page":1,"per_page":10,"total":1,"pages":1},"links":{"first":"/books?author=Umberto%20Eco&page=1","prev":null,"next":null,"last":"/books?author=Umberto%20Eco&page=1"}}
204
404 {"type":"about:blank","title":"Not Found","status":404,"detail":"Book not found"}
