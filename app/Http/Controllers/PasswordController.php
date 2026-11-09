<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $forcingPassword = $request->session()->get('force_password_change', false);
        $validated = $request->validate([
            'current_password' => [$forcingPassword ? 'nullable' : 'required', 'current_password'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        $user->password = $validated['new_password'];
        $user->save();
        $request->session()->forget('force_password_change');

        return to_route('profile.edit')->with('profile_status', 'Your password was updated.');
    }
}
