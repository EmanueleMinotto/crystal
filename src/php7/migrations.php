<?php

/**
 * Database migrations built on top of the database service.
 *
 * Migrations are identified by a version string and sorted using the
 * natural order algorithm, so both `1`, `2`, `10` and timestamps
 * like `20240101120000_create_users` are supported.
 * Applied versions are tracked in a dedicated table (`crystal_migrations`
 * by default) created on first use.
 *
 * @see https://www.php.net/manual/en/function.strnatcmp.php
 */
$deps['migrations'] = new class ($deps) {
    /**
     * Dynamic set of dependencies, used to retrieve the database service.
     *
     * @var array
     */
    private $deps;

    /**
     * Registered migrations, version => ['up' => callable, 'down' => callable|null].
     *
     * @var array
     */
    private $migrations = [];

    /**
     * Table used to track applied migrations.
     *
     * @var string
     */
    private $table = 'crystal_migrations';

    /**
     * @param array $deps Dynamic set of dependencies.
     */
    public function __construct(array &$deps = [])
    {
        $this->deps = &$deps;
    }

    /**
     * Change the table used to track applied migrations.
     */
    public function setTable(string $table)
    {
        $this->table = $table;
    }

    /**
     * Register a migration.
     *
     * Both callbacks receive the database service as first argument.
     *
     * @param string        $version Unique identifier of the migration
     * @param callable      $up      Callback applying the changes
     * @param callable|null $down    Callback reverting the changes
     *
     * @throws \InvalidArgumentException If the version is already registered or down isn't callable
     */
    public function add(string $version, callable $up, $down = null)
    {
        if (!is_null($down) && !is_callable($down)) {
            throw new \InvalidArgumentException(sprintf('Migration "%s" down callback is not callable', $version));
        }

        if (isset($this->migrations[$version])) {
            throw new \InvalidArgumentException(sprintf('Migration "%s" already registered', $version));
        }

        $this->migrations[$version] = [
            'up' => $up,
            'down' => $down,
        ];

        uksort($this->migrations, 'strnatcmp');
    }

    /**
     * Register all the migrations found in a directory.
     *
     * Each `*.php` file is a migration, the file name (without extension)
     * is the version and the file must return a callable (the `up` callback)
     * or an array with `up` and `down` keys.
     *
     * @throws \InvalidArgumentException If the directory or a migration is invalid
     */
    public function load(string $directory)
    {
        if (!is_dir($directory)) {
            throw new \InvalidArgumentException(sprintf('Migrations directory not found: %s', $directory));
        }

        foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.php') as $file) {
            $migration = require $file;

            if (is_callable($migration)) {
                $migration = ['up' => $migration];
            }

            if (!is_array($migration) || !isset($migration['up']) || !is_callable($migration['up'])) {
                throw new \InvalidArgumentException(sprintf('Invalid migration file: %s', $file));
            }

            $this->add(basename($file, '.php'), $migration['up'], $migration['down'] ?? null);
        }
    }

    /**
     * List of applied versions, in the order they should be reverted.
     *
     * @return string[]
     */
    public function applied(): array
    {
        $this->createTable();

        $versions = array_column($this->database()->select($this->table, 'version'), 'version');

        usort($versions, 'strnatcmp');

        return $versions;
    }

    /**
     * List of registered versions not applied yet.
     *
     * @return string[]
     */
    public function pending(): array
    {
        // numeric versions are converted to integers when used as array keys
        $registered = array_map('strval', array_keys($this->migrations));

        return array_values(array_diff($registered, $this->applied()));
    }

    /**
     * Apply pending migrations, up to the target version (included) if provided.
     *
     * Each migration runs in a transaction, on failure its changes are
     * reverted (when supported by the database) and the migration
     * isn't marked as applied.
     *
     * @param string|null $target Last version to apply
     *
     * @return string[] Applied versions
     */
    public function migrate($target = null): array
    {
        $applied = [];

        foreach ($this->pending() as $version) {
            if (!is_null($target) && strnatcmp($version, $target) > 0) {
                break;
            }

            $this->run($version, 'up');
            $applied[] = $version;
        }

        return $applied;
    }

    /**
     * Revert the latest applied migrations.
     *
     * @param int $steps Number of migrations to revert
     *
     * @throws \RuntimeException If a migration isn't registered or can't be reverted
     *
     * @return string[] Reverted versions
     */
    public function rollback(int $steps = 1): array
    {
        $versions = array_slice(array_reverse($this->applied()), 0, max(0, $steps));

        foreach ($versions as $version) {
            if (!isset($this->migrations[$version])) {
                throw new \RuntimeException(sprintf('Migration "%s" is not registered', $version));
            }

            if (is_null($this->migrations[$version]['down'])) {
                throw new \RuntimeException(sprintf('Migration "%s" can\'t be reverted', $version));
            }

            $this->run($version, 'down');
        }

        return $versions;
    }

    /**
     * Execute a migration direction and update the tracking table.
     */
    private function run(string $version, string $direction)
    {
        $db = $this->database();

        $db->beginTransaction();

        try {
            call_user_func($this->migrations[$version][$direction], $db);

            if ('up' === $direction) {
                $db->create($this->table, [
                    'version' => $version,
                    'applied_at' => time(),
                ]);
            } else {
                $db->delete($this->table, $version, 'version');
            }

            // some databases (e.g. MySQL) implicitly commit on DDL statements
            if ($db->inTransaction()) {
                $db->commit();
            }
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Create the tracking table if it doesn't exist.
     */
    private function createTable()
    {
        $this->database()->query(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (version VARCHAR(255) NOT NULL PRIMARY KEY, applied_at INT NOT NULL)',
            $this->table
        ));
    }

    /**
     * Retrieve the database service, it can be replaced at runtime.
     *
     * @return object
     */
    private function database()
    {
        $database = $this->deps['database'];

        return is_callable($database)
            ? call_user_func($database)
            : $database;
    }
};
