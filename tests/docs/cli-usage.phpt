--TEST--
Wiki: the command line scripts of the CLI Usage page
--FILE--
<?php

require __DIR__.'/helpers.php';

$app = docs_app('CLI-Usage');

echo docs_cli($app, 'hello.php');
echo docs_cli($app, 'hello.php', ['Alice']);
echo docs_cli($app, 'bin/report.php');
echo docs_cli($app, 'bin/report.php', ['Sales', '--limit=10', '--verbose']);
echo docs_cli($app, 'bin/report.php', ['--limit=0']);

?>
--EXPECT--
Hello World!
Hello Alice!
Report: 3 rows
Sales: 10 rows
Options: {"limit":"10","verbose":true}
The limit field must be at least 1.
exit code: 1
