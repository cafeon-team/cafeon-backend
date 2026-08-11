<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportDatabaseDbml extends Command
{
    protected $signature = 'db:export-dbml {--output=docs/cafeon-current.dbml} {--include-system}';

    protected $description = 'Export the current MySQL schema as dbdiagram.io DBML';

    private const SYSTEM_TABLES = [
        'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs',
        'migrations', 'password_reset_tokens', 'personal_access_tokens', 'sessions',
    ];

    public function handle(): int
    {
        abort_unless(DB::getDriverName() === 'mysql', 1, 'This exporter currently supports MySQL only.');
        $database = DB::getDatabaseName();
        $tables = collect(DB::select(
            'SELECT TABLE_NAME AS table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
            [$database, 'BASE TABLE'],
        ))->pluck('table_name');

        $domainTables = $tables->reject(fn ($table) => in_array($table, self::SYSTEM_TABLES, true));
        $systemTables = $tables->filter(fn ($table) => in_array($table, self::SYSTEM_TABLES, true));
        $lines = [
            '// CafeON current database schema',
            '// Generated from MySQL database: '.$database,
            '// Import this file at https://dbdiagram.io',
            '',
            'Project CafeON {',
            "  database_type: 'MySQL'",
            "  Note: 'CafeON Laravel backend current schema'",
            '}',
            '',
            '// ==================== CafeON domain tables ====================',
            '',
        ];

        foreach ($domainTables as $table) {
            $lines = [...$lines, ...$this->tableLines($database, $table)];
        }

        if ($this->option('include-system') && $systemTables->isNotEmpty()) {
            $lines[] = '// ==================== Laravel system tables ====================';
            $lines[] = '';
            foreach ($systemTables as $table) {
                $lines = [...$lines, ...$this->tableLines($database, $table)];
            }
        }

        $lines[] = '// ==================== Foreign key relationships ====================';
        $lines[] = '';
        $includedTables = $this->option('include-system') ? $tables : $domainTables;
        foreach ($this->foreignKeys($database) as $foreignKey) {
            if (! $includedTables->contains($foreignKey->table_name) || ! $includedTables->contains($foreignKey->referenced_table_name)) {
                continue;
            }
            $delete = strtolower((string) $foreignKey->delete_rule);
            $lines[] = sprintf(
                'Ref: `%s`.`%s` > `%s`.`%s` [delete: %s]',
                $foreignKey->table_name,
                $foreignKey->column_name,
                $foreignKey->referenced_table_name,
                $foreignKey->referenced_column_name,
                $delete,
            );
        }

        $output = base_path((string) $this->option('output'));
        File::ensureDirectoryExists(dirname($output));
        File::put($output, implode(PHP_EOL, $lines).PHP_EOL);
        $this->info("DBML exported: {$output}");
        $this->info($domainTables->count().' domain table(s)'.($this->option('include-system') ? ', '.$systemTables->count().' Laravel system table(s)' : '').'.');

        return self::SUCCESS;
    }

    private function tableLines(string $database, string $table): array
    {
        $columns = DB::select(
            'SELECT COLUMN_NAME AS column_name, DATA_TYPE AS data_type, COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default, COLUMN_KEY AS column_key, EXTRA AS extra, COLUMN_COMMENT AS column_comment FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$database, $table],
        );
        $indexes = collect(DB::select(
            "SELECT INDEX_NAME AS index_name, NON_UNIQUE AS non_unique, GROUP_CONCAT(CONCAT('`', COLUMN_NAME, '`') ORDER BY SEQ_IN_INDEX SEPARATOR ', ') AS columns_list FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME <> 'PRIMARY' GROUP BY INDEX_NAME, NON_UNIQUE ORDER BY INDEX_NAME",
            [$database, $table],
        ));

        $lines = ["Table `{$table}` {"];
        foreach ($columns as $column) {
            $settings = [];
            if ($column->column_key === 'PRI') {
                $settings[] = 'pk';
            }
            if (str_contains((string) $column->extra, 'auto_increment')) {
                $settings[] = 'increment';
            }
            if ($column->is_nullable === 'NO') {
                $settings[] = 'not null';
            }
            if ($column->column_default !== null) {
                $settings[] = 'default: '.$this->defaultValue($column->column_default);
            }
            if ($column->column_comment !== '') {
                $settings[] = "note: '".str_replace("'", "\\'", $column->column_comment)."'";
            } elseif ($column->data_type === 'enum') {
                $settings[] = "note: '".str_replace("'", '', $column->column_type)."'";
            }
            $suffix = $settings === [] ? '' : ' ['.implode(', ', $settings).']';
            $lines[] = sprintf('  `%s` %s%s', $column->column_name, $this->dbmlType($column), $suffix);
        }

        if ($indexes->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '  indexes {';
            foreach ($indexes as $index) {
                $type = (int) $index->non_unique === 0 ? ' [unique]' : '';
                $lines[] = '    ('.$index->columns_list.')'.$type;
            }
            $lines[] = '  }';
        }
        $lines[] = '}';
        $lines[] = '';

        return $lines;
    }

    private function dbmlType(object $column): string
    {
        return match ($column->data_type) {
            'bigint' => 'bigint',
            'int', 'mediumint', 'smallint' => 'int',
            'tinyint' => str_starts_with($column->column_type, 'tinyint(1)') ? 'boolean' : 'int',
            'decimal' => preg_replace('/ unsigned$/', '', $column->column_type),
            'varchar', 'char' => $column->column_type,
            'enum' => 'varchar(30)',
            'longtext' => 'text',
            default => $column->data_type,
        };
    }

    private function defaultValue(mixed $value): string
    {
        $value = (string) $value;
        if (preg_match('/^CURRENT_TIMESTAMP/i', $value)) {
            return '`'.$value.'`';
        }
        if (is_numeric($value)) {
            return $value;
        }

        return "'".str_replace("'", "\\'", $value)."'";
    }

    private function foreignKeys(string $database): array
    {
        return DB::select(
            'SELECT k.TABLE_NAME AS table_name, k.COLUMN_NAME AS column_name, k.REFERENCED_TABLE_NAME AS referenced_table_name, k.REFERENCED_COLUMN_NAME AS referenced_column_name, r.DELETE_RULE AS delete_rule FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            [$database],
        );
    }
}
