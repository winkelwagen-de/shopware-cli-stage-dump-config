<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Migration;

final class MigrationPlaceholderResolver
{
    public function __construct(
        private readonly EntityNameResolver $entityNameResolver = new EntityNameResolver(),
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function resolveFromSource(string $source, string $pluginPath): array
    {
        $useMap = $this->parseUseStatements($source);
        $replacements = [];

        if (preg_match_all(
            '/str_replace\s*\(\s*(?:\\\\)?\[([^\]]+)\]\s*,\s*\[([^\]]+)\]/s',
            $source,
            $matches,
            PREG_SET_ORDER
        ) !== false) {
            foreach ($matches as $match) {
                $search = $this->parseArrayElements($match[1]);
                $replace = $this->parseArrayElements($match[2]);

                foreach ($search as $index => $placeholder) {
                    if (!str_starts_with($placeholder, '#') || !str_ends_with($placeholder, '#')) {
                        continue;
                    }

                    $value = $replace[$index] ?? null;
                    if ($value === null) {
                        continue;
                    }

                    $resolved = $this->resolveReplacementValue($value, $useMap, $pluginPath);
                    if ($resolved !== null) {
                        $replacements[$placeholder] = $resolved;
                    }
                }
            }
        }

        return $replacements;
    }

    public function apply(string $sql, array $replacements): string
    {
        if ($replacements === []) {
            return $sql;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $sql);
    }

    /**
     * @return array<string, string>
     */
    private function parseUseStatements(string $source): array
    {
        $map = [];

        if (preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $source, $matches, PREG_SET_ORDER) === false) {
            return $map;
        }

        foreach ($matches as $match) {
            $fqcn = $match[1];
            $alias = $match[2] ?? substr($fqcn, strrpos($fqcn, '\\') + 1);
            $map[$alias] = $fqcn;
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function parseArrayElements(string $arrayBody): array
    {
        $elements = [];

        if (preg_match_all("/'((?:\\\\'|[^'])*)'|(\\w+::\\w+)/", $arrayBody, $matches, PREG_SET_ORDER) === false) {
            return $elements;
        }

        foreach ($matches as $match) {
            if ($match[1] !== '') {
                $elements[] = stripcslashes($match[1]);
                continue;
            }

            $elements[] = $match[2];
        }

        return $elements;
    }

    /**
     * @param array<string, string> $useMap
     */
    private function resolveReplacementValue(string $value, array $useMap, string $pluginPath): ?string
    {
        if (preg_match("/^'((?:\\\\'|[^'])*)'$/", $value, $match) === 1) {
            return stripcslashes($match[1]);
        }

        if (preg_match('/^(\w+)::ENTITY_NAME$/', $value, $match) === 1) {
            $fqcn = $useMap[$match[1]] ?? null;
            if ($fqcn === null) {
                return null;
            }

            return $this->entityNameResolver->resolve($fqcn, $pluginPath);
        }

        return null;
    }
}
