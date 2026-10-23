<?php

// fake SMTP server accepting a single connection, the port is printed
// on the first line and the received commands are printed afterwards
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
$address = stream_socket_get_name($server, false);

echo substr(strrchr($address, ':'), 1)."\n";
fflush(STDOUT);

$client = stream_socket_accept($server, 10);
$reply = function ($response) use ($client) {
    fwrite($client, $response."\r\n");
};

$reply('220 localhost ESMTP');
$data = false;

while (false !== ($line = fgets($client))) {
    if ($data) {
        echo $line;

        if (".\r\n" === $line) {
            $data = false;
            $reply('250 OK queued');
        }

        continue;
    }

    echo $line;
    $command = strtoupper(substr($line, 0, 4));

    switch ($command) {
        case 'EHLO':
            $reply('250-localhost');
            $reply('250 AUTH LOGIN');

            break;

        case 'AUTH':
            $reply('334 VXNlcm5hbWU6');
            echo fgets($client);
            $reply('334 UGFzc3dvcmQ6');
            echo fgets($client);
            $reply('235 Authentication successful');

            break;

        case 'RCPT':
            $reply(false !== strpos($line, 'rejected@') ? '550 No such user' : '250 OK');

            break;

        case 'DATA':
            $data = true;
            $reply('354 End data with <CR><LF>.<CR><LF>');

            break;

        case 'QUIT':
            $reply('221 Bye');
            fclose($client);

            exit(0);

        default:
            $reply('250 OK');
    }
}
