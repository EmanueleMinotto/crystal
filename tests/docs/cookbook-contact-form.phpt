--TEST--
Wiki: the contact form of the Cookbook
--FILE--
<?php

require __DIR__.'/helpers.php';

$app = docs_app('Cookbook-Contact-Form');

$errors = function (array $result) {
    preg_match_all('/<span class="error">([^<]+)<\/span>/', $result['body'], $matches);

    return implode(' | ', $matches[1]);
};

$result = docs_request($app, 'GET', '/contact');
echo $result['status'].' '.(false !== strpos($result['body'], '<h1>Contact us</h1>') ? 'form' : 'no form').' errors: '.count(array_filter(explode(' | ', $errors($result))))."\n";

$result = docs_request($app, 'POST', '/contact', ['post' => []]);
echo $result['status'].' errors: '.$errors($result)."\n";

$result = docs_request($app, 'POST', '/contact', ['post' => ['name' => 'Bob', 'email' => 'bob', 'message' => 'Hi']]);
echo $result['status'].' errors: '.$errors($result).' old value: '.(false !== strpos($result['body'], 'value="Bob"') ? 'yes' : 'no')."\n";

$message = ['name' => 'Bob', 'email' => 'bob@example.com', 'message' => 'Hello, I would like to know more.'];

$result = docs_request($app, 'POST', '/contact', ['post' => $message]);
echo $result['status']."\n";

$mails = glob($app.'/mails/*.eml');
$mail = file_get_contents($mails[0]);
echo count($mails).' email: '.(preg_match('/^Subject: Message from Bob\r$/m', $mail) ? 'subject' : 'no subject')
    .', '.(preg_match('/^Reply-To: "Bob" <bob@example.com>\r$/m', $mail) ? 'reply to' : 'no reply to')
    .', '.(false !== strpos($mail, 'Hello, I would like to know more.') ? 'text' : 'no text')."\n";

$result = docs_request($app, 'GET', '/contact?sent=1');
echo $result['status'].' '.(false !== strpos($result['body'], 'Thank you') ? 'notice' : 'no notice')."\n";

// 3 messages per hour
foreach ([2, 3, 4] as $attempt) {
    $result = docs_request($app, 'POST', '/contact', ['post' => $message]);
    echo $attempt.': '.$result['status']."\n";
}

echo count(glob($app.'/mails/*.eml'))." emails\n";

?>
--EXPECT--
200 form errors: 0
422 errors: The name field is required. | The email field is required. | The message field is required.
422 errors: The email field must be a valid email address. | The message field must be between 10 and 2000. old value: yes
302
1 email: subject, reply to, text
200 notice
2: 302
3: 302
4: 429
3 emails
