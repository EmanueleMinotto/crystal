--TEST--
Mailer message validation
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $mailer = $mf('mailer');

    $valid = array(
        'from' => 'noreply@example.com',
        'to' => 'alice@example.com',
        'subject' => 'Hello',
        'text' => 'Hello',
    );

    $invalid = array(
        array('from' => null),
        array('from' => array('a@example.com', 'b@example.com')),
        array('from' => 'not-an-email'),
        array('to' => array()),
        array('to' => 'alice@example.com>, eve@example.com'),
        array('text' => null),
        array('subject' => "Hello\r\nBcc: eve@example.com"),
        array('to' => array('alice@example.com' => "Alice\nBcc: eve@example.com")),
        array('headers' => array('X-Foo' => "bar\r\nBcc: eve@example.com")),
        array('headers' => array('X Foo' => 'bar')),
        array('attachments' => array(__DIR__.'/not-existing.txt')),
        array('attachments' => array(array('content' => 'foo'))),
    );

    foreach ($invalid as $override) {
        try {
            $mailer->compose(array_merge($valid, $override));
            echo "no exception\n";
        } catch (InvalidArgumentException $e) {
            echo str_replace(__DIR__, '', $e->getMessage())."\n";
        }
    }

    try {
        $mailer->setDriver('smtp', array());
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage()."\n";
    }

    try {
        $mailer->setDriver('smtp', array('host' => 'localhost', 'encryption' => 'foo'));
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage()."\n";
    }
});

?>
--EXPECT--
Exactly one "from" address is required
Exactly one "from" address is required
Invalid "from" address: not-an-email
At least one recipient is required
Invalid "to" address: alice@example.com>, eve@example.com
Message requires a "text" or "html" body
Invalid line break in "subject"
Invalid line break in "name"
Invalid line break in "X-Foo"
Invalid header name: X Foo
Attachment not readable: /not-existing.txt
Attachments require a "path" or both "content" and "name"
smtp driver requires an array with at least the "host" key as second argument
Unknown SMTP encryption: foo