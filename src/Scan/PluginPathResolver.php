<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Scan;

final class PluginPathResolver
{
    public const SOURCE_PLUGINS = 'plugins';
    public const SOURCE_STATIC_PLUGINS = 'static-plugins';

    /** @var list<string> */
    private const SHOPWARE_PLUGIN_DIRS = [
        self::SOURCE_PLUGINS => 'custom/plugins',
        self::SOURCE_STATIC_PLUGINS => 'custom/static-plugins',
    ];

    public function isPluginDirectory(string $path): bool
    {
        return $this->hasMigrations($path);
    }

    public function hasMigrations(string $path): bool
    {
        $migrationDir = rtrim($path, '/') . '/src/Migration';
        if (!is_dir($migrationDir)) {
            return false;
        }

        return (glob($migrationDir . '/Migration*.php') ?: []) !== [];
    }

    public function isShopwareRoot(string $path): bool
    {
        $path = rtrim($path, '/');

        foreach (self::SHOPWARE_PLUGIN_DIRS as $relativeDir) {
            if (is_dir($path . '/' . $relativeDir)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string> absolute plugin directory paths
     */
    public function resolvePluginDirectories(string $path): array
    {
        return array_map(
            static fn (array $entry): string => $entry['path'],
            $this->resolvePluginEntries($path)
        );
    }

    /**
     * @return list<array{path: string, source: string}>
     */
    public function resolvePluginEntries(string $path): array
    {
        $path = rtrim($path, '/');

        if ($this->isShopwareRoot($path)) {
            return $this->collectPluginsFromShopwareRoot($path);
        }

        if ($this->hasMigrations($path)) {
            return [['path' => realpath($path) ?: $path, 'source' => self::SOURCE_PLUGINS]];
        }

        throw new \RuntimeException(sprintf(
            'Expected a Shopware project root (directory containing custom/plugins): %s',
            $path
        ));
    }

    /**
     * @return list<array{path: string, source: string}>
     */
    private function collectPluginsFromShopwareRoot(string $path): array
    {
        $plugins = [];

        foreach (self::SHOPWARE_PLUGIN_DIRS as $source => $relativeDir) {
            $baseDir = $path . '/' . $relativeDir;
            if (!is_dir($baseDir)) {
                continue;
            }

            foreach (scandir($baseDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $pluginPath = $baseDir . '/' . $entry;
                if (!is_dir($pluginPath)) {
                    continue;
                }

                if ($this->hasMigrations($pluginPath)) {
                    $plugins[] = [
                        'path' => realpath($pluginPath) ?: $pluginPath,
                        'source' => $source,
                    ];
                }
            }
        }

        usort($plugins, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);

        return $plugins;
    }

    public function defaultOutputPath(string $composerName, string $outputDir, string $source = self::SOURCE_PLUGINS): string
    {
        $parts = explode('/', $composerName, 2);
        if (\count($parts) === 2) {
            $vendor = $parts[0];
            if ($source === self::SOURCE_STATIC_PLUGINS) {
                $vendor = '_' . $vendor;
            }

            return rtrim($outputDir, '/') . '/' . $vendor . '/' . $parts[1] . '.yaml';
        }

        $safe = preg_replace('/[^a-z0-9._-]+/i', '-', $composerName) ?: 'unknown';
        if ($source === self::SOURCE_STATIC_PLUGINS) {
            $safe = '_' . $safe;
        }

        return rtrim($outputDir, '/') . '/' . $safe . '.yaml';
    }
}
