<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Validate;

use ShopwareGdprDump\Build\DumpConfigBuilder;
use ShopwareGdprDump\Build\PluginConfigMerger;
use ShopwareGdprDump\Util\FakerNormalizer;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class DumpConfigValidator
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    /**
     * @param list<string> $files relative or absolute paths; empty = all plugin files
     *
     * @return list<string> errors
     */
    public function validate(array $files = [], bool $checkDist = true, bool $checkQuality = false): array
    {
        $errors = [];
        $pluginFiles = $this->resolvePluginFiles($files);

        foreach ($pluginFiles as $file) {
            if ($checkQuality) {
                $errors = [...$errors, ...$this->validatePluginFileQuality($file)];
            }
            $errors = [...$errors, ...$this->validatePluginFile($file)];
        }

        $errors = [...$errors, ...$this->validateUniquePluginNames($pluginFiles)];
        $errors = [...$errors, ...$this->validateMergeConflicts()];
        $errors = [...$errors, ...$this->validateAgainstSchema($pluginFiles)];

        if ($checkDist) {
            $errors = [...$errors, ...$this->validateDistBuildable()];
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validatePluginFileQuality(string $file): array
    {
        return (new PluginFileQualityValidator())->validate($file);
    }

    /**
     * @return list<string>
     */
    private function validatePluginFile(string $file): array
    {
        $errors = [];

        try {
            $parsed = Yaml::parseFile($file);
        } catch (ParseException $e) {
            return [sprintf('%s: YAML parse error: %s', $file, $e->getMessage())];
        }

        if (!\is_array($parsed)) {
            return [sprintf('%s: root must be a mapping', $file)];
        }

        $plugin = $parsed['plugin'] ?? null;
        if (!\is_array($plugin) || !isset($plugin['name']) || !\is_string($plugin['name']) || $plugin['name'] === '') {
            $errors[] = sprintf('%s: plugin.name is required', $file);
        }

        $dump = $parsed['dump'] ?? null;
        if ($dump !== null && !\is_array($dump)) {
            $errors[] = sprintf('%s: dump must be a mapping', $file);
            return $errors;
        }

        if ($dump === null) {
            return $errors;
        }

        $tables = $dump['tables'] ?? null;
        if ($tables !== null && !\is_array($tables)) {
            $errors[] = sprintf('%s: dump.tables must be a mapping', $file);
            return $errors;
        }

        if (!\is_array($tables)) {
            return $errors;
        }

        foreach ($tables as $table => $tableConfig) {
            if (!\is_array($tableConfig)) {
                $errors[] = sprintf('%s: dump.tables.%s must be a mapping', $file, $table);
                continue;
            }

            $rewrites = $tableConfig['rewrite'] ?? [];
            if ($rewrites !== [] && !\is_array($rewrites)) {
                $errors[] = sprintf('%s: dump.tables.%s.rewrite must be a mapping', $file, $table);
                continue;
            }

            if (\is_array($rewrites)) {
                foreach ($rewrites as $column => $value) {
                    if (!\is_string($value)) {
                        $errors[] = sprintf('%s: dump.tables.%s.rewrite.%s must be a string', $file, $table, $column);
                        continue;
                    }

                    $expanded = (new \ShopwareGdprDump\Build\ShorthandExpander())->expandValue($value);
                    if ($expanded === null) {
                        if (\in_array(trim($value), ['skip', 'review'], true)) {
                            $errors[] = sprintf('%s: remove draft marker "%s" for %s.%s before committing', $file, trim($value), $table, $column);
                        }
                        continue;
                    }
                    if (!FakerNormalizer::isValidRewriteValue($expanded)) {
                        $errors[] = sprintf('%s: invalid rewrite value for %s.%s: %s', $file, $table, $column, $value);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<string> $files
     *
     * @return list<string>
     */
    private function validateUniquePluginNames(array $files): array
    {
        $seen = [];
        $errors = [];

        foreach ($files as $file) {
            try {
                $parsed = Yaml::parseFile($file);
            } catch (ParseException) {
                continue;
            }

            if (!\is_array($parsed)) {
                continue;
            }

            $name = $parsed['plugin']['name'] ?? null;
            if (!\is_string($name)) {
                continue;
            }

            if (isset($seen[$name])) {
                $errors[] = sprintf('Duplicate plugin.name "%s" in %s and %s', $name, $seen[$name], $file);
            } else {
                $seen[$name] = $file;
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateMergeConflicts(): array
    {
        try {
            (new PluginConfigMerger())->merge($this->loadAllPluginConfigs());
        } catch (\RuntimeException $e) {
            return [$e->getMessage()];
        }

        return [];
    }

    /**
     * @param list<string> $files
     *
     * @return list<string>
     */
    private function validateAgainstSchema(array $files): array
    {
        $schemaPath = $this->projectRoot . '/schema/plugin-config.schema.json';
        if (!is_file($schemaPath)) {
            return [];
        }

        $schema = json_decode((string) file_get_contents($schemaPath), true);
        if (!\is_array($schema)) {
            return ['Unable to read schema/plugin-config.schema.json'];
        }

        $errors = [];
        foreach ($files as $file) {
            try {
                $parsed = Yaml::parseFile($file);
            } catch (ParseException $e) {
                continue;
            }

            if (!\is_array($parsed)) {
                continue;
            }

            $fileErrors = $this->validateSchemaNode($parsed, $schema, '$');
            foreach ($fileErrors as $error) {
                $errors[] = $file . ': ' . $error;
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateDistBuildable(): array
    {
        try {
            (new DumpConfigBuilder($this->projectRoot))->build();
        } catch (\Throwable $e) {
            return ['Unable to build dist/shopware-gdpr-dump.yml: ' . $e->getMessage()];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function resolvePluginFiles(array $files): array
    {
        if ($files !== []) {
            return array_map(fn (string $f): string => str_starts_with($f, '/') ? $f : $this->projectRoot . '/' . $f, $files);
        }

        $pluginsDir = $this->projectRoot . '/plugins';
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

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadAllPluginConfigs(): array
    {
        $configs = [];
        foreach ($this->resolvePluginFiles([]) as $file) {
            $parsed = Yaml::parseFile($file);
            if (!\is_array($parsed)) {
                continue;
            }
            $name = $parsed['plugin']['name'] ?? null;
            if (\is_string($name) && $name !== '') {
                $configs[$name] = $parsed;
            }
        }

        return $configs;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return list<string>
     */
    private function validateSchemaNode(mixed $data, array $schema, string $path): array
    {
        $errors = [];
        $type = $schema['type'] ?? null;

        if ($type === 'object') {
            if (!\is_array($data)) {
                return [sprintf('%s must be an object', $path)];
            }

            $required = $schema['required'] ?? [];
            foreach ($required as $key) {
                if (!\array_key_exists($key, $data)) {
                    $errors[] = sprintf('%s.%s is required', $path, $key);
                }
            }

            $properties = $schema['properties'] ?? [];
            foreach ($data as $key => $value) {
                if (isset($properties[$key]) && \is_array($properties[$key])) {
                    $errors = [...$errors, ...$this->validateSchemaNode($value, $properties[$key], $path . '.' . $key)];
                }
            }
        }

        if ($type === 'string' && !\is_string($data)) {
            $errors[] = sprintf('%s must be a string', $path);
        }

        return $errors;
    }
}
