Crystal fits where installing and running a modern framework is hard or not worth it.
See [[Comparison]] for how it compares with the other frameworks.

## Good fits

-   **Free and shared hosting with only FTP**: no SSH, no Composer, no CLI. Crystal is one file to upload, see [InfinityFree](#infinityfree) below.
-   **Hosting with a file (inode) limit**: one file instead of the thousands installed in `vendor/` by full-stack frameworks.
-   **Old PHP versions**: it runs on PHP 7.0 to 8.5, useful on legacy servers that can't be upgraded yet.
-   **Small websites and prototypes**: landing pages, portfolios, contact forms, small blogs, admin panels, hackathons.
-   **Internal tools**: dashboards, small CRUD applications, webhooks receivers on a server you control.
-   **Adding routes to an existing PHP application**: `require` one file in a legacy project, without restructuring it.
-   **Teaching**: the whole framework can be read in a single file, without build steps.
-   **Command line scripts**: callbacks can run in CLI mode (see [[CLI Usage]]).

## Not a good fit

-   **Large applications and teams**, where conventions, an ORM and a large ecosystem matter more than simplicity: use Laravel or Symfony.
-   **Background jobs, queues and scheduled tasks**: crystal has none of them.
-   **PSR-7/PSR-15 based architectures**: use Slim or Mezzio.
-   **Streaming responses** (large downloads, server-sent events): route output is buffered.
-   **High traffic APIs** needing persistent workers (Swoole, RoadRunner, FrankenPHP worker mode): crystal is designed for the classic one-process-per-request model.

## InfinityFree

[InfinityFree](https://www.infinityfree.com/) is a free hosting with PHP and MySQL, and some important limits.
These are the ones reported by its documentation and staff in October 2026, check the current ones before relying on them:

| Limit | Impact on crystal |
| --- | --- |
| No SSH, no Composer | none: upload `crystal.php` via FTP or the file manager |
| No cron jobs, scripts can be started only by browser requests | no scheduled tasks: run maintenance during normal requests |
| `mail()` isn't available, outgoing SMTP only on port 587 with STARTTLS | use the [[Mailer]] with an external SMTP provider and `tls` encryption |
| A browser check (JavaScript and cookies) blocks non-browser clients | APIs for mobile apps, webhooks, `curl` and bearer tokens don't work; JSON requests from your own pages do |
| Daily hits and inodes limits | a single file helps with the inodes |
| No PHP version choice | crystal supports every PHP version from 7.0 |

### Setup

Upload these files to the `htdocs` directory:

```
htdocs/
├── .htaccess
├── crystal.php
└── index.php
```

`.htaccess`, to send every request to `index.php` (see [[Webserver Configuration]]):

```apache
<IfModule mod_rewrite.c>
    Options -MultiViews
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [QSA,L]
</IfModule>
```

`index.php`:

```php
<?php

$mf = require __DIR__.'/crystal.php';

// error pages without details: never enable the debug mode on a public site
$mf('errors')->enable(false);

// MySQL credentials and host are shown in the control panel, the host isn't "localhost"
$mf('database')::setPdo(new PDO(
    'mysql:host=YOUR_MYSQL_HOST;dbname=YOUR_DATABASE;charset=utf8mb4',
    'YOUR_USERNAME',
    'YOUR_PASSWORD'
));

$mf('/', function () {
    echo 'Hello from InfinityFree!';
});
```

Keep the credentials out of the code with a `.env` file and the [[Configuration]] service, and deny access to it in `.htaccess`:

```apache
<Files ".env">
    Require all denied
</Files>
```

### Services

-   **Cache**: APCu may not be enabled (check `phpinfo()`), use the `filename` or `database` drivers (see [[Cache]]).
-   **Sessions and authentication**: native file sessions work, so [[Authentication]] (with remember-me) and [[CSRF]] work as usual. Bearer tokens don't, because of the browser check.
-   **Mail**: configure the [[Mailer]] with SMTP, port 587 and `tls` encryption, using a provider like Gmail, Brevo or SMTP2GO.
-   **Migrations**: there's no CLI, run them from a protected route and remove it afterwards:

```php
// the "admin" gate has to be defined, see Authentication
$mf('/migrate', $mf('middleware')->wrap(function () use ($mf) {
    $migrations = $mf('migrations');
    $migrations->load(__DIR__.'/migrations');

    echo 'Applied: ' . implode(', ', $migrations->migrate());
}, $mf('auth')->middleware('admin')));
```

-   **Scheduled tasks**: do light maintenance during normal requests (i.e. removing expired records after a login). Don't use external services to call your site periodically: InfinityFree considers it a way to bypass the missing cron jobs, and it can lead to a suspension.

### Sources

-   [Cron jobs disabled](https://forum.infinityfree.com/t/cron-job-feature-is-now-disabled/80495) and [restrictions on PHP](https://forum.infinityfree.com/t/restrictions-on-php/110456)
-   [Browser security system](https://forum.infinityfree.com/t/browser-security-system-features-and-limitations/49353) and [API access](https://forum.infinityfree.com/t/why-isnt-api-access-working-on-my-website/115198)
-   [Sending email with SMTP](https://forum.infinityfree.com/t/how-to-send-mail-from-free-infinity-hosting/68978)

## Other limited hostings

The same considerations apply to most free and cheap shared hostings: when there's no SSH or Composer, check the PHP version, the available extensions (`phpinfo()`), whether `mail()` and outgoing connections are allowed, and the files limits.
