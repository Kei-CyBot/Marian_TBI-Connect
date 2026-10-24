<?php

namespace App\Http\Requests\Forms;

use App\Models\FormSubmission;
use App\Models\Startup;
use App\Support\Forms\StartupFormCatalog;
use Closure;
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
            'startup' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $startup = Startup::withTrashed()->where('name', $value)->first();

                    if ($startup === null) {
                        $fail('The selected startup does not exist.');

                        return;
                    }

                    $user = $this->user();

                    if ($user === null || ! $startup->userCanAccessFolder($user)) {
                        $fail('You do not have access to this startup folder.');
                    }
                },
            ],
            'track' => ['required', Rule::in(['internal', 'external'])],
            'data' => ['required', 'array'],
            'signer_name' => ['required', 'string', 'max:255'],
        ];

        if ($form?->prerequisite !== null) {
            $extensions = implode(',', $form->prerequisite->allowedExtensions);
            $prerequisiteRules = [
                'file',
                'mimes:'.$extensions,
                'max:'.$form->prerequisite->maxKilobytes,
            ];

            // When editing an existing submission the attachment may be kept as-is.
            $rules['prerequisite'] = $this->route('submission') instanceof FormSubmission
                ? ['nullable', ...$prerequisiteRules]
                : ['required', ...$prerequisiteRules];
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
