<?php

namespace App\Services;

use App\Models\FormSubmission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class DocumentConversionService
{
    public function toPdf(FormSubmission|array $source, array $metadata = []): string
    {
        return Pdf::loadHTML($this->toHtml($source, $metadata))
            ->setPaper('a4')
            ->output();
    }

    public function toHtml(FormSubmission|array $source, array $metadata = []): string
    {
        [$data, $meta] = $this->normalize($source, $metadata);

        return view('pdf.document-form', [
            'title' => $meta['title'],
            'referenceId' => $meta['reference_id'],
            'createdAt' => $meta['created_at'],
            'author' => $meta['author'],
            'startup' => $meta['startup'],
            'track' => $meta['track'],
            'rows' => $this->rows($data),
            'notes' => $meta['notes'],
        ])->render();
    }

    private function normalize(FormSubmission|array $source, array $metadata): array
    {
        if ($source instanceof FormSubmission) {
            return [
                is_array($source->data) ? $source->data : [],
                [
                    'title' => $metadata['title'] ?? $source->form_name,
                    'reference_id' => $metadata['reference_id'] ?? sprintf('FORM-%06d', $source->getKey()),
                    'created_at' => $metadata['created_at'] ?? $source->created_at ?? now(),
                    'author' => $metadata['author'] ?? $source->user?->name ?? 'System submission',
                    'startup' => $metadata['startup'] ?? $source->startup_name,
                    'track' => $metadata['track'] ?? Str::headline($source->track),
                    'notes' => $metadata['notes'] ?? ($source->prerequisite_original_name
                        ? 'Attachment: '.$source->prerequisite_original_name
                        : null),
                ],
            ];
        }

        $data = isset($source['data']) && is_array($source['data']) ? $source['data'] : $source;

        return [
            $data,
            [
                'title' => $metadata['title'] ?? $source['title'] ?? 'Institutional Form Submission',
                'reference_id' => $metadata['reference_id'] ?? $source['reference_id'] ?? 'FORM-'.strtoupper(Str::random(8)),
                'created_at' => $metadata['created_at'] ?? $source['created_at'] ?? now(),
                'author' => $metadata['author'] ?? $source['author'] ?? 'System submission',
                'startup' => $metadata['startup'] ?? $source['startup'] ?? $source['startup_name'] ?? '—',
                'track' => $metadata['track'] ?? $source['track'] ?? '—',
                'notes' => $metadata['notes'] ?? $source['notes'] ?? null,
            ],
        ];
    }

    private function rows(array $data, string $prefix = ''): array
    {
        $rows = [];

        foreach ($data as $key => $value) {
            $label = Str::headline(is_string($key) ? $key : (string) $key);
            $label = $prefix === '' ? $label : $prefix.' · '.$label;

            if (is_array($value)) {
                if ($value === []) {
                    $rows[] = ['label' => $label, 'value' => '—'];

                    continue;
                }

                if (array_is_list($value) && collect($value)->every(fn ($item): bool => ! is_array($item))) {
                    $rows[] = [
                        'label' => $label,
                        'value' => collect($value)
                            ->map(fn ($item): string => is_bool($item) ? ($item ? 'Yes' : 'No') : (string) $item)
                            ->implode(', '),
                    ];

                    continue;
                }

                foreach ($value as $index => $item) {
                    $childLabel = array_is_list($value) ? $label.' #'.($index + 1) : $label;

                    if (is_array($item)) {
                        $rows = [...$rows, ...$this->rows($item, $childLabel)];
                    } else {
                        $rows[] = [
                            'label' => $childLabel,
                            'value' => is_bool($item) ? ($item ? 'Yes' : 'No') : (string) $item,
                        ];
                    }
                }

                continue;
            }

            $rows[] = [
                'label' => $label,
                'value' => $value === null || $value === ''
                    ? '—'
                    : (is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value),
            ];
        }

        return $rows;
    }
}
