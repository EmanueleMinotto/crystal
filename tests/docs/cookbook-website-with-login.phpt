--TEST--
Wiki: the website with login of the Cookbook
--SKIPIF--
<?php if (!extension_loaded('pdo_sqlite')) die('skip pdo_sqlite is required'); ?>
--FILE--
<?php

require __DIR__.'/helpers.php';

$app = docs_app('Cookbook-Website-With-Login');

$token = function (array $result) {
    return preg_match('/name="_token" value="([^"]+)"/', $result['body'], $match) ? $match[1] : null;
};
$text = function (array $result) {
    return trim(preg_replace('/\s+/', ' ', strip_tags($result['body'])));
};

echo docs_cli($app, 'bin/create-user.php');
echo docs_cli($app, 'bin/create-user.php', ['Alice', 'alice@example.com', 'secret']);

// guests are sent to the login page
$result = docs_request($app, 'GET', '/account');
echo 'account as a guest: '.$result['status']."\n";

$result = docs_request($app, 'GET', '/');
echo 'home: '.$result['status'].' '.$text($result)."\n";

// the form and its token
$form = docs_request($app, 'GET', '/login');
echo 'login form: '.$form['status'].' '.$text($form).' token: '.(is_null($token($form)) ? 'no' : 'yes')."\n";
$session = $form['session'];

// forms without the token are refused
$result = docs_request($app, 'POST', '/login', ['session' => $session, 'post' => ['email' => 'alice@example.com', 'password' => 'secret']]);
echo 'login without token: '.$result['status']."\n";

// wrong password: the error is shown once
$result = docs_request($app, 'POST', '/login', ['session' => $session, 'post' => ['_token' => $token($form), 'email' => 'alice@example.com', 'password' => 'wrong']]);
echo 'wrong password: '.$result['status']."\n";

$result = docs_request($app, 'GET', '/login', ['session' => $session]);
echo 'login form: '.$text($result)."\n";

$result = docs_request($app, 'GET', '/login', ['session' => $session]);
echo 'login form: '.$text($result)."\n";

// valid credentials: the session identifier changes
$result = docs_request($app, 'POST', '/login', ['session' => $session, 'post' => ['_token' => $token($form), 'email' => 'alice@example.com', 'password' => 'secret']]);
echo 'login: '.$result['status'].' new session: '.($result['session'] !== $session ? 'yes' : 'no')."\n";
$session = $result['session'];

$account = docs_request($app, 'GET', '/account', ['session' => $session]);
echo 'account: '.$account['status'].' '.$text($account)."\n";

// logout
$result = docs_request($app, 'POST', '/logout', ['session' => $session, 'post' => ['_token' => $token($account)]]);
echo 'logout: '.$result['status']."\n";
$session = $result['session'];

$result = docs_request($app, 'GET', '/account', ['session' => $session]);
echo 'account after the logout: '.$result['status']."\n";

?>
--EXPECT--
Usage: php bin/create-user.php <name> <email> <password>
exit code: 1
User alice@example.com created
account as a guest: 302
home: 200 My site Home Log in Welcome!
login form: 200 Log in Home Log in Log in Log in token: yes
login without token: 403
wrong password: 302
login form: Log in Home Log in Log in Invalid email or password. Log in
login form: Log in Home Log in Log in Log in
login: 302 new session: yes
account: 200 Your account Home Account Log out Hello Alice!
logout: 302
account after the logout: 302
