<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Migration;

final class EntityNameResolver
{
    /** @var array<string, string|null> */
    private array $cache = [];

    public function resolve(string $fqcn, string $pluginPath): ?string
    {
        if (\array_key_exists($fqcn, $this->cache)) {
            return $this->cache[$fqcn];
        }

        $shortName = str_contains($fqcn, '\\') ? substr($fqcn, strrpos($fqcn, '\\') + 1) : $fqcn;
        $srcDir = rtrim($pluginPath, '/') . '/src';

        if (!is_dir($srcDir)) {
            return $this->cache[$fqcn] = null;
        }

        foreach ($this->findFilesNamed($srcDir, $shortName . '.php') as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            if (preg_match("/const\s+ENTITY_NAME\s*=\s*'([^']+)'/", $contents, $match) === 1) {
                return $this->cache[$fqcn] = $match[1];
            }
        }

        return $this->cache[$fqcn] = null;
    }

    /**
     * @return list<string>
     */
    private function findFilesNamed(string $directory, string $fileName): array
    {
        $matches = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === $fileName) {
                $matches[] = $file->getPathname();
            }
        }

        return $matches;
    }
}
