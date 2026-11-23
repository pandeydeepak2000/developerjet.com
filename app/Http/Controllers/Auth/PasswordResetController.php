<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('pages.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $email = strtolower($request->string('email')->toString());

        // Staff accounts reset through the staff portal only (own broker, stronger password rules).
        if (! User::where('email', $email)->first()?->isStaffAccount()) {
            Password::sendResetLink(['email' => $email]);
        }

        // Same response whether or not the account exists (no account enumeration).
        return back()->with('status', 'If an account exists for that email, a password reset link has been sent.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('pages.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            ['email' => strtolower($data['email']), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password) {
                abort_if($user->isStaffAccount(), 403);
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'This reset link is invalid or has expired.']);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. You can now sign in.');
    }
}
