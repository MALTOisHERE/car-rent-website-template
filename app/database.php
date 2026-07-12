<?php

function db()
{
    global $mysqlconnection;
    return $mysqlconnection;
}

function dbFetchOne($sql, array $parameters = [])
{
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function dbFetchAll($sql, array $parameters = [])
{
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function dbExecute($sql, array $parameters = [])
{
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    return $statement;
}

function withTransaction(callable $callback)
{
    $pdo = db();
    $startedHere = !$pdo->inTransaction();
    if ($startedHere) {
        $pdo->beginTransaction();
    }

    try {
        $result = $callback($pdo);
        if ($startedHere) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $exception) {
        if ($startedHere && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function tableExists($tableName)
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $tableName)) {
        return false;
    }

    $statement = db()->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
         LIMIT 1'
    );
    $statement->execute(['table_name' => $tableName]);

    return (bool) $statement->fetchColumn();
}

