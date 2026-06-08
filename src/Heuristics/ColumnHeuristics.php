<?php

declare(strict_types=1);

namespace ShopwareGdprDump\Heuristics;

final class ColumnHeuristics
{
    /** @var list<string> */
    private const SKIP_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'version_id',
    ];

    /**
     * @return array{value: string, uncertain: bool, structural: bool}
     */
    public function classifyForDraft(string $column): array
    {
        if ($this->shouldSkip($column)) {
            return ['value' => 'skip', 'uncertain' => false, 'structural' => true];
        }

        $suggestion = $this->suggest($column);
        if ($suggestion !== null) {
            return [
                'value' => $suggestion['shorthand'],
                'uncertain' => $suggestion['uncertain'],
                'structural' => false,
            ];
        }

        return ['value' => 'review', 'uncertain' => true, 'structural' => false];
    }

    /**
     * @return array{shorthand: string, uncertain: bool}|null
     */
    public function suggest(string $column): ?array
    {
        $column = strtolower($column);

        if ($this->shouldSkip($column)) {
            return null;
        }

        if ($column === 'email' || $column === 'admin_mail' || str_ends_with($column, '_email')) {
            return ['shorthand' => 'email', 'uncertain' => false];
        }

        if ($column === 'first_name' || str_ends_with($column, '_first_name')) {
            return ['shorthand' => 'first_name', 'uncertain' => false];
        }

        if ($column === 'last_name' || str_ends_with($column, '_last_name')) {
            return ['shorthand' => 'last_name', 'uncertain' => false];
        }

        if ($column === 'phone_number' || str_contains($column, 'phone')) {
            return ['shorthand' => 'phone_number', 'uncertain' => str_contains($column, 'phone') && $column !== 'phone_number'];
        }

        if ($column === 'street' || str_ends_with($column, '_street')) {
            return ['shorthand' => 'street', 'uncertain' => false];
        }

        if ($column === 'zipcode' || str_ends_with($column, '_zipcode') || $column === 'zip_code') {
            return ['shorthand' => 'zipcode', 'uncertain' => false];
        }

        if ($column === 'city' || str_ends_with($column, '_city')) {
            return ['shorthand' => 'city', 'uncertain' => false];
        }

        if ($column === 'company' || str_ends_with($column, '_company')) {
            return ['shorthand' => 'company', 'uncertain' => true];
        }

        if ($column === 'remote_address' || str_ends_with($column, '_ip') || $column === 'ip_address') {
            return ['shorthand' => 'remote_address', 'uncertain' => $column !== 'remote_address'];
        }

        if (preg_match('/(?:password|secret|token|api_key|credential)/', $column) === 1) {
            return ['shorthand' => "'***'", 'uncertain' => false];
        }

        if (preg_match('/(?:note|comment|message|description|payload|data|json|content)/', $column) === 1) {
            return ['shorthand' => "'redacted'", 'uncertain' => true];
        }

        return null;
    }

    private function shouldSkip(string $column): bool
    {
        if (\in_array($column, self::SKIP_COLUMNS, true)) {
            return true;
        }

        if (str_ends_with($column, '_id') || str_ends_with($column, '_version_id')) {
            return true;
        }

        return false;
    }
}
