<?php

namespace App\Services;

use App\Database\Connection;
use PDO;
use RuntimeException;

class BackupService
{
    private PDO $db;
    private string $backupDirectory;

    public function __construct(?PDO $db = null, ?string $backupDirectory = null)
    {
        $this->db = $db ?? Connection::get();

        $this->backupDirectory = $backupDirectory
            ?? dirname(__DIR__, 2) . '/storage/backups';
    }

    /**
     * Create a SQL backup of the entire database.
     *
     * @return string Absolute path to the generated backup file.
     */
    public function createBackup(): string
    {
        $this->ensureBackupDirectory();

        $timestamp = date('Y-m-d_H-i-s');
        $filename = "tapp_{$timestamp}.sql";
        $path = $this->backupDirectory . DIRECTORY_SEPARATOR . $filename;

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Unable to create backup file: {$path}");
        }

        try {
            $this->writeLine($handle, '-- TAPP Database Backup');
            $this->writeLine($handle, '-- Generated: ' . date('c'));
            $this->writeLine($handle, '');

            $this->writeLine($handle, 'SET FOREIGN_KEY_CHECKS=0;');
            $this->writeLine($handle, 'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";');
            $this->writeLine($handle, '');

            $tables = $this->getTables();

            foreach ($tables as $table) {
                $this->writeTableBackup($handle, $table);
            }

            $this->writeLine($handle, 'SET FOREIGN_KEY_CHECKS=1;');

            fclose($handle);

            return $path;
        } catch (\Throwable $e) {
            fclose($handle);

            if (is_file($path)) {
                unlink($path);
            }

            throw $e;
        }
    }

    /**
     * Get all base tables in the configured database.
     *
     * @return string[]
     */
    private function getTables(): array
    {
        $statement = $this->db->query(
            'SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_TYPE = \'BASE TABLE\'
             ORDER BY TABLE_NAME'
        );

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Write one table's structure and data to the backup.
     *
     * @param resource $handle
     */
    private function writeTableBackup($handle, string $table): void
    {
        $quotedTable = $this->quoteIdentifier($table);

        $this->writeLine($handle, "-- ----------------------------------------");
        $this->writeLine($handle, "-- Table: {$table}");
        $this->writeLine($handle, "-- ----------------------------------------");
        $this->writeLine($handle, '');

        $statement = $this->db->query("SHOW CREATE TABLE {$quotedTable}");
        $createTable = $statement->fetch(PDO::FETCH_ASSOC);

        if ($createTable === false) {
            throw new RuntimeException("Unable to retrieve structure for table: {$table}");
        }

        $createSql = $createTable['Create Table'] ?? null;

        if (!is_string($createSql)) {
            throw new RuntimeException("Invalid CREATE TABLE result for: {$table}");
        }

        $this->writeLine($handle, "DROP TABLE IF EXISTS {$quotedTable};");
        $this->writeLine($handle, $createSql . ';');
        $this->writeLine($handle, '');

        $rows = $this->db->query("SELECT * FROM {$quotedTable}");

        $columns = $this->getColumnNames($rows);

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = $this->quoteValue($row[$column]);
            }

            $columnList = implode(
                ', ',
                array_map(fn (string $column) => $this->quoteIdentifier($column), $columns)
            );

            $valueList = implode(', ', $values);

            $this->writeLine(
                $handle,
                "INSERT INTO {$quotedTable} ({$columnList}) VALUES ({$valueList});"
            );
        }

        $this->writeLine($handle, '');
    }

    /**
     * @param \PDOStatement $statement
     * @return string[]
     */
    private function getColumnNames($statement): array
    {
        $count = $statement->columnCount();
        $columns = [];

        for ($i = 0; $i < $count; $i++) {
            $metadata = $statement->getColumnMeta($i);

            if (!isset($metadata['name'])) {
                throw new RuntimeException('Unable to determine database column name.');
            }

            $columns[] = $metadata['name'];
        }

        return $columns;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $this->db->quote((string) $value);
    }

    /**
     * @param resource $handle
     */
    private function writeLine($handle, string $line): void
    {
        if (fwrite($handle, $line . PHP_EOL) === false) {
            throw new RuntimeException('Unable to write to backup file.');
        }
    }

    private function ensureBackupDirectory(): void
    {
        if (is_dir($this->backupDirectory)) {
            return;
        }

        if (!mkdir($this->backupDirectory, 0755, true) && !is_dir($this->backupDirectory)) {
            throw new RuntimeException(
                "Unable to create backup directory: {$this->backupDirectory}"
            );
        }
    }
}
