<?php

declare(strict_types=1);

/**
 * Thin mysqli wrapper. Every query goes through a prepared statement, so
 * user input is always sent separately from the SQL text.
 * mysqli is configured to throw exceptions instead of returning false.
 */

function db(): mysqli
{
    static $connection = null;
    if ($connection instanceof mysqli) {
        return $connection;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $config = app_config()['db'];
    $connection = new mysqli($config['host'], $config['user'], $config['password'], $config['name'], $config['port']);
    $connection->set_charset('utf8mb4');

    return $connection;
}

/**
 * Prepares and executes a statement. Parameter types are derived from the PHP
 * values: int -> i, float -> d, everything else (including decimal strings
 * such as prices) -> s.
 *
 * @param list<int|float|string|bool|null> $params
 */
function db_execute_statement(string $sql, array $params = []): mysqli_stmt
{
    $statement = db()->prepare($sql);

    if ($params !== []) {
        $types = '';
        $values = [];
        foreach ($params as $param) {
            if (is_bool($param)) {
                $param = (int) $param;
            }
            $types .= match (true) {
                is_int($param) => 'i',
                is_float($param) => 'd',
                default => 's',
            };
            $values[] = $param;
        }
        $statement->bind_param($types, ...$values);
    }

    $statement->execute();

    return $statement;
}

/**
 * @param list<int|float|string|bool|null> $params
 * @return list<array<string, mixed>>
 */
function db_fetch_all(string $sql, array $params = []): array
{
    $statement = db_execute_statement($sql, $params);
    $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();

    return $rows;
}

/**
 * @param list<int|float|string|bool|null> $params
 * @return array<string, mixed>|null
 */
function db_fetch_one(string $sql, array $params = []): ?array
{
    $rows = db_fetch_all($sql, $params);

    return $rows[0] ?? null;
}

/**
 * @param list<int|float|string|bool|null> $params
 */
function db_fetch_value(string $sql, array $params = []): mixed
{
    $row = db_fetch_one($sql, $params);

    return $row === null ? null : reset($row);
}

/**
 * Runs an INSERT / UPDATE / DELETE and returns the number of affected rows.
 *
 * @param list<int|float|string|bool|null> $params
 */
function db_execute(string $sql, array $params = []): int
{
    $statement = db_execute_statement($sql, $params);
    $affectedRows = $statement->affected_rows;
    $statement->close();

    return (int) $affectedRows;
}

/**
 * Runs an INSERT and returns the generated AUTO_INCREMENT id.
 *
 * @param list<int|float|string|bool|null> $params
 */
function db_insert(string $sql, array $params = []): int
{
    $statement = db_execute_statement($sql, $params);
    $insertId = $statement->insert_id;
    $statement->close();

    return (int) $insertId;
}

/**
 * Runs $callback inside a transaction; commits on success, rolls back and
 * re-throws on any exception.
 *
 * @template T
 * @param callable(): T $callback
 * @return T
 */
function db_transaction(callable $callback): mixed
{
    $connection = db();
    $connection->begin_transaction();

    try {
        $result = $callback();
        $connection->commit();
        return $result;
    } catch (Throwable $exception) {
        $connection->rollback();
        throw $exception;
    }
}

/** MySQL error code for a violated UNIQUE / PRIMARY KEY constraint. */
const DB_ERROR_DUPLICATE_ENTRY = 1062;

/** MySQL error code for deleting a row that other rows still reference. */
const DB_ERROR_ROW_IS_REFERENCED = 1451;
