<?php

$deps['database'] = new class () {
    /**
     * Shared PDO instance.
     *
     * @var PDO
     */
    private static $pdo;

    /**
     * Set a shared PDO connection.
     */
    public static function setPdo(PDO $pdo)
    {
        static::$pdo = $pdo;
        static::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Execute a query and parse result rows as array.
     */
    public function query(string $sql, array $params = array())
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
            $fields = array($fields);
        }

        if (is_array($fields)) {
            $fields = join(', ', array_map(function ($field) {
                return preg_match('/^[a-zA-Z0-9\_]+$/', $field)
                    ? sprintf('`%s`', $field)
                    : $field;
            }, $fields));
        }

        $sql = sprintf('SELECT %s FROM `%s`', $fields, $table);

        $params = array();

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
            'INSERT INTO `%s` (%s) VALUES (:%s)',
            $table,
            implode(', ', $keys),
            implode(', :', $keys)
        );

        $this->query($sql, $values);

        $lastInsertId = static::$pdo->lastInsertId();

        return is_numeric($lastInsertId)
            ? (int) $lastInsertId
            : $lastInsertId;
    }

    /**
     * Retrieve a single record.
     */
    public function read(string $table, $where, $key = 'id')
    {
        $where = $this->buildWhereCondition($where, $key);
        $stmt = static::$pdo->prepare(sprintf('SELECT * FROM `%s` WHERE %s LIMIT 1', $table, $where['sql']));
        $stmt->execute($where['params']);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Update one or more records based on the shared conditions system.
     */
    public function update(string $table, array $values, $where, $key = 'id')
    {
        $params = $values;
        $keys = array_keys($values);

        $set = join(', ', array_map(function ($key) {
            return sprintf('`%s` = :%s', $key, $key);
        }, $keys));

        $where = $this->buildWhereCondition($where, $key);
        $stmt = static::$pdo->prepare(sprintf('UPDATE `%s` SET %s WHERE %s', $table, $set, $where['sql']));

        return $stmt->execute(array_merge($where['params'], $params));
    }

    /**
     * Delete one or more records based on the shared conditions system.
     */
    public function delete(string $table, $where, $key = 'id')
    {
        $where = $this->buildWhereCondition($where, $key);
        $stmt = static::$pdo->prepare(sprintf('DELETE FROM `%s` WHERE %s', $table, $where['sql']));

        return $stmt->execute($where['params']);
    }

    private function buildWhereCondition(): array
    {
        $value = func_get_arg(0);

        if (1 === func_num_args()) {
            if (empty($value)) {
                return array(
                    'params' => array(),
                    'sql' => ''
                );
            }

            // 1 => `id` = 1
            // 'foo' => `id` = "foo"
            if (is_scalar($value) || is_numeric($value)) {
                return array(
                    'params' => array(
                        ':id' => $value
                    ),
                    'sql' => '`id` = :id',
                );
            }

            if (array_is_list($value)) {
                // [1, 'foo'] => `id` IN (1, "foo")
                return array(
                    'params' => array(
                        ':id' => $value
                    ),
                    'sql' => '`id` IN (:id)',
                );
            }

            if (is_array($value)) {
                // ['field1 >= ? AND field1 <= ?' => [42, 100], 'field2 LIKE ?' => '%test%'] => (field1 >= ? AND field1 <= ?) AND field2 LIKE ?
                // ['bar' => 'foo', 'lorem' => 'ipsum'] => `bar` = "foo" AND `lorem` = "ipsum"
                $sql = '';
                $params = array();

                foreach ($value as $k => $v) {
                    if (preg_match('/^[a-zA-Z0-9\_]+$/', $k)) {
                        $v = array($k => $v);
                        $k = sprintf('`%s` = :%s', $k, $k);
                    }

                    if (!empty($sql)) {
                        $sql .= ' AND ';
                    }

                    $sql .= sprintf('(%s)', $k);

                    if (is_scalar($v)) {
                        $v = array($v);
                    }

                    if (array_is_list($v)) {
                        array_push($params, ...$v);

                        continue;
                    }

                    $params += $v;
                }

                return array(
                    'params' => $params,
                    'sql' => $sql,
                );
            }
        }

        if (2 === func_num_args()) {
            $key = func_get_arg(1);
            $prefixed = ':'.$key;

            if (is_array($value) && array_is_list($value)) {
                // [1, 2], 'bar' => `bar` IN (1, 2)
                return array(
                    'params' => array(
                        $prefixed => $value
                    ),
                    'sql' => sprintf('`%s` IN (%s)', $key, $prefixed),
                );
            }

            // 'foo', 'bar' => `bar` = "foo"
            return array(
                'params' => array(
                    $prefixed => $value
                ),
                'sql' => sprintf('`%s` = %s', $key, $prefixed),
            );
        }
    }
};
