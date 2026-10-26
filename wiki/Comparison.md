How crystal compares with other PHP frameworks and microframeworks.
Versions and requirements are the ones published on Packagist in October 2026.

## At a glance

| | Crystal | Slim 4 | Flight 3 | Fat-Free 3.9 | Leaf 5 | Laravel 13 | Symfony 8 | Mezzio 3 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Type | microframework | microframework | microframework | microframework | microframework | full-stack | full-stack | middleware framework |
| Minimum PHP | **7.0** | 7.4 | 7.4 | 7.2 | 8.2 | 8.3 | 8.4 | 8.2 |
| Installation | **copy one file** | Composer | Composer | Composer or download | Composer | Composer | Composer | Composer |
| Runtime dependencies | **none** | 7 packages + a PSR-7 implementation | none | none | modular `leafs/*` packages | 41 packages | many components | 10 packages |
| PSR-7 / PSR-15 | no (own request/response) | yes | no | no | no | no (own, bridges available) | no (own, bridges available) | yes |
| Ecosystem | small | medium | small | small | small | very large | very large | medium |

Crystal is the only one in the list that runs on PHP 7.0 and is distributed as a single file (`crystal.php`, about 280 KB) with nothing else to install.

## Features

| | Crystal | Slim 4 | Flight 3 | Fat-Free 3.9 | Laravel 13 | Symfony 8 |
| --- | --- | --- | --- | --- | --- | --- |
| Routing | regular expressions, named routes, groups | FastRoute patterns | patterns | patterns | patterns | patterns, attributes |
| Middlewares | per callback and per group | PSR-15 | yes | hooks | yes | event listeners |
| Dependency injection | PSR-11-like container with autowiring | PSR-11 (bring your own) | simple container | hive | full container | full container |
| Templates | plain PHP with layouts and sections | add-on (Twig, PHP-View) | plain PHP views | own template engine | Blade | Twig |
| Database | PDO CRUD helpers, transactions, migrations | none | PDO wrapper | SQL mappers (lightweight ORM) | Eloquent ORM, migrations | Doctrine (bundle), migrations |
| Authentication | sessions, gates, remember-me, API tokens, throttling | none | none | basic (Auth class) | starter kits, Sanctum | Security component |
| CSRF | yes | add-on | none | yes (Session) | yes | yes |
| Validation | array rules | none | none | partial | yes | Validator component |
| Cache | APCu, array, file, database, Memcached, Redis, chains | none | none | yes | yes | Cache component |
| Mail | `mail()` and SMTP | none | none | SMTP | yes | Mailer component |
| Logger | PSR-3-like (syslog or custom) | PSR-3 (bring your own) | none | yes | yes (Monolog) | yes (Monolog) |
| Error pages and 404 | yes, with 405 (opt-in) | yes | yes | yes | yes | yes |
| Queues, scheduler | no | no | no | no | yes | yes (Messenger, Scheduler) |
| CLI | callbacks in CLI mode | no | Runway (add-on) | yes | Artisan | Console |

The table compares the core packages: most features missing in microframeworks can be added with third-party packages, when Composer is available.

## Compared with...

### Laravel and Symfony

Full-stack frameworks with an ORM, queues, scheduling, a CLI, starter kits and a huge ecosystem.
They need Composer, recent PHP versions (8.3+ and 8.4+) and install thousands of files in `vendor/`.

Choose them for applications that will grow, teams, long-term projects, and anything needing background jobs.
Choose crystal when you can't (or don't want to) use Composer and a CLI, or the hosting is limited, see [[When to use crystal]].

### Laminas MVC and Mezzio

Laminas MVC is in security-only maintenance until the end of PHP 8.4 security support (31 December 2028), the Laminas project recommends Mezzio for new projects.
Mezzio is a PSR-15 middleware framework: a good choice when interoperability with PSR-7/PSR-15 packages matters, which crystal doesn't provide.

### Lumen

Laravel itself no longer recommends starting new projects with Lumen, and suggests Laravel instead.
Crystal covers the same "small API or site" use case without dependencies.

### Slim

The most similar to crystal in scope, with strict PSR-7, PSR-11 and PSR-15 support: any PSR middleware or container can be used.
Slim provides only routing and middlewares, everything else comes from Composer packages.
Crystal includes database, authentication, sessions, CSRF, validation, cache, mail, templates and migrations out of the box, but without PSR interoperability.

### Flight and Fat-Free

Both have no dependencies, like crystal, and can be used without Composer.
Fat-Free includes more data tools (SQL mappers, a template engine), Flight is focused on routing and simplicity.
Crystal is a single file, supports older PHP versions and includes authentication, CSRF and validation.

### Leaf

A modern modular microframework requiring PHP 8.2 and Composer, with features installed as separate packages.

## Crystal limitations

To be fair, crystal has limits you should know before choosing it:

-   **No PSR-7/PSR-15 interoperability**: PSR middlewares and request handlers can't be used directly.
-   **No ORM, queues or scheduler**: the database helpers are CRUD operations over PDO.
-   **Regular expression routing**: routes are matched one by one and callbacks run in shutdown functions, an unusual design that allows multiple matching routes (see [[Routing]]).
-   **Output of the routes is buffered** until the end of the script, so streaming large responses isn't supported (see [[Request Response]]).
-   **Small ecosystem and community**: no plugins, few examples outside this wiki.
-   **Not on Packagist**: it's installed by copying `crystal.php`.
-   **No benchmarks** have been published: for most small applications the bottleneck is the database, not the framework.
