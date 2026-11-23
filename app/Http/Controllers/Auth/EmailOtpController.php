<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmailOtpController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        return view('pages.verify-email', ['email' => $request->user()->email, 'wait' => $this->otp->waitSeconds($request->user())]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'Enter the 6-digit code.']);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        $result = $this->otp->verify($user, $request->string('code')->toString());

        if ($result !== OtpService::OK) {
            throw ValidationException::withMessages(['code' => match ($result) {
                OtpService::EXPIRED => 'This code has expired. Request a new one.',
                OtpService::LOCKED => 'Too many incorrect attempts. Request a new code.',
                OtpService::MISSING => 'No active code. Request a new one.',
                default => 'That code is not correct.',
            }]);
        }

        return redirect()->intended(route('dashboard'))->with('status', 'Email verified. Welcome to DeveloperJet!');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        if ($wait = $this->otp->waitSeconds($user)) {
            return back()->with('error', $wait >= 3600
                ? 'Too many codes requested. Please try again later.'
                : "Please wait {$wait} seconds before requesting another code.");
        }

        $status = $this->otp->issueWithStatus($user, $request->ip());

        return match ($status) {
            OtpService::SENT => back()->with('status', 'A new code has been sent by email.'),
            OtpService::LOGGED_ONLY => back()->with('error', 'Email delivery is not configured (the current mailer only writes messages to a log), so the code was not delivered to your inbox. A Super Admin must configure SMTP in Settings - Email (SMTP), then request a new code.'),
            default => back()->with('error', 'We could not send the email. Please try again shortly.'),
        };
    }
}
