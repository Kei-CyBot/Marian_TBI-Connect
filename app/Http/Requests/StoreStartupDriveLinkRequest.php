<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\Startup;

class StoreStartupDriveLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        Startup::ensureConfigured();

        $this->merge([
            'label' => is_string($this->input('label')) ? trim(strip_tags($this->input('label'))) : $this->input('label'),
            'url' => is_string($this->input('url')) ? trim($this->input('url')) : $this->input('url'),
        ]);
    }

    public function rules(): array
    {
        return [
            'startup_id' => ['required', 'integer', 'exists:startups,id'],
            'label' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url:https', 'max:2048'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('startup_id') && ! Startup::query()->whereKey($this->input('startup_id'))->exists()) {
                    $validator->errors()->add('startup_id', 'Choose an active incubatee.');
                }

                $url = $this->input('url');
                $host = is_string($url) ? strtolower((string) parse_url($url, PHP_URL_HOST)) : '';

                if ($host !== '' && ! in_array($host, ['drive.google.com', 'docs.google.com'], true)) {
                    $validator->errors()->add('url', 'Enter a Google Drive or Google Docs link.');
                }
            },
        ];
    }
}
