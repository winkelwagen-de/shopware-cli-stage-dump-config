<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Build;

use ShopwareGdprDump\Util\FakerNormalizer;

final class PluginConfigMerger
{
    public function __construct(
        private readonly ShorthandExpander $shorthandExpander = new ShorthandExpander(),
    ) {
    }

    /**
     * @param list<array<string, mixed>> $pluginConfigs keyed by composer name
     *
     * @return array{rewrite: array<string, array<string, string>>, nodata: list<string>, ignore: list<string>}
     */
    public function merge(array $pluginConfigs): array
    {
        ksort($pluginConfigs);

        $mergedRewrite = [];
        $nodata = [];
        $ignore = [];
        $conflicts = [];

        foreach ($pluginConfigs as $composerName => $config) {
            $expanded = $this->shorthandExpander->expandPluginDump($config);

            foreach ($expanded['nodata'] as $table) {
                $nodata[$table] = true;
            }

            foreach ($expanded['ignore'] as $table) {
                $ignore[$table] = true;
            }

            foreach ($expanded['rewrite'] as $table => $columns) {
                foreach ($columns as $column => $value) {
                    if (isset($mergedRewrite[$table][$column])) {
                        $conflicts[] = sprintf(
                            'Conflict on %s.%s between plugins (existing value vs %s)',
                            $table,
                            $column,
                            $composerName
                        );
                        continue;
                    }

                    $mergedRewrite[$table][$column] = FakerNormalizer::normalize($value);
                }
            }
        }

        if ($conflicts !== []) {
            throw new \RuntimeException("Merge conflicts detected:\n- " . implode("\n- ", $conflicts));
        }

        ksort($mergedRewrite);
        foreach ($mergedRewrite as $table => $columns) {
            ksort($mergedRewrite[$table]);
        }

        $nodataList = array_keys($nodata);
        sort($nodataList);

        $ignoreList = array_keys($ignore);
        sort($ignoreList);

        return [
            'rewrite' => $mergedRewrite,
            'nodata' => $nodataList,
            'ignore' => $ignoreList,
        ];
    }
}
