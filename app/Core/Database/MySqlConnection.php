<?php
declare(strict_types=1);

namespace App\Core\Database;

use App\Core\Config;
use App\Core\Contracts\ConnectionInterface;
use mysqli;
use mysqli_stmt;
use RuntimeException;

/**
 * MySQLi connection wrapper. Every query uses prepared statements with bound parameters.
 */
final class MySqlConnection implements ConnectionInterface
{
    public function __construct(
        private mysqli $mysqli,
        private string $databaseName
    ) {
    }

    /**
     * @param string $prefix Configuration prefix, e.g. CORE_DB or TENANT_DB.
     */
    public static function fromConfig(string $prefix, string $database): self
    {
        $host = (string) Config::get($prefix . '_HOST', '127.0.0.1');
        $port = Config::int($prefix . '_PORT', 3306);
        $user = (string) Config::get($prefix . '_USER', '');
        $password = (string) Config::get($prefix . '_PASSWORD', Config::get('DB_PASSWORD', '') ?? '');
        $name = (string) Config::get($prefix . '_NAME', $database);

        return self::connect($host, $port, $name, $user, $password);
    }

    public static function connect(string $host, int $port, string $database, string $user, string $password): self
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $mysqli = new mysqli($host, $user, $password, $database, $port);
            $mysqli->set_charset('utf8mb4');
        } catch (\mysqli_sql_exception $exception) {
            // Never surface credentials or driver details to the caller.
            throw new RuntimeException(
                'Koneksi database gagal. Periksa konfigurasi database pada environment.',
                0,
                $exception
            );
        }

        return new self($mysqli, $database);
    }

    public function select(string $sql, array $params = []): array
    {
        $statement = $this->run($sql, $params);
        $result = $statement->get_result();
        $rows = $result instanceof \mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $statement->close();

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }

    public function selectOne(string $sql, array $params = []): ?array
    {
        $rows = $this->select($sql, $params);

        return $rows[0] ?? null;
    }

    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->run($sql, $params);
        $affected = $statement->affected_rows;
        $statement->close();

        return $affected;
    }

    public function insert(string $sql, array $params = []): int
    {
        $statement = $this->run($sql, $params);
        $id = (int) $this->mysqli->insert_id;
        $statement->close();

        return $id;
    }

    public function transaction(callable $callback): mixed
    {
        $this->mysqli->begin_transaction();

        try {
            $result = $callback($this);
            $this->mysqli->commit();

            return $result;
        } catch (\Throwable $throwable) {
            $this->mysqli->rollback();
            throw $throwable;
        }
    }

    public function name(): string
    {
        return $this->databaseName;
    }

    public function raw(): mysqli
    {
        return $this->mysqli;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private function run(string $sql, array $params): mysqli_stmt
    {
        $statement = $this->mysqli->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Query tidak dapat disiapkan.');
        }

        if ($params !== []) {
            $types = '';
            $values = [];
            foreach (array_values($params) as $value) {
                if (is_int($value) || is_bool($value)) {
                    $types .= 'i';
                    $values[] = (int) $value;
                } elseif (is_float($value)) {
                    $types .= 'd';
                    $values[] = $value;
                } else {
                    $types .= 's';
                    $values[] = $value === null ? null : (string) $value;
                }
            }

            $statement->bind_param($types, ...$values);
        }

        $statement->execute();

        return $statement;
    }
}
