Complete applications to copy and adapt, each one in a few files.
They use SQLite, so they run with `php -S localhost:8080 index.php` without installing a database.

| Recipe | What it shows |
| --- | --- |
| [JSON API](Cookbook-JSON-API) | routes for each method, validation, pagination, CORS, 201/204/404/422 responses, Problem Details |
| [Website with login](Cookbook-Website-With-Login) | layouts, login and logout, protected pages, CSRF tokens, flash messages, a script creating the users |
| [Contact form](Cookbook-Contact-Form) | errors shown next to the fields, emails via SMTP or saved as files, rate limiting, Post/Redirect/Get |
| [Webhook receiver](Cookbook-Webhook) | signature verification, nested JSON validation, events processed once, logs in a file |
| [Cache and entity tags](Cookbook-Caching) | caching a slow query, removing outdated values, 304 Not Modified responses |
| [Command line script](Cookbook-CLI-Script) | arguments, exit codes, validation of a CSV file, `--dry-run` |

New to crystal? Start from [[Getting Started]].

Every recipe is tested by the continuous integration: the files of each page are extracted and the requests shown are sent to them, so the code always works with the current `crystal.php`.
