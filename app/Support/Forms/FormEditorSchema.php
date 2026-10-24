<?php

namespace App\Support\Forms;

use App\Helpers\FormFormatHelper;

/**
 * Resolves the rich editor field list for a saved submission: schema-driven
 * controls (select / radio / checkbox / date / textarea …) matching the React
 * form catalog, plus a plain-text fallback for any scalar key the schema does
 * not know, so every saved value stays editable.
 */
final class FormEditorSchema
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, label: string, type: string, options: list<array{value: string, label: string}>, value: mixed, editable: bool}>
     */
    public static function forSubmission(int $formId, array $data): array
    {
        $schema = config('form_editor.forms')[$formId] ?? [];
        $fields = [];
        $claimed = [];

        foreach ($schema as $key => $definition) {
            $key = (string) $key;
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $claimed[$key] = true;
            $type = $definition['type'] ?? 'text';
            $value = $data[$key];

            if ($type === 'readonly') {
                $fields[] = [
                    'key' => $key,
                    'label' => (string) ($definition['label'] ?? FormFormatHelper::toLabel($key)),
                    'type' => 'text',
                    'options' => [],
                    'value' => is_scalar($value) || $value === null ? (string) $value : '',
                    'editable' => false,
                ];

                continue;
            }

            if ($type === 'checkbox') {
                $fields[] = [
                    'key' => $key,
                    'label' => (string) ($definition['label'] ?? FormFormatHelper::toLabel($key)),
                    'type' => 'checkbox',
                    'options' => [],
                    'value' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
                    'editable' => true,
                ];

                continue;
            }

            $scalar = is_scalar($value) || $value === null;
            if (! $scalar) {
                // Structured value saved for a schema field: keep it preserved instead.
                continue;
            }

            $stringValue = (string) $value;

            if ($type === 'select' || $type === 'radio') {
                $options = self::normalizeOptions($definition['options'] ?? []);
                $optionValues = array_column($options, 'value');
                if ($stringValue !== '' && ! in_array($stringValue, $optionValues, true)) {
                    array_unshift($options, ['value' => $stringValue, 'label' => $stringValue]);
                }

                $fields[] = [
                    'key' => $key,
                    'label' => (string) ($definition['label'] ?? FormFormatHelper::toLabel($key)),
                    'type' => $type,
                    'options' => $options,
                    'value' => $stringValue,
                    'editable' => true,
                ];

                continue;
            }

            $fields[] = [
                'key' => $key,
                'label' => (string) ($definition['label'] ?? FormFormatHelper::toLabel($key)),
                'type' => in_array($type, ['text', 'email', 'date', 'textarea'], true) ? $type : 'text',
                'options' => [],
                'value' => $stringValue,
                'editable' => true,
            ];
        }

        foreach ($data as $key => $value) {
            $key = (string) $key;
            if (isset($claimed[$key]) || $key === 'signer_name') {
                continue;
            }
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $fields[] = [
                'key' => $key,
                'label' => FormFormatHelper::toLabel($key),
                'type' => 'text',
                'options' => [],
                'value' => $value === null ? '' : (string) $value,
                'editable' => true,
            ];
        }

        return $fields;
    }

    /**
     * Keys whose values the editor submits (everything else is preserved verbatim
     * through data_complex, including readonly and structured keys).
     *
     * @param  list<array{key: string, editable: bool}>  $fields
     * @return list<string>
     */
    public static function editableKeys(array $fields): array
    {
        return array_values(array_map(
            static fn (array $field): string => $field['key'],
            array_filter($fields, static fn (array $field): bool => (bool) ($field['editable'] ?? false))
        ));
    }

    /**
     * Accepts both list-style options (value === label) and maps (value => label).
     *
     * @return list<array{value: string, label: string}>
     */
    private static function normalizeOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        if (array_is_list($options)) {
            return array_map(
                static fn (mixed $option): array => [
                    'value' => (string) $option,
                    'label' => (string) $option,
                ],
                $options
            );
        }

        $normalized = [];
        foreach ($options as $value => $label) {
            $normalized[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return $normalized;
    }
}
