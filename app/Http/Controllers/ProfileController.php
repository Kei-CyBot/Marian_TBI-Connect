<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $contact = $user->contact
            ?? Contact::query()->where('email', $user->email)->first();

        $affiliations = array_values(array_unique(array_merge(
            config('contact.affiliations.internal'),
            config('contact.affiliations.external'),
        )));
        $roles = array_values(array_unique(array_merge(
            config('contact.roles.internal'),
            config('contact.roles.external'),
        )));

        return view('profile', [
            'user' => $user,
            'contact' => $contact,
            'affiliations' => $affiliations,
            'roles' => $roles,
            'domainExpertiseOptions' => config('contact.domains'),
            'technicalSkillsOptions' => config('contact.technical_skills'),
            'toolsOptions' => array_values(array_unique(array_merge(...array_values(config('contact.tool_groups'))))),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $oldEmail = $user->email;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'affiliation' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'domain_expertise' => ['nullable'],
            'technical_skills' => ['nullable'],
            'tools_languages_methods' => ['nullable'],
            'affiliation_other' => ['nullable', 'string', 'max:255'],
            'role_other' => ['nullable', 'string', 'max:255'],
            'domain_expertise_other' => ['nullable', 'string', 'max:1000'],
            'technical_skills_other' => ['nullable', 'string', 'max:1000'],
            'tools_languages_methods_other' => ['nullable', 'string', 'max:255'],
        ]);

        $contact = Contact::query()->where('email', $oldEmail)->first();

        $affiliation = $this->resolveOther($validated['affiliation'] ?? null, $validated['affiliation_other'] ?? null)
            ?: $contact?->affiliation_type
            ?: 'External';
        $role = $this->resolveOther($validated['role'] ?? null, $validated['role_other'] ?? null)
            ?: $contact?->role
            ?: 'Member';
        $domains = $this->mergeOther($this->splitList($validated['domain_expertise'] ?? []), $validated['domain_expertise_other'] ?? null);
        $technicalSkills = $this->mergeOther($this->splitList($validated['technical_skills'] ?? []), $validated['technical_skills_other'] ?? null);
        $tools = $this->mergeOther($this->splitList($validated['tools_languages_methods'] ?? []), $validated['tools_languages_methods_other'] ?? null);

        $contact = Contact::updateOrCreate(
            ['email' => $oldEmail],
            [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'affiliation_type' => $affiliation,
                'organization' => $validated['organization'] ?? null,
                'role' => $role,
                'side' => $contact?->side ?? $user->startup?->track ?? 'external',
                'domains' => $domains,
                'technical_skills' => $technicalSkills,
                'skill_details' => implode(', ', $tools) ?: null,
                'incubatees' => $contact?->incubatees ?? [],
                'activity_log' => $contact?->activity_log ?? [],
            ]
        );

        $emailChanged = $user->email !== $validated['email'];
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->contact_id = $contact->id;
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        return to_route('profile.edit')->with('profile_status', 'Your profile and credentials were updated.');
    }

    /**
     * @return list<string>
     */
    private function splitList(array|string $value): array
    {
        $items = is_array($value) ? $value : explode(',', $value);

        return array_values(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            $items
        )));
    }

    private function resolveOther(?string $selected, ?string $custom): ?string
    {
        if ($selected === 'Other' && filled($custom)) {
            return trim($custom);
        }

        return $selected;
    }

    /**
     * @return list<string>
     */
    private function mergeOther(array $values, ?string $custom): array
    {
        $hadOther = in_array('Other', $values, true);
        $values = array_values(array_filter($values, static fn (string $value): bool => $value !== 'Other'));
        $custom = trim((string) $custom);

        if ($custom !== '') {
            return array_values(array_unique(array_merge($values, $this->splitList($custom))));
        }

        if ($hadOther) {
            $values[] = 'Other';
        }

        return $values;
    }
}
