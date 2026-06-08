<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Scan;

final class PluginMetadataResolver
{
    public function resolveComposerName(string $pluginPath): string
    {
        $fromComposer = $this->fromComposerJson($pluginPath);
        if ($fromComposer !== null) {
            return $fromComposer;
        }

        return $this->fromDirectoryName($pluginPath);
    }

    public function resolveLabel(string $pluginPath, string $composerName): string
    {
        $fromComposer = $this->labelFromComposerJson($pluginPath);
        if ($fromComposer !== null) {
            return $fromComposer;
        }

        $parts = explode('/', $composerName);
        $name = end($parts) ?: $composerName;

        return ucwords(str_replace(['-', '_'], ' ', $name));
    }

    private function fromComposerJson(string $pluginPath): ?string
    {
        $composerFile = rtrim($pluginPath, '/') . '/composer.json';
        if (!is_file($composerFile)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($composerFile), true);
        if (!\is_array($data)) {
            return null;
        }

        $name = $data['name'] ?? null;

        return \is_string($name) && $name !== '' ? $name : null;
    }

    private function labelFromComposerJson(string $pluginPath): ?string
    {
        $composerFile = rtrim($pluginPath, '/') . '/composer.json';
        if (!is_file($composerFile)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($composerFile), true);
        if (!\is_array($data)) {
            return null;
        }

        foreach (['description', 'extra.label'] as $key) {
            if ($key === 'extra.label') {
                $label = $data['extra']['label'] ?? null;
            } else {
                $label = $data[$key] ?? null;
            }

            if (\is_string($label) && $label !== '') {
                return $label;
            }
        }

        return null;
    }

    private function fromDirectoryName(string $pluginPath): string
    {
        $base = basename(rtrim($pluginPath, '/'));
        $kebab = strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $base));
        $kebab = strtolower(str_replace('_', '-', $kebab));

        return 'local/' . $kebab;
    }
}
