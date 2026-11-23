<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\ValidPhone;
use App\Services\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('pages.register');
    }

    public function store(Request $request, OtpService $otp): RedirectResponse
    {
        // Normalise first so uniqueness is case-insensitive on every database collation.
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            'phone_country' => ['required', 'string', 'in:'.implode(',', array_keys(config('phone.countries')))],
            'phone' => ['required', 'string', 'max:24', new ValidPhone],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = User::create([
            'name' => trim($data['name']),
            'email' => strtolower($data['email']),
            'phone' => PhoneNumber::normalise($data['phone_country'], $data['phone']),
            'password' => $data['password'],
        ]);

        Auth::login($user); // fires Login event -> guest cart merge
        $request->session()->regenerate();

        $status = $otp->issueWithStatus($user, $request->ip());

        return redirect()->route('verification.notice')->with(
            $status === OtpService::SENT ? 'status' : 'error',
            match ($status) {
                OtpService::SENT => 'We emailed you a 6-digit verification code.',
                OtpService::LOGGED_ONLY => 'Your account was created, but email delivery is not configured (the current mailer only writes messages to a log), so the code was not delivered to your inbox. A Super Admin must configure SMTP in Settings - Email (SMTP), then use "Resend code".',
                default => 'Your account was created, but we could not send the code. Please use "Resend code".',
            },
        );
    }
}
