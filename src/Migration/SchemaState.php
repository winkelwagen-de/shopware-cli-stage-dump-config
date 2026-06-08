<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Migration;

final class SchemaState
{
    /** @var array<string, list<string>> */
    private array $tables = [];

    /** @var array<string, true> */
    private array $createdTables = [];

    /** @var array<string, array<string, true>> */
    private array $alteredColumns = [];

    /** @var list<string> */
    private array $warnings = [];

    public function applyStatement(string $sql): void
    {
        if (preg_match('/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`([^`]+)`/i', $sql, $match)) {
            $table = strtolower($match[1]);
            unset($this->tables[$table], $this->createdTables[$table], $this->alteredColumns[$table]);

            return;
        }

        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`\s*\((.*)\)\s*(?:ENGINE|DEFAULT|COMMENT|;|$)/is', $sql, $match)) {
            $table = strtolower($match[1]);
            $this->tables[$table] = $this->parseColumnsFromCreateBody($match[2]);
            $this->createdTables[$table] = true;
            unset($this->alteredColumns[$table]);

            return;
        }

        if (preg_match('/ALTER\s+TABLE\s+`([^`]+)`\s+ADD\s+(?:COLUMN\s+)?`([^`]+)`/i', $sql, $match)) {
            $table = strtolower($match[1]);
            $column = strtolower($match[2]);
            $this->tables[$table] ??= [];
            if (!\in_array($column, $this->tables[$table], true)) {
                $this->tables[$table][] = $column;
            }
            $this->alteredColumns[$table][$column] = true;

            return;
        }

        if (preg_match('/ALTER\s+TABLE\s+`([^`]+)`\s+DROP\s+COLUMN\s+`([^`]+)`/i', $sql, $match)) {
            $table = strtolower($match[1]);
            $column = strtolower($match[2]);
            if (isset($this->tables[$table])) {
                $this->tables[$table] = array_values(array_filter(
                    $this->tables[$table],
                    static fn (string $existing): bool => $existing !== $column
                ));
            }
            unset($this->alteredColumns[$table][$column]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function tables(): array
    {
        return $this->tables;
    }

    public function isCreatedTable(string $table): bool
    {
        return isset($this->createdTables[strtolower($table)]);
    }

    /**
     * @return list<string>
     */
    public function alteredColumnsFor(string $table): array
    {
        $table = strtolower($table);
        if (!isset($this->alteredColumns[$table])) {
            return [];
        }

        return array_keys($this->alteredColumns[$table]);
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    /**
     * @return list<string>
     */
    private function parseColumnsFromCreateBody(string $body): array
    {
        $columns = [];

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '--')) {
                continue;
            }

            $line = rtrim($line, ',');
            if (preg_match('/^(?:PRIMARY\s+KEY|UNIQUE\s+KEY|KEY|INDEX|CONSTRAINT|FOREIGN\s+KEY)\b/i', $line)) {
                continue;
            }

            if (preg_match('/^`([^`]+)`/', $line, $match)) {
                $columns[] = strtolower($match[1]);
            }
        }

        return $columns;
    }
}
