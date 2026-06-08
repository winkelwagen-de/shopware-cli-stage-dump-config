<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Util;

final class FakerNormalizer
{
    public static function normalize(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return $trimmed;
        }

        if (str_starts_with($trimmed, 'faker.') && !str_contains($trimmed, '{{-')) {
            return '{{- ' . $trimmed . ' -}}';
        }

        return $trimmed;
    }

    public static function isValidRewriteValue(string $value): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return true;
        }

        if (preg_match("/^'(?:[^'\\\\]|\\\\.)*'$/", $trimmed) === 1) {
            return true;
        }

        if (preg_match('/^\{\{-?\s*faker\.[A-Za-z0-9_.()]+\s*-?\}\}$/', $trimmed) === 1) {
            return true;
        }

        if (preg_match('/^faker\.[A-Za-z0-9_.()]+\(\)$/', $trimmed) === 1) {
            return true;
        }

        return false;
    }
}
