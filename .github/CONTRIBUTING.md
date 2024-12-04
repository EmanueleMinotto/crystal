Contributes must follow these rules:

-   no vendors code, no namespaces, no global variables, new functions nor new classes (yes, I know, it's hard)
-   every PHP functionality must be included in the `crystal.php` file
-   the [PHP documentation](http://php.net/manual/en/indexes.functions.php) is full of functionalities, use them!
-   the code must be documented (one big file is hard to read, one big undocumented source is impossible to read)
-   every pull request must be documented
-   PECL extensions are allowed only if the existence is checked using `extension_loaded` and if the functionality doesn't have side effects but adds functionalities or improves the existing features

Code can be split into `src/php5` folder for PHP 5.3+ compatible code and into `src/php7` folder for PHP 7+ compatible code.
Polyfills can be added in the `src/polyfills` folder.
See existing files for examples.

Before committing your code, make sure you have built and linted with `composer build` and verified tests with `composer test`.

Use .phpt extension to write tests.
