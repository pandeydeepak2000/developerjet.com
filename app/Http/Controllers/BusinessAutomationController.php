<?php

namespace App\Http\Controllers;

use App\Models\AutomationRequest;
use App\Models\Plan;
use App\Rules\ValidPhone;
use App\Services\AutomationLeadService;
use App\Support\SafeText;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Public Business Automation page and requirement form. Guests can submit (no purchase or account needed); a signed-in
 * customer's request is attached to their account. Protection: CSRF (web middleware), route + per-email rate limits,
 * a honeypot field, a signed minimum-fill-time token, sanitised input, secret redaction and duplicate suppression.
 */
class BusinessAutomationController extends Controller
{
    public const CATEGORY = 'business-automation';

    private const MIN_FILL_SECONDS = 3;

    private const MAX_FORM_AGE_SECONDS = 86400;

    public function show(Request $request): View
    {
        $plans = Plan::with(['prices', 'resourceProfile'])->active()->where('visibility', 'public')
            ->whereHas('category', fn ($q) => $q->where('slug', self::CATEGORY)->where('is_active', true))
            ->orderBy('sort_order')->get();

        $user = $request->user();

        return view('pages.business-automation', [
            'plans' => $plans,
            'formToken' => Crypt::encryptString(json_encode(['t' => time(), 'id' => (string) Str::uuid()])),
            'prefill' => $user ? ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone] : [],
            'selectedPlan' => $request->query('plan'),
        ]);
    }

    public function store(Request $request, AutomationLeadService $leads): RedirectResponse
    {
        // Honeypot: real visitors never see or fill this field.
        if (filled($request->input('fax_number'))) {
            throw ValidationException::withMessages(['form' => 'We could not accept this submission. Please try again.']);
        }

        $token = $this->formToken((string) $request->input('form_token'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'phone_country' => ['required', 'string', 'in:'.implode(',', array_keys(config('phone.countries')))],
            'phone' => ['required', 'string', 'max:24', new ValidPhone],
            'business_type' => ['required', 'string', 'max:120'],
            'requirements' => ['required', 'string', 'min:20', 'max:5000'],
            'company' => ['nullable', 'string', 'max:150'],
            'website' => ['nullable', 'string', 'max:200', 'url:http,https'],
            'business_size' => ['nullable', 'in:'.implode(',', array_keys(AutomationRequest::BUSINESS_SIZES))],
            'current_tools' => ['nullable', 'string', 'max:1500'],
            'integrations' => ['nullable', 'string', 'max:1500'],
            'timeline' => ['nullable', 'in:'.implode(',', array_keys(AutomationRequest::TIMELINES))],
            'budget' => ['nullable', 'in:'.implode(',', array_keys(AutomationRequest::BUDGETS))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'plan' => ['nullable', 'string', 'max:120'],
            'consent' => ['accepted'],
        ], [
            'requirements.min' => 'Please describe what you would like to automate (at least 20 characters).',
            'consent.accepted' => 'Please confirm you have not included passwords, API keys or other credentials.',
        ]);

        $redacted = false;
        $clean = [];
        foreach (['requirements' => 5000, 'current_tools' => 1500, 'integrations' => 1500, 'notes' => 2000] as $field => $max) {
            [$value, $hit] = SafeText::redactSecrets(SafeText::block($data[$field] ?? null, $max));
            $clean[$field] = $value;
            $redacted = $redacted || $hit;
        }
        foreach (['name' => 120, 'business_type' => 120, 'company' => 150] as $field => $max) {
            [$value, $hit] = SafeText::redactSecrets(SafeText::line($data[$field] ?? null, $max));
            $clean[$field] = $value;
            $redacted = $redacted || $hit;
        }
        if ($clean['name'] === null || $clean['business_type'] === null || $clean['requirements'] === null || mb_strlen($clean['requirements']) < 20) {
            throw ValidationException::withMessages(['requirements' => 'Please enter your name, business type and requirements as plain text.']);
        }

        $plan = filled($data['plan'] ?? null)
            ? Plan::where('slug', $data['plan'])->whereHas('category', fn ($q) => $q->where('slug', self::CATEGORY))->value('id')
            : null;

        [$lead, $duplicate] = $leads->submit($clean + [
            'email' => mb_strtolower(trim($data['email'])),
            'phone' => AutomationLeadService::phone($data['phone_country'], $data['phone']),
            'website' => $data['website'] ?? null,
            'business_size' => $data['business_size'] ?? null,
            'timeline' => $data['timeline'] ?? null,
            'budget' => $data['budget'] ?? null,
            'plan_id' => $plan,
            'redacted' => $redacted,
        ], $request->user(), $token, $request->ip());

        return redirect()->route('business-automation.thanks')->with('lead_confirmation', [
            'reference' => $lead->reference,
            'duplicate' => $duplicate,
            'redacted' => ! $duplicate && $lead->redacted,
            'account' => $lead->user_id !== null,
            'id' => $lead->id,
        ]);
    }

    /** Confirmation page. The reference comes only from this session's flash, never from the URL. */
    public function thanks(Request $request): View|RedirectResponse
    {
        $confirmation = $request->session()->get('lead_confirmation');

        return $confirmation ? view('pages.business-automation-thanks', ['c' => $confirmation]) : redirect()->route('business-automation');
    }

    /** Returns the per-render submission id; rejects missing, tampered, too-fast or stale forms. */
    private function formToken(string $raw): string
    {
        try {
            $payload = json_decode(Crypt::decryptString($raw), true, 4, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages(['form' => 'This form has expired. Please reload the page and try again.']);
        }

        $age = time() - (int) ($payload['t'] ?? 0);
        if ($age < self::MIN_FILL_SECONDS) {
            throw ValidationException::withMessages(['form' => 'That was very quick — please check your details and submit again.']);
        }
        if ($age > self::MAX_FORM_AGE_SECONDS || ! is_string($payload['id'] ?? null)) {
            throw ValidationException::withMessages(['form' => 'This form has expired. Please reload the page and try again.']);
        }

        return hash('sha256', $payload['id']);
    }
}
