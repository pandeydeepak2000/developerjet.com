<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/** A customer's AI provider key, saved once and reused by their services. Secret is encrypted and write-only. */
class CustomerApiKey extends Model
{
    protected $guarded = [];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function providerLabel(): string
    {
        return config('provisioning.ai_providers.'.$this->provider, $this->provider);
    }

    /** "sk-…a1b2" style hint: provider prefix shape + last 4. The full key is never shown again. */
    public function masked(): string
    {
        $prefix = match ($this->provider) {
            'openai' => 'sk-', 'anthropic' => 'sk-ant-', 'openrouter' => 'sk-or-', 'google' => 'AIza', default => '',
        };

        return $prefix.'…'.$this->last4;
    }

    /** Services of the same customer that currently use this key. */
    public function servicesUsing(): Collection
    {
        return Service::where('user_id', $this->user_id)->get()->filter(fn (Service $s) => (int) ($s->config['api_key_id'] ?? 0) === $this->id)->values();
    }

    public static function store(User $user, string $provider, string $secret, ?string $label = null): self
    {
        $secret = trim($secret);

        return self::create([
            'user_id' => $user->id, 'provider' => $provider, 'secret' => $secret,
            'last4' => mb_substr($secret, -4),
            'label' => trim((string) $label) !== '' ? mb_substr(trim($label), 0, 80) : config('provisioning.ai_providers.'.$provider, $provider).' key',
        ]);
    }

    /** Copy this key onto a service (the installer reads the service's encrypted column) and remember the link. */
    public function applyTo(Service $service): void
    {
        $service->forceFill([
            'ai_provider' => $this->provider,
            'ai_api_key' => $this->secret,
            'config' => array_merge($service->config ?? [], ['api_key_id' => $this->id, 'ai_skipped' => false]),
        ])->save();
        $this->forceFill(['last_used_at' => now()])->save();
    }
}
