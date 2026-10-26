<?php

/**
 * Helpers used to test the examples of the wiki.
 *
 * The files of a page are the code blocks preceded by a line with their
 * path, like "`index.php`:", they're extracted to a temporary directory
 * together with crystal.php and each request runs in a new process.
 */

/**
 * Code blocks of a wiki page.
 *
 * @return array List of ['lang' => string, 'code' => string, 'file' => string|null]
 */
function docs_blocks(string $page): array
{
    $lines = file(__DIR__.'/../../wiki/'.$page.'.md', FILE_IGNORE_NEW_LINES);
    $blocks = [];
    $block = null;
    $previous = '';

    foreach ($lines as $line) {
        if (is_null($block) && preg_match('/^```\s*([\w-]*)\s*$/', $line, $match)) {
            $block = [
                'lang' => $match[1],
                'code' => '',
                'file' => preg_match('/^`([^`\s]+)`:$/', $previous, $file) ? $file[1] : null,
            ];

            continue;
        }

        if (!is_null($block) && preg_match('/^```\s*$/', $line)) {
            $blocks[] = $block;
            $block = null;
            $previous = '';

            continue;
        }

        if (!is_null($block)) {
            $block['code'] .= $line."\n";
        } elseif ('' !== trim($line)) {
            $previous = trim($line);
        }
    }

    return $blocks;
}

/**
 * Extract the files of a wiki page in a new directory, with crystal.php.
 *
 * @return string The directory
 */
function docs_app(string $page): string
{
    $dir = sys_get_temp_dir().'/crystal-docs-'.uniqid();
    mkdir($dir.'/sessions', 0777, true);
    copy(__DIR__.'/../../crystal.php', $dir.'/crystal.php');

    foreach (docs_blocks($page) as $block) {
        if (is_null($block['file'])) {
            continue;
        }

        $path = $dir.'/'.$block['file'];

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $block['code']);
    }

    register_shutdown_function(function () use ($dir) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    });

    return $dir;
}

/**
 * Run a request against the index.php file of a directory.
 *
 * Options: "post" (form values), "body" (raw body), "headers", "cookies"
 * and "session" (the session identifier, the session is shared between requests).
 *
 * @return array ['status' => int, 'body' => string, 'session' => string]
 */
function docs_request(string $dir, string $method, string $uri, array $options = []): array
{
    $options['method'] = $method;
    $options['uri'] = $uri;

    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/request.php').' '
        .escapeshellarg($dir).' '.escapeshellarg(base64_encode(json_encode($options)));

    $output = shell_exec($command.' 2>&1');
    $position = strrpos($output, "\n#");
    $report = json_decode(substr($output, $position + 2), true);

    return [
        'status' => $report['status'],
        'body' => substr($output, 0, $position),
        'session' => $report['session'],
    ];
}

/**
 * Run a command line script of a directory.
 *
 * @return string The output, followed by the exit code when it isn't 0
 */
function docs_cli(string $dir, string $script, array $arguments = []): string
{
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($dir.'/'.$script);

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg($argument);
    }

    exec($command.' 2>&1', $lines, $code);

    $output = implode("\n", $lines);
    $output .= '' === $output ? '' : "\n";

    return 0 === $code ? $output : $output.'exit code: '.$code."\n";
}
