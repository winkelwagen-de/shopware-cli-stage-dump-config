<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Heuristics;

final class TableHeuristics
{
    /**
     * @return array{nodata?: true, ignore?: true, uncertain?: bool}|null
     */
    public function suggest(string $table): ?array
    {
        $table = strtolower($table);

        if (preg_match('/(?:^|_)cache(?:$|_)/', $table) === 1 || str_ends_with($table, '_cache')) {
            return ['ignore' => true, 'uncertain' => false];
        }

        if (preg_match('/(?:_log|_queue|_index|_tmp|_history|messenger)/', $table) === 1) {
            return ['nodata' => true, 'uncertain' => str_contains($table, '_index')];
        }

        if (str_ends_with($table, '_staging')) {
            return ['ignore' => true, 'uncertain' => true];
        }

        return null;
    }
}
