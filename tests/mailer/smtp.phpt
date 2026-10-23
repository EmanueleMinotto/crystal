--TEST--
Mailer with SMTP driver
--SKIPIF--
<?php

if (PHP_MAJOR_VERSION < 7) {
    die('Skip: PHP 7+ is required');
}

if (!function_exists('proc_open')) {
    die('Skip: proc_open is required');
}

?>
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $mailer = $mf('mailer');

    $start = function () {
        $process = proc_open(
            escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/fixtures/smtp-server.php'),
            array(1 => array('pipe', 'w')),
            $pipes
        );

        return array($process, $pipes[1], (int) fgets($pipes[1]));
    };

    list($process, $output, $port) = $start();

    $mailer->setDriver('smtp', array(
        'host' => '127.0.0.1',
        'port' => $port,
        'username' => 'user',
        'password' => 'secret',
        'timeout' => 5,
    ));

    var_dump($mailer->send(array(
        'from' => 'noreply@example.com',
        'to' => 'alice@example.com',
        'bcc' => 'bob@example.com',
        'subject' => 'Hello',
        'text' => "Hello\n.\nWorld",
    )));

    $transcript = stream_get_contents($output);
    proc_close($process);

    // headers and body are checked separately, Date and Message-ID change on each run
    list($commands, $data) = explode("DATA\r\n", $transcript, 2);
    echo $commands;
    echo "DATA\n";
    echo (false !== strpos($data, "Subject: Hello\r\n") ? 'subject sent' : 'subject missing')."\n";
    echo (false === strpos($data, 'bob@example.com') ? 'bcc hidden' : 'bcc exposed')."\n";
    echo substr($data, strpos($data, "\r\n\r\n") + 4);

    // failures are reported as exceptions
    list($process, $output, $port) = $start();

    $mailer->setDriver('smtp', array('host' => '127.0.0.1', 'port' => $port, 'timeout' => 5));

    try {
        $mailer->send(array(
            'from' => 'noreply@example.com',
            'to' => 'rejected@example.com',
            'text' => 'Hello',
        ));
    } catch (RuntimeException $e) {
        echo $e->getMessage()."\n";
    }

    proc_terminate($process);
    proc_close($process);
});

?>
--EXPECT--
bool(true)
EHLO example.com
AUTH LOGIN
dXNlcg==
c2VjcmV0
MAIL FROM:<noreply@example.com>
RCPT TO:<alice@example.com>
RCPT TO:<bob@example.com>
DATA
subject sent
bcc hidden
Hello
..
World
.
QUIT
Unexpected SMTP response: 550 No such user