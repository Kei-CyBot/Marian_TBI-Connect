<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStartupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'venture_type' => $this->input('venture_type') === '__other__'
                ? $this->input('venture_type_other')
                : $this->input('venture_type'),
            'legal_entity_type' => $this->input('legal_entity_type') === '__other__'
                ? $this->input('legal_entity_type_other')
                : $this->input('legal_entity_type'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'founder' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'track' => ['required', Rule::in(['internal', 'external'])],
            'status' => ['required', Rule::in(['Applicant', 'Incubatee', 'Incubatee Extended', 'Graduate', 'Alumni Network'])],
            'cohort_id' => ['required', 'integer', 'between:1,9999'],
            'venture_type' => ['nullable', 'string', 'max:255'],
            'campus_department' => ['nullable', 'string', 'max:255'],
            'legal_entity_type' => ['nullable', 'string', 'max:255'],
        ];
    }
}
