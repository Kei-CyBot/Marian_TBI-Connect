<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => ['required', 'current_password'],
            'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $emailChanged = $user->email !== $validated['email'];
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        if (filled($validated['new_password'] ?? null)) {
            $user->password = $validated['new_password'];
        }
        $user->save();

        return to_route('profile.edit')->with('profile_status', 'Your profile and credentials were updated.');
    }
}
