<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Scan;

use ShopwareGdprDump\Heuristics\ColumnHeuristics;
use ShopwareGdprDump\Heuristics\TableHeuristics;
use ShopwareGdprDump\Migration\MigrationSchemaParser;

final class PluginMigrationScanner
{
    public function __construct(
        private readonly MigrationSchemaParser $parser = new MigrationSchemaParser(),
        private readonly ColumnHeuristics $columnHeuristics = new ColumnHeuristics(),
        private readonly TableHeuristics $tableHeuristics = new TableHeuristics(),
    ) {
    }

    /**
     * @return array{
     *   plugin: array<string, string>,
     *   tables: array<string, array<string, mixed>>,
     *   warnings: list<string>,
     *   todos: list<string>
     * }
     */
    public function scan(string $pluginPath, string $composerName, ?string $label = null): array
    {
        $migrationDir = rtrim($pluginPath, '/') . '/src/Migration';
        if (!is_dir($migrationDir)) {
            throw new \RuntimeException(sprintf('Migration directory not found: %s', $migrationDir));
        }

        $files = glob($migrationDir . '/Migration*.php') ?: [];
        usort($files, static fn (string $a, string $b): int => self::extractTimestamp($a) <=> self::extractTimestamp($b));

        if ($files === []) {
            throw new \RuntimeException(sprintf('No migration files found in %s', $migrationDir));
        }

        $state = $this->parser->parseFiles($files);
        $tables = [];
        $warnings = $state->warnings();

        foreach ($state->tables() as $table => $columns) {
            if ($state->isCreatedTable($table)) {
                $tables[$table] = $this->buildCreatedTableConfig($table, $columns);
                continue;
            }

            $altered = $state->alteredColumnsFor($table);
            if ($altered === []) {
                continue;
            }

            $tables[$table] = $this->buildColumnReviewConfig($altered);
        }

        ksort($tables);

        return [
            'plugin' => array_filter([
                'name' => $composerName,
                'label' => $label ?? (new PluginMetadataResolver())->resolveLabel($pluginPath, $composerName),
            ]),
            'tables' => $tables,
            'warnings' => $warnings,
            'todos' => [],
        ];
    }

    public static function guessComposerName(string $pluginPath): ?string
    {
        $resolver = new PluginMetadataResolver();

        try {
            return $resolver->resolveComposerName($pluginPath);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param list<string> $columns
     *
     * @return array<string, mixed>
     */
    private function buildCreatedTableConfig(string $table, array $columns): array
    {
        $config = $this->buildColumnReviewConfig($columns);

        $tableSuggestion = $this->tableHeuristics->suggest($table);
        if ($tableSuggestion !== null) {
            $config['nodata'] = true;
            $config['_comment'] = 'table name matches nodata heuristic';
            $config['_remove_table'] = true;
        }

        return $config;
    }

    /**
     * @param list<string> $columns
     *
     * @return array<string, mixed>
     */
    private function buildColumnReviewConfig(array $columns): array
    {
        $rewrite = [];
        $uncertain = [];
        $structural = [];

        foreach ($columns as $column) {
            $classification = $this->columnHeuristics->classifyForDraft($column);
            $rewrite[$column] = $classification['value'];

            if ($classification['structural']) {
                $structural[$column] = true;
            }

            if ($classification['uncertain']) {
                $uncertain[$column] = true;
            }
        }

        ksort($rewrite);

        return [
            'rewrite' => $rewrite,
            '_uncertain' => $uncertain,
            '_structural' => $structural,
        ];
    }

    private static function extractTimestamp(string $file): int
    {
        if (preg_match('/Migration(\d+)/', basename($file), $match)) {
            return (int) $match[1];
        }

        return 0;
    }
}
