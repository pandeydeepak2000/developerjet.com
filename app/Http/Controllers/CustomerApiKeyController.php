<?php

namespace App\Http\Controllers;

use App\Models\CustomerApiKey;
use App\Models\Service;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard → AI API keys. Customers save provider keys once and reuse them across services. Keys are encrypted,
 * shown only as "sk-…a1b2", never logged, never emailed. Only the signed-in customer's own keys are touched.
 */
class CustomerApiKeyController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $keys = CustomerApiKey::where('user_id', $user->id)->latest('id')->get();
        $services = Service::where('user_id', $user->id)->get();

        return view('dashboard.keys', [
            'keys' => $keys,
            'usage' => $keys->mapWithKeys(fn ($k) => [$k->id => $services->filter(fn ($s) => (int) ($s->config['api_key_id'] ?? 0) === $k->id)->values()]),
            'providers' => config('provisioning.ai_providers'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(self::rules());
        $key = CustomerApiKey::store($request->user(), $data['provider'], $data['secret'], $data['label'] ?? null);
        Audit::log('customer.api_key_added', $key, ['provider' => $key->provider], $request->user()->id); // never the key

        return redirect()->route('dashboard.keys')->with('success', $key->providerLabel().' key saved ('.$key->masked().'). You can now pick it on any AI service.');
    }

    public function destroy(Request $request, CustomerApiKey $key): RedirectResponse
    {
        abort_unless($key->user_id === $request->user()->id, 404);
        // Services keep working with the copy they already have; they simply stop being linked to this saved key.
        foreach ($key->servicesUsing() as $service) {
            $config = $service->config ?? [];
            unset($config['api_key_id']);
            $service->forceFill(['config' => $config])->save();
        }
        Audit::log('customer.api_key_deleted', null, ['provider' => $key->provider], $request->user()->id);
        $key->delete();

        return redirect()->route('dashboard.keys')->with('success', 'Key removed from your account. Services already set up keep their current key until you change it.');
    }

    /** Shared validation for a new key (also used by checkout and the service page). */
    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix.'provider' => ['required', 'in:'.implode(',', array_keys(config('provisioning.ai_providers')))],
            $prefix.'secret' => ['required', 'string', 'min:8', 'max:300', 'not_regex:/\s/'],
            $prefix.'label' => ['nullable', 'string', 'max:80'],
        ];
    }
}
