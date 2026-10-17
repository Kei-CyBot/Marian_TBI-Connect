<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTechnicalNeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'startup_id' => ['required', 'integer', Rule::exists('startups', 'id')->whereNull('deleted_at')],
            'requirement' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subscription_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'months_needed' => ['nullable', 'integer', 'between:1,65535'],
            'responsible_team_member' => ['nullable', 'string', 'max:255'],
            'date_entry' => ['required', 'date'],
            'date_needed' => ['nullable', 'date', 'after_or_equal:date_entry'],
            'resource_tool' => ['nullable', 'string', 'max:255'],
            'prospect_resource_person' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['Ongoing', 'Done'])],
            'priority' => ['required', Rule::in(['Low', 'Moderate', 'High'])],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'signed_approval' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}
