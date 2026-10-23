--TEST--
Mailer with custom driver
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $mailer = $mf('mailer');

    $driver = new class () {
        public $messages = array();

        public function send(array $message)
        {
            $this->messages[] = $message;

            return true;
        }
    };

    $mailer->setDriver($driver);
    $mailer->setDefaults(array('from' => array('noreply@example.com' => 'Crystal')));

    var_dump($mailer->send(array(
        'to' => array('alice@example.com' => 'Alice', 'bob@example.com'),
        'cc' => 'carol@example.com',
        'bcc' => 'dave@example.com',
        'reply_to' => 'support@example.com',
        'subject' => 'Caffè',
        'text' => "Hello\nWorld",
        'headers' => array('X-Mailer' => 'crystal'),
    )));

    $message = $driver->messages[0];
    $headers = $message['headers'];

    echo $message['from']."\n";
    echo implode(',', $message['recipients'])."\n";
    echo $message['subject']."\n";
    echo $headers['From']."\n";
    echo $headers['To']."\n";
    echo $headers['Cc']."\n";
    echo $headers['Reply-To']."\n";
    echo $headers['Subject']."\n";
    echo $headers['X-Mailer']."\n";
    echo $headers['Content-Type']."\n";
    echo $headers['Content-Transfer-Encoding']."\n";
    echo (isset($headers['Bcc']) ? 'bcc exposed' : 'bcc hidden')."\n";
    echo (preg_match('/^<[0-9a-f]{32}@example\.com>$/', $headers['Message-ID']) ? 'message-id ok' : 'message-id ko')."\n";
    echo json_encode($message['body'])."\n";

    // custom drivers must implement send
    try {
        $mailer->setDriver(new stdClass());
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage()."\n";
    }

    try {
        $mailer->setDriver('unknown');
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage()."\n";
    }
});

?>
--EXPECT--
bool(true)
noreply@example.com
alice@example.com,bob@example.com,carol@example.com,dave@example.com
Caffè
"Crystal" <noreply@example.com>
"Alice" <alice@example.com>, bob@example.com
carol@example.com
support@example.com
=?UTF-8?B?Q2FmZsOo?=
crystal
text/plain; charset=UTF-8
quoted-printable
bcc hidden
message-id ok
"Hello\r\nWorld"
Custom driver is missing method: send
Unknown mailer driver: unknown