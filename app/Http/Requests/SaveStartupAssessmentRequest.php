<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStartupAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->input('data');
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : null;
        }

        if (is_array($data) && is_array($data['otherCriticalSkills'] ?? null)) {
            $data['otherCriticalSkills'] = array_map(
                static fn ($value): ?string => is_string($value) ? trim(strip_tags($value)) : null,
                $data['otherCriticalSkills']
            );
        }

        $this->merge(['data' => $data]);
    }

    public function rules(): array
    {
        $keys = collect(config('startup_assessment', []))
            ->flatMap(static fn (array $area): array => array_column($area['competencies'], 'key'))
            ->all();

        $ratingRules = [];
        foreach ($keys as $key) {
            $ratingRules['data.competencyRatings.'.$key] = ['required', 'integer', 'between:1,5'];
        }

        return [
            'startup_id' => ['required', 'string', 'max:64', Rule::exists('startups', 'id')],
            'startup_name' => ['required', 'string', 'max:255'],
            'data' => ['required', 'array:competencyRatings,otherCriticalSkills'],
            'data.competencyRatings' => ['required', 'array:'.implode(',', $keys)],
            ...$ratingRules,
            'data.otherCriticalSkills' => ['required', 'array', 'size:5'],
            'data.otherCriticalSkills.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
