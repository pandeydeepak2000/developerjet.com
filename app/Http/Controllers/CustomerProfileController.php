<?php

namespace App\Http\Controllers;

use App\Rules\ValidPhone;
use App\Support\Audit;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Customer profile & security. Accounts created before phone numbers were required keep working and can add one
 * here; only the signed-in customer's own row is ever updated (no user id is accepted from the request).
 * Email changes are not offered here because they would need a fresh OTP verification flow.
 */
class CustomerProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('dashboard.profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone_country' => ['required', 'string', 'in:'.implode(',', array_keys(config('phone.countries')))],
            'phone' => ['required', 'string', 'max:24', new ValidPhone],
        ]);

        $user = $request->user();
        $user->forceFill(['name' => trim($data['name']), 'phone' => PhoneNumber::normalise($data['phone_country'], $data['phone'])]);
        $changed = array_keys($user->getDirty());
        $user->save();

        if ($changed) {
            Audit::log('customer.profile.updated', $user, ['fields' => $changed], $user->id); // field names only, never values
        }

        return redirect()->route('dashboard.profile')->with('success', 'Profile saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'different:current_password'],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        Auth::logoutOtherDevices($data['password']);
        $request->session()->regenerate();
        Audit::log('customer.password.changed', $user, [], $user->id);

        return redirect()->route('dashboard.profile')->with('success', 'Password changed. Other signed-in sessions were signed out.');
    }
}
