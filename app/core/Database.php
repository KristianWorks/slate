<?php
/**
 * app/core/Database.php
 *
 * PDO-backed database singleton. Every query goes through prepared
 * statements — never concatenate user input into SQL.
 */

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

final class Database
{
    private static ?self $instance = null;

    private readonly PDO $pdo;

    /**
     * @param array{
     *   host:string, port:int|string, database:string,
     *   username:string, password:string, charset?:string
     * } $config
     */
    private function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            (int) $config['port'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4',
        );

        try {
            $this->pdo = new PDO(
                dsn:      $dsn,
                username: $config['username'],
                password: $config['password'],
                options:  [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ],
            );
        } catch (PDOException $e) {
            // Log critical BEFORE rethrowing so the failure makes it to disk
            // even if the uncaught exception handler can't render the 500.
            try {
                Logger::getInstance()->critical('Database connection failed', [
                    'host'    => (string) ($config['host']     ?? ''),
                    'port'    => (int)    ($config['port']     ?? 0),
                    'database'=> (string) ($config['database'] ?? ''),
                    'error'   => $e->getMessage(),
                    'code'    => $e->getCode(),
                ]);
            } catch (\Throwable) {}
            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e,
            );
        }
    }

    /**
     * Initialise the singleton with the runtime DB config.
     * Called exactly once during bootstrap (see public/index.php).
     *
     * @param array<string,mixed> $config
     */
    public static function init(array $config): void
    {
        self::$instance ??= new self($config);
    }

    /** Retrieve the initialised singleton. */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('Database not initialised — call Database::init() first.');
        }
        return self::$instance;
    }

    /** Raw PDO handle for advanced use (transactions, lastInsertId, etc.). */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Run a prepared statement and return it.
     *
     * @param array<string,scalar|null>|list<scalar|null> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row.
     *
     * @param array<string,scalar|null>|list<scalar|null> $params
     * @return array<string,mixed>|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Fetch all rows.
     *
     * @param array<string,scalar|null>|list<scalar|null> $params
     * @return list<array<string,mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        /** @var list<array<string,mixed>> $rows */
        $rows = $this->query($sql, $params)->fetchAll();
        return $rows;
    }

    /**
     * Fetch a single scalar value from the first column of the first row.
     *
     * @param array<string,scalar|null>|list<scalar|null> $params
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    /**
     * Insert a row using an associative array and return the new id.
     *
     * @param array<string,scalar|null> $data
     */
    public function insert(string $table, array $data): string
    {
        $cols         = array_keys($data);
        $placeholders = array_map(static fn(string $c): string => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn(string $c): string => "`{$c}`", $cols)),
            implode(', ', $placeholders),
        );
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    /**
     * Update rows matching `$where` and return the affected row count.
     *
     * @param array<string,scalar|null> $data
     * @param array<string,scalar|null> $where
     */
    public function update(string $table, array $data, array $where): int
    {
        $sets   = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[]            = "`{$col}` = :set_{$col}";
            $params["set_{$col}"] = $val;
        }
        $conds = [];
        foreach ($where as $col => $val) {
            $conds[]               = "`{$col}` = :where_{$col}";
            $params["where_{$col}"] = $val;
        }
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $sets),
            implode(' AND ', $conds),
        );
        return $this->query($sql, $params)->rowCount();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /** Singletons cannot be cloned. */
    private function __clone() {}

    /** Singletons cannot be unserialised. */
    public function __wakeup(): void
    {
        throw new RuntimeException('Cannot unserialise a singleton.');
    }
}
