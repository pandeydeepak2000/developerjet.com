<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Mail\StaffPasswordResetMail;
use App\Models\User;
use App\Support\Audit;
use App\Support\StaffPortal;
use App\Support\StaffSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

/**
 * Staff password reset, isolated from the customer flow: its own broker and token table
 * (`staff_password_reset_tokens`), links point at the current staff portal slug, and only active staff
 * accounts receive a link. Responses never reveal whether an account exists.
 */
class StaffPasswordController extends Controller
{
    public static function rule(): PasswordRule
    {
        return PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols();
    }

    public function request(): View
    {
        return view('staff.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $email = strtolower($request->validate(['email' => ['required', 'email', 'max:190']])['email']);
        $user = User::where('email', $email)->first();

        if ($user && $user->isStaff()) {
            self::sendLink($user);
        }

        return back()->with('status', 'If an active staff account exists for that email, a reset link has been sent.');
    }

    /** Also used by "Reset access" in Staff management. Returns false if the mailer failed. */
    public static function sendLink(User $user): bool
    {
        $status = Password::broker('staff')->sendResetLink(['email' => $user->email], function (User $user, string $token) {
            $url = StaffPortal::url('password.reset', ['token' => $token, 'email' => $user->email]);
            try {
                Mail::to($user->email)->queue(new StaffPasswordResetMail($user, $url, (int) config('auth.passwords.staff.expire')));
            } catch (Throwable $e) {
                Log::error('Staff password reset email failed.', ['user_id' => $user->id, 'error' => $e::class]);
            }
        });
        Audit::log('admin.password_reset_requested', $user, ['status' => $status], auth()->id());

        return $status === Password::RESET_LINK_SENT;
    }

    public function edit(Request $request, string $token): View
    {
        return view('staff.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', self::rule()],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();
        if (! $user || ! $user->isStaff()) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'This reset link is invalid or has expired.']);
        }

        $status = Password::broker('staff')->reset(
            ['email' => strtolower($data['email']), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'password_change_required' => false])->save();
                StaffSecurity::revokeSessions($user);
                Audit::log('admin.password_reset', $user, [], $user->id);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'This reset link is invalid or has expired.']);
        }

        return redirect()->to(StaffPortal::url('login'))->with('status', 'Your password has been reset. Sign in with the new password.');
    }
}
