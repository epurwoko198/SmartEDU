<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Contracts\ConnectionInterface;
use RuntimeException;

/**
 * Runs versioned SQL migrations stored as PHP files returning a list of statements.
 * The same runner is used for the core database and for every tenant database.
 */
final class MigrationRunner
{
    public function __construct(private string $directory)
    {
    }

    /**
     * @return list<string> Applied migration names.
     */
    public function run(ConnectionInterface $db): array
    {
        $this->ensureTable($db);

        $applied = $this->appliedMigrations($db);
        $batch = $this->nextBatch($db);
        $executed = [];

        foreach ($this->migrationFiles() as $name => $path) {
            if (in_array($name, $applied, true)) {
                continue;
            }

            $statements = (static fn (string $file): mixed => require $file)($path);
            if (!is_array($statements)) {
                throw new RuntimeException('Migrasi tidak mengembalikan daftar statement: ' . $name);
            }

            foreach ($statements as $statement) {
                if (!is_string($statement) || trim($statement) === '') {
                    continue;
                }

                $db->execute($statement);
            }

            $db->insert(
                'INSERT INTO schema_migrations (migration, batch, applied_at) VALUES (?, ?, ?)',
                [$name, $batch, date('Y-m-d H:i:s')]
            );

            $executed[] = $name;
        }

        return $executed;
    }

    /**
     * @return list<string>
     */
    public function appliedMigrations(ConnectionInterface $db): array
    {
        $this->ensureTable($db);

        $rows = $db->select('SELECT migration FROM schema_migrations ORDER BY migration ASC');

        return array_values(array_map(static fn (array $row): string => (string) $row['migration'], $rows));
    }

    /**
     * @return array<string, string> name => absolute path, sorted by name.
     */
    public function migrationFiles(): array
    {
        if (!is_dir($this->directory)) {
            throw new RuntimeException('Direktori migrasi tidak ditemukan: ' . $this->directory);
        }

        $files = glob(rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);

        $migrations = [];
        foreach ($files as $file) {
            $migrations[basename($file, '.php')] = $file;
        }

        return $migrations;
    }

    private function ensureTable(ConnectionInterface $db): void
    {
        $db->execute(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration VARCHAR(191) NOT NULL,
                batch INT UNSIGNED NOT NULL DEFAULT 1,
                applied_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_schema_migrations_migration (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function nextBatch(ConnectionInterface $db): int
    {
        $row = $db->selectOne('SELECT COALESCE(MAX(batch), 0) AS current_batch FROM schema_migrations');

        return (int) ($row['current_batch'] ?? 0) + 1;
    }
}
