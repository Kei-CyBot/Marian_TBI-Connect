<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $domains = (array) $this->input('domains', []);
        $technicalSkills = (array) $this->input('technical_skills', []);

        $this->merge([
            'role' => $this->input('role') === 'Other' ? $this->input('role_other') : $this->input('role'),
            'affiliation_type' => $this->input('affiliation_type') === 'Other'
                ? $this->input('affiliation_type_other')
                : $this->input('affiliation_type'),
            'domains_other_selected' => in_array('Other', $domains, true),
            'technical_other_selected' => in_array('Other', $technicalSkills, true),
            'domains' => array_values(array_diff($domains, ['Other'])),
            'technical_skills' => array_values(array_diff($technicalSkills, ['Other'])),
        ]);
    }

    public function rules(): array
    {
        return [
            'side' => ['required', Rule::in(['internal', 'external'])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'affiliation_type' => ['required', 'string', 'max:160'],
            'role' => ['required', 'string', 'max:160'],
            'organization' => ['nullable', 'required_unless:affiliation_type,Freelancer / Self-employed', 'string', 'max:160'],
            'academic_program' => ['nullable', 'required_if:role,Student Lead', 'string', 'max:120'],
            'academic_year' => ['nullable', 'required_if:role,Student Lead', Rule::in(['1st year', '2nd year', '3rd year', '4th year', '5th year or higher', 'Graduate student'])],
            'domains' => ['nullable', 'array', 'max:20'],
            'domains.*' => ['required', 'string', 'max:120'],
            'domains_other_selected' => ['boolean'],
            'domain_other' => ['nullable', 'string', 'max:120'],
            'technical_skills' => ['nullable', 'array', 'max:20'],
            'technical_skills.*' => ['required', 'string', 'max:160'],
            'technical_other_selected' => ['boolean'],
            'technical_other' => ['nullable', 'string', 'max:160'],
            'skill_details' => ['nullable', 'string', 'max:200'],
            'nda_status' => ['required', Rule::in(['Pending', 'NDA Signed', 'MOU Signed', 'Both Signed'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! count((array) $this->input('domains', [])) && ! $this->boolean('domains_other_selected')) {
                    $validator->errors()->add('domains', 'Select at least one domain expertise area.');
                }

                if ($this->boolean('domains_other_selected') && ! filled($this->input('domain_other'))) {
                    $validator->errors()->add('domain_other', 'Specify your other domain expertise.');
                }

                if ($this->boolean('technical_other_selected') && ! filled($this->input('technical_other'))) {
                    $validator->errors()->add('technical_other', 'Specify your other technical skill.');
                }
            },
        ];
    }
}
