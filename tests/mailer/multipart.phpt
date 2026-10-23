--TEST--
Mailer multipart messages and attachments
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $mailer = $mf('mailer');

    // text and html become a multipart/alternative message
    $message = $mailer->compose(array(
        'from' => 'noreply@example.com',
        'to' => 'alice@example.com',
        'subject' => 'Alternative',
        'text' => 'Hello',
        'html' => '<p>Hello</p>',
    ));

    preg_match('/^multipart\/alternative; boundary="(.+)"$/', $message['headers']['Content-Type'], $matches);
    $boundary = $matches[1];

    echo str_replace($boundary, 'boundary', $message['body'])."\n";
    echo "---\n";

    // attachments wrap the content into a multipart/mixed message
    $message = $mailer->compose(array(
        'from' => 'noreply@example.com',
        'to' => 'alice@example.com',
        'subject' => 'Attachments',
        'html' => '<p>Hello</p>',
        'attachments' => array(
            __FILE__,
            array('content' => 'foo,bar', 'name' => 'data.csv', 'type' => 'text/csv'),
        ),
    ));

    preg_match('/^multipart\/mixed; boundary="(.+)"$/', $message['headers']['Content-Type'], $matches);
    $parts = explode('--'.$matches[1], $message['body']);

    echo count($parts)."\n";
    echo trim($parts[1])."\n";
    echo (false !== strpos($parts[2], 'filename="'.basename(__FILE__).'"') ? 'file attached' : 'file missing')."\n";
    echo trim($parts[3])."\n";
    echo trim($parts[4])."\n";
});

?>
--EXPECT--
--boundary
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: quoted-printable

Hello
--boundary
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: quoted-printable

<p>Hello</p>
--boundary--
---
5
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: quoted-printable

<p>Hello</p>
file attached
Content-Type: text/csv; name="data.csv"
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename="data.csv"

Zm9vLGJhcg==
--