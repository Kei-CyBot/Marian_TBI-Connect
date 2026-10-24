<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class FormFormatHelper
{
    /**
     * Convert a snake_case, camelCase, or dotted key into a readable Title Case label.
     */
    public static function toLabel(string $key): string
    {
        $parts = preg_split('/(?<=[a-z0-9])(?=[A-Z])|[_.]+/', $key) ?: [];

        $label = collect($parts)
            ->filter()
            ->map(static fn (string $part): string => Str::ucfirst($part))
            ->implode(' ');

        return $label !== '' ? $label : $key;
    }

    /**
     * Convert a raw value into a printable string (arrays become readable lists).
     */
    public static function formatScalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return collect($value)
                    ->map(static fn (mixed $item): string => self::formatScalar($item))
                    ->filter()
                    ->implode(', ');
            }

            return collect($value)
                ->map(static fn (mixed $item, int|string $itemKey): string
                    => self::toLabel((string) $itemKey).': '.self::formatScalar($item))
                ->implode(', ');
        }

        return (string) $value;
    }

    /**
     * Transform submission data into display blocks for the UI:
     * - scalars become "field" blocks,
     * - key/value objects become two-column "grid" blocks (Metric / Score style),
     * - lists of objects become "table" blocks (e.g. evaluators),
     * - lists of scalars become "badges" blocks.
     *
     * @return list<array<string, mixed>>
     */
    public static function formatData(array $data): array
    {
        $blocks = [];

        foreach ($data as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $keyString = (string) $key;

            if (is_array($value) && array_is_list($value)) {
                $blocks[] = self::formatListBlock($keyString, $value);

                continue;
            }

            if (is_array($value)) {
                $blocks[] = [
                    'type' => 'grid',
                    'key' => $keyString,
                    'label' => self::toLabel($keyString),
                    'items' => collect($value)
                        ->map(static fn (mixed $item, int|string $itemKey): array => [
                            'key' => (string) $itemKey,
                            'label' => self::toLabel((string) $itemKey),
                            'value' => self::formatScalar($item),
                        ])
                        ->values()
                        ->all(),
                ];

                continue;
            }

            $blocks[] = [
                'type' => 'field',
                'key' => $keyString,
                'label' => self::toLabel($keyString),
                'value' => self::formatScalar($value),
            ];
        }

        return $blocks;
    }

    /**
     * Top-level scalar fields that can be edited as plain text inputs.
     *
     * @return list<array{key:string,label:string,value:string}>
     */
    public static function editableFields(array $data): array
    {
        return collect($data)
            ->filter(static fn (mixed $value): bool => is_scalar($value) || $value === null)
            ->map(static fn (mixed $value, int|string $key): array => [
                'key' => (string) $key,
                'label' => self::toLabel((string) $key),
                'value' => self::formatScalar($value),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<mixed>  $value
     * @return array<string, mixed>
     */
    private static function formatListBlock(string $key, array $value): array
    {
        $label = self::toLabel($key);

        $isTable = collect($value)->every(static fn (mixed $row): bool
            => is_array($row) && $row !== [] && ! array_is_list($row));

        if ($isTable) {
            $columns = [];
            foreach ($value as $row) {
                foreach (array_keys($row) as $column) {
                    $columnKey = (string) $column;
                    $columns[$columnKey] ??= self::toLabel($columnKey);
                }
            }

            return [
                'type' => 'table',
                'key' => $key,
                'label' => $label,
                'columns' => $columns,
                'rows' => array_map(static fn (array $row): array => collect($columns)
                    ->keys()
                    ->mapWithKeys(static fn (string $column): array => [
                        $column => self::formatScalar($row[$column] ?? null),
                    ])
                    ->all(), array_values($value)),
            ];
        }

        return [
            'type' => 'badges',
            'key' => $key,
            'label' => $label,
            'items' => array_values(array_map(
                static fn (mixed $item): string => self::formatScalar($item),
                $value
            )),
        ];
    }
}
