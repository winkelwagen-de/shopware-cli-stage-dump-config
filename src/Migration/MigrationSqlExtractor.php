<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Migration;

final class MigrationSqlExtractor
{
    /**
     * @return list<string>
     */
    public function extractFromFile(string $filePath): array
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read migration file: %s', $filePath));
        }

        return $this->extractFromSource($contents);
    }

    /**
     * @return list<string>
     */
    public function extractFromSource(string $source): array
    {
        $statements = [];

        if (preg_match_all('/<<<[\'"]?SQL[\'"]?\s*\R(.*?)\R\s*SQL;/si', $source, $matches)) {
            foreach ($matches[1] as $sqlBlock) {
                $statements = [...$statements, ...$this->splitStatements($sqlBlock)];
            }
        }

        if (preg_match_all('/executeStatement\s*\(\s*([\'"])(.*?)\1/s', $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statements = [...$statements, ...$this->splitStatements($match[2])];
            }
        }

        return array_values(array_filter(array_map('trim', $statements)));
    }

    /**
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $parts = preg_split('/;\s*(?:\R|$)/', $sql) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }
}
