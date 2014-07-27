# Contributing

Contributes must follow these rules:

 * no vendors code and no namespaces
 * every PHP functionality must be included in the `microframework.php` file
 * the [PHP documentation](http://php.net/manual/en/indexes.functions.php) is full of functionalities, use them!
 * the code must be documented (one big file is hard to read, one big undocumented source is impossible to read)
 * every pull request must be documented
 * PECL extensions are allowed only if the existence if checked using `extension_loaded` and if the functionality doesn't infect enything else but adds functionalities or improves the existing features
