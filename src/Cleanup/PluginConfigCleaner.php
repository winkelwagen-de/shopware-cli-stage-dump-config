<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Cleanup;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class PluginConfigCleaner
{
    /**
     * @return list<string> errors
     */
    public function cleanFile(string $file, bool $dryRun = false): array
    {
        try {
            $parsed = Yaml::parseFile($file);
        } catch (ParseException $e) {
            return [sprintf('%s: YAML parse error: %s', $file, $e->getMessage())];
        }

        if (!\is_array($parsed)) {
            return [sprintf('%s: root must be a mapping', $file)];
        }

        $cleaned = $this->cleanConfig($parsed);
        $yaml = Yaml::dump($cleaned, 6, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

        if (!$dryRun) {
            file_put_contents($file, $yaml);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function cleanConfig(array $config): array
    {
        $tables = $config['dump']['tables'] ?? null;
        if (!\is_array($tables)) {
            return $config;
        }

        $cleanTables = [];

        foreach ($tables as $tableName => $tableConfig) {
            if (!\is_array($tableConfig)) {
                continue;
            }

            $cleanedTable = [];

            if (($tableConfig['nodata'] ?? false) === true || ($tableConfig['ignore'] ?? false) === true) {
                $cleanedTable['nodata'] = true;
            }

            $rewrite = $tableConfig['rewrite'] ?? [];
            if (\is_array($rewrite)) {
                $cleanRewrite = [];
                foreach ($rewrite as $column => $value) {
                    if (!\is_string($value)) {
                        continue;
                    }

                    $trimmed = trim($value);
                    if (\in_array($trimmed, ['skip', 'review'], true)) {
                        continue;
                    }

                    $cleanRewrite[(string) $column] = $value;
                }

                if ($cleanRewrite !== []) {
                    ksort($cleanRewrite);
                    $cleanedTable['rewrite'] = $cleanRewrite;
                }
            }

            if ($cleanedTable === []) {
                continue;
            }

            $cleanTables[(string) $tableName] = $cleanedTable;
        }

        ksort($cleanTables);

        if ($cleanTables === []) {
            unset($config['dump']);
        } else {
            $config['dump'] = ['tables' => $cleanTables];
        }

        return $config;
    }

    /**
     * @return list<string>
     */
    public function resolvePluginFiles(string $projectRoot, array $files = []): array
    {
        if ($files !== []) {
            return array_map(
                fn (string $file): string => str_starts_with($file, '/') ? $file : $projectRoot . '/' . $file,
                $files
            );
        }

        $pluginsDir = $projectRoot . '/plugins';
        if (!is_dir($pluginsDir)) {
            return [];
        }

        $all = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pluginsDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            if (preg_match('/\.ya?ml$/', $path) !== 1) {
                continue;
            }

            if (str_contains($path, '/plugins/_')) {
                continue;
            }

            $all[] = $path;
        }

        sort($all);

        return $all;
    }
}
