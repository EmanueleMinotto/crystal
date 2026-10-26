--TEST--
Wiki: the webhook receiver of the Cookbook
--SKIPIF--
<?php if (!extension_loaded('pdo_sqlite')) die('skip pdo_sqlite is required'); ?>
--FILE--
<?php

require __DIR__.'/helpers.php';

$app = docs_app('Cookbook-Webhook');

$deliver = function ($body, $signature = null) use ($app) {
    $signature = $signature ?: 'sha256='.hash_hmac('sha256', $body, 'change-me');
    $result = docs_request($app, 'POST', '/webhooks/payments', [
        'headers' => ['Content-Type' => 'application/json', 'X-Signature' => $signature],
        'body' => $body,
    ]);

    echo rtrim($result['status'].' '.$result['body'])."\n";
};

$event = '{"id":"evt_1","type":"payment.succeeded","data":{"order_id":42,"amount":1999}}';

$deliver($event);
$deliver($event, 'sha256=wrong');
$deliver($event);
$deliver('{"id":"evt_2","type":"refund","data":{"amount":0}}');

echo file_get_contents($app.'/webhooks.log');

?>
--EXPECT--
204
401 {"error":{"status":401,"title":"Unauthorized","message":"Invalid signature"}}
204
422 {"error":{"status":422,"title":"Unprocessable Content","message":"The given data is invalid.","errors":{"type":["The selected type is invalid."],"data.order_id":["The data.order id field is required."],"data.amount":["The data.amount field must be at least 1."]}}}
info: Event evt_1 processed: payment.succeeded of order 42
warning: Invalid signature from 127.0.0.1
info: Event evt_1 already processed
