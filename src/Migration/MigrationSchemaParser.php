<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Migration;

final class MigrationSchemaParser
{
    public function __construct(
        private readonly MigrationSqlExtractor $sqlExtractor = new MigrationSqlExtractor(),
    ) {
    }

    /**
     * @param list<string> $migrationFiles
     */
    public function parseFiles(array $migrationFiles): SchemaState
    {
        $state = new SchemaState();

        foreach ($migrationFiles as $file) {
            foreach ($this->sqlExtractor->extractFromFile($file) as $statement) {
                $state->applyStatement($statement);
            }
        }

        return $state;
    }
}
