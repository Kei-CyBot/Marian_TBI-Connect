<?php

namespace App\Http\Requests\Forms;

use App\Support\Forms\StartupFormCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitFormRequest extends FormRequest
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

        if (! is_array($data)) {
            $this->merge(['data' => $data]);

            return;
        }

        array_walk_recursive($data, static function (&$value): void {
            if (is_string($value)) {
                $value = trim(strip_tags($value));
            }
        });

        $this->merge(['data' => $data]);
    }

    public function rules(StartupFormCatalog $catalog): array
    {
        $formId = filter_var($this->input('form_id'), FILTER_VALIDATE_INT);
        $form = $formId === false ? null : $catalog->find($formId);
        $rules = [
            'form_id' => ['required', 'integer', Rule::in(array_map(
                static fn ($definition): int => $definition->id,
                $catalog->all()
            ))],
            'startup' => ['required', 'string', 'max:255'],
            'track' => ['required', Rule::in(['internal', 'external'])],
            'data' => ['required', 'array'],
            'signer_name' => ['required', 'string', 'max:255'],
        ];

        if ($form?->prerequisite !== null) {
            $extensions = implode(',', $form->prerequisite->allowedExtensions);
            $rules['prerequisite'] = [
                'required',
                'file',
                'mimes:'.$extensions,
                'max:'.$form->prerequisite->maxKilobytes,
            ];
        } else {
            $rules['prerequisite'] = ['prohibited'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'prerequisite.required' => 'Attach the required document before submitting this form.',
            'prerequisite.mimes' => 'The required document must be a PDF file.',
            'prerequisite.max' => 'The required PDF document must not exceed 10 MB.',
        ];
    }
}
