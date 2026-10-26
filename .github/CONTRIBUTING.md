Contributes must follow these rules:

-   no vendors code, no namespaces, no global variables, new functions nor new classes (yes, I know, it's hard)
-   every PHP functionality must be included in the `crystal.php` file
-   the [PHP documentation](http://php.net/manual/en/indexes.functions.php) is full of functionalities, use them!
-   the code must be documented (one big file is hard to read, one big undocumented source is impossible to read)
-   every pull request must be documented
-   commit messages must follow the [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/) specification (e.g. `fix: keep service instances between calls`)
-   PECL extensions are allowed only if the existence is checked using `extension_loaded` and if the functionality doesn't have side effects but adds functionalities or improves the existing features

Code must be compatible with PHP 7.0+ and can be split into the `src/php7` folder.
Polyfills can be added in the `src/polyfills` folder.
See existing files for examples.

`crystal.php` is built from the `src` folder: change the files of `src` and never edit `crystal.php` directly.
Before committing your code, run `composer check`: it builds and formats `crystal.php` (`composer build`) and runs the tests against it (`composer test`).
The continuous integration fails when the committed `crystal.php` differs from the one built from `src`.

Use .phpt extension to write tests.

The wiki is tested too (`tests/docs`): every PHP code block must have a valid PHP 7.0 syntax (blocks starting with `// PHP 8.0+` are checked from PHP 8), and the code blocks preceded by a line with their path (like `` `index.php`: ``) are extracted and run by the tests of their page.
When a change affects the documented behavior, update the wiki and its tests together.
