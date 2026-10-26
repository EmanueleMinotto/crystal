<?php

$deps['database'] = new class () {
    /**
     * Shared PDO instance.
     *
     * @var PDO
     */
    private static $pdo;

    /**
     * Name of the PDO driver, like "mysql" or "sqlite".
     *
     * @var string
     */
    private static $driver;

    /**
     * Set a shared PDO connection.
     */
    public static function setPdo(PDO $pdo)
    {
        static::$pdo = $pdo;
        static::$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        static::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        static::$pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
    }

    /**
     * Quote a table or column name, using the syntax of the database.
     *
     * Names containing a dot (like `schema.table`) are quoted part by part.
     */
    public function quoteIdentifier(string $name): string
    {
        switch (static::$driver) {
            case 'mysql':
                // SQLite reads unknown double quoted identifiers as strings
            case 'sqlite':
                $quotes = ['`', '`'];

                break;

            case 'sqlsrv':
            case 'dblib':
                $quotes = ['[', ']'];

                break;

            default:
                // SQL standard, used by PostgreSQL, Oracle, ...
                $quotes = ['"', '"'];
        }

        return implode('.', array_map(function ($part) use ($quotes) {
            return $quotes[0].str_replace($quotes[1], $quotes[1].$quotes[1], $part).$quotes[1];
        }, explode('.', $name)));
    }

    /**
     * Execute a query and parse result rows as array.
     */
    public function query(string $sql, array $params = [])
    {
        $stmt = static::$pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * A simple query builder, parsing all the results as array of array.
     *
     * @param array|string              $fields
     * @param array|integer|string|null $where
     * @param string|null               $order
     * @param string|null               $limit
     */
    public function select(string $table, $fields = '*', $where = null, $order = null, $limit = null)
    {
        if (empty($fields)) {
            $fields = '*';
        }

        if (is_scalar($fields) && preg_match('/[a-zA-Z0-9\_]+/', $fields)) {
            $fields = [$fields];
        }

        if (is_array($fields)) {
            $fields = join(', ', array_map(function ($field) {
                return preg_match('/^[a-zA-Z0-9\_]+$/', $field)
                    ? $this->quoteIdentifier($field)
                    : $field;
            }, $fields));
        }

        $sql = sprintf('SELECT %s FROM %s', $fields, $this->quoteIdentifier($table));

        $params = [];

        if (!empty($where)) {
            $where = $this->buildWhereCondition($where);

            $params += $where['params'];
            $sql .= sprintf(' WHERE %s', $where['sql']);
        }

        if (!empty($order)) {
            $sql .= sprintf(' ORDER BY %s', $order);
        }

        if (!empty($limit)) {
            $sql .= sprintf(' LIMIT %s', $limit);
        }

        return $this->query($sql, $params);
    }

    /**
     * Given an array of columns and values, creates a row in the table.
     *
     * @return integer|string
     */
    public function create(string $table, array $values)
    {
        $keys = array_keys($values);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (:%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map([$this, 'quoteIdentifier'], $keys)),
            implode(', :', $keys)
        );

        if ('pgsql' === static::$driver) {
            // lastInsertId() fails without a sequence, aborting the current transaction
            $rows = $this->query($sql.' RETURNING *', $values);
            $lastInsertId = $rows[0]['id'] ?? false;
        } else {
            $this->query($sql, $values);
            $lastInsertId = static::$pdo->lastInsertId();
        }

        // without auto increment (MySQL returns "0") the inserted id is used
        if (empty($lastInsertId) && isset($values['id'])) {
            $lastInsertId = $values['id'];
        }

        return is_numeric($lastInsertId)
            ? (int) $lastInsertId
            : $lastInsertId;
    }

    /**
     * Retrieve a single record.
     */
    public function read(string $table, $where, $key = 'id')
    {
        $where = $this->where($where, $key);
        $stmt = static::$pdo->prepare(sprintf(
            'SELECT * FROM %s WHERE %s LIMIT 1',
            $this->quoteIdentifier($table),
            $where['sql']
        ));
        $stmt->execute($where['params']);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Update one or more records based on the shared conditions system.
     */
    public function update(string $table, array $values, $where, $key = 'id')
    {
        $where = $this->where($where, $key);
        // placeholders can't be mixed: positional for conditions with "?", named and prefixed
        // otherwise, so they don't collide with the ones of the conditions
        $positional = !empty($where['params']) && array_is_list($where['params']);
        $set = [];
        $params = [];

        foreach ($values as $column => $value) {
            $placeholder = $positional ? '?' : ':set_'.$column;
            $set[] = sprintf('%s = %s', $this->quoteIdentifier($column), $placeholder);

            if ($positional) {
                $params[] = $value;
            } else {
                $params[$placeholder] = $value;
            }
        }

        $stmt = static::$pdo->prepare(sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(', ', $set),
            $where['sql']
        ));

        return $stmt->execute($positional ? array_merge($params, $where['params']) : $params + $where['params']);
    }

    /**
     * Delete one or more records based on the shared conditions system.
     */
    public function delete(string $table, $where, $key = 'id')
    {
        $where = $this->where($where, $key);
        $stmt = static::$pdo->prepare(sprintf(
            'DELETE FROM %s WHERE %s',
            $this->quoteIdentifier($table),
            $where['sql']
        ));

        return $stmt->execute($where['params']);
    }

    // === TRANSACTIONS ===
    /**
     * @link https://www.php.net/manual/en/pdo.begintransaction.php
     */
    public function beginTransaction()
    {
        return static::$pdo->beginTransaction();
    }

    /**
     * @link https://www.php.net/manual/en/pdo.commit.php
     */
    public function commit()
    {
        return static::$pdo->commit();
    }

    /**
     * @link https://www.php.net/manual/en/pdo.rollback.php
     */
    public function rollBack()
    {
        return static::$pdo->rollBack();
    }

    /**
     * @link https://www.php.net/manual/en/pdo.intransaction.php
     */
    public function inTransaction()
    {
        return static::$pdo->inTransaction();
    }

    /**
     * Grouping of database operations, this allows their executions
     * without making changes if one of them fails.
     *
     * @return mixed The output of the callback.
     */
    public function transactional(callable $callback)
    {
        $this->beginTransaction();

        try {
            $output = $callback();

            $this->commit();

            return $output;
        } catch (Throwable $exception) {
            $this->rollBack();

            throw $exception;
        }
    }

    /**
     * Conditions of read, update and delete: associative arrays are
     * conditions like the ones of select, other values are matched by the key.
     */
    private function where($where, $key): array
    {
        return is_array($where) && !empty($where) && !array_is_list($where)
            ? $this->buildWhereCondition($where)
            : $this->buildWhereCondition($where, $key);
    }

    private function buildWhereCondition(): array
    {
        $value = func_get_arg(0);

        if (1 === func_num_args()) {
            if (empty($value)) {
                return [
                    'params' => [],
                    'sql' => ''
                ];
            }

            // 1 => `id` = 1
            // 'foo' => `id` = "foo"
            // [1, 'foo'] => `id` IN (1, "foo")
            if (is_scalar($value) || array_is_list($value)) {
                return $this->buildWhereCondition($value, 'id');
            }

            if (is_array($value)) {
                // ['field1 >= ? AND field1 <= ?' => [42, 100], 'field2 LIKE ?' => '%test%'] => (field1 >= ? AND field1 <= ?) AND field2 LIKE ?
                // ['bar' => 'foo', 'lorem' => 'ipsum'] => `bar` = "foo" AND `lorem` = "ipsum"
                $sql = '';
                $params = [];
                $positional = false;
                $named = false;

                // PDO can't combine named and positional placeholders (SQLite binds the
                // positional values to the named placeholders), so the conditions on the
                // columns use the placeholders of the SQL conditions
                foreach ($value as $k => $v) {
                    if (!preg_match('/^[a-zA-Z0-9\_]+$/', $k)) {
                        $positional = $positional || is_scalar($v) || (is_array($v) && !empty($v) && array_is_list($v));
                        $named = $named || (is_array($v) && !array_is_list($v));
                    }
                }

                if ($positional && $named) {
                    throw new InvalidArgumentException('Named and positional placeholders can\'t be combined in the same conditions.');
                }

                foreach ($value as $k => $v) {
                    if (preg_match('/^[a-zA-Z0-9\_]+$/', $k)) {
                        $column = $k;
                        $k = sprintf('%s = %s', $this->quoteIdentifier($column), $positional ? '?' : ':'.$column);
                        $v = $positional ? [$v] : [$column => $v];
                    }

                    if (!empty($sql)) {
                        $sql .= ' AND ';
                    }

                    $sql .= sprintf('(%s)', $k);

                    if (is_scalar($v)) {
                        $v = [$v];
                    }

                    if (array_is_list($v)) {
                        array_push($params, ...$v);

                        continue;
                    }

                    $params += $v;
                }

                return [
                    'params' => $params,
                    'sql' => $sql,
                ];
            }
        }

        if (2 === func_num_args()) {
            $key = func_get_arg(1);
            $prefixed = ':'.$key;

            if (is_array($value) && array_is_list($value)) {
                // [], 'bar' => no rows, instead of an invalid `bar` IN ()
                if (empty($value)) {
                    return [
                        'params' => [],
                        'sql' => '1 = 0',
                    ];
                }

                // [1, 2], 'bar' => `bar` IN (1, 2), a placeholder for each value
                $params = [];

                foreach (array_values($value) as $index => $item) {
                    $params[$prefixed.'_'.$index] = $item;
                }

                return [
                    'params' => $params,
                    'sql' => sprintf('%s IN (%s)', $this->quoteIdentifier($key), implode(', ', array_keys($params))),
                ];
            }

            // 'foo', 'bar' => `bar` = "foo"
            return [
                'params' => [
                    $prefixed => $value
                ],
                'sql' => sprintf('%s = %s', $this->quoteIdentifier($key), $prefixed),
            ];
        }
    }
};
