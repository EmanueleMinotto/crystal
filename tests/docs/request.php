<?php

/**
 * Run a request against the index.php file of a directory, from the command line.
 *
 * Usage: php request.php <directory> <options encoded with base64 and JSON>
 *
 * The output is the body followed by a line starting with "#", containing
 * the status code and the session identifier as JSON.
 */
$dir = $argv[1];
$options = json_decode(base64_decode($argv[2]), true);

$_SERVER['REQUEST_METHOD'] = $options['method'];
$_SERVER['REQUEST_URI'] = $options['uri'];
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

parse_str((string) parse_url($options['uri'], PHP_URL_QUERY), $_GET);
$_POST = $options['post'] ?? [];
$_COOKIE = $options['cookies'] ?? [];

foreach ($options['headers'] ?? [] as $name => $value) {
    $key = strtoupper(str_replace('-', '_', $name));
    $_SERVER[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_'.$key] = $value;
}

session_save_path($dir.'/sessions');

if (!empty($options['session'])) {
    $_COOKIE[session_name()] = $options['session'];
}

// the report is printed after the routes, invoked in shutdown functions
$report = function ($depth) use (&$report) {
    if ($depth > 0) {
        register_shutdown_function($report, $depth - 1);

        return;
    }

    $status = http_response_code();

    echo "\n#".json_encode(['status' => $status ?: 200, 'session' => session_id()]);
};
register_shutdown_function($report, 20);

chdir($dir);

require $dir.'/index.php';

if (isset($options['body'])) {
    $mf('request')->setBody($options['body']);
}
