<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Service extends Model
{
    protected $guarded = [];

    /** Secrets never leave the model through serialization. */
    protected $hidden = ['ai_api_key'];

    protected function casts(): array
    {
        return [
            'renews_at' => 'datetime',
            'config' => 'array',
            'domain_verified' => 'boolean',
            'ai_api_key' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class)->latest('id');
    }

    /** Latest LIVE deployment only (dry runs never drive customer-facing progress). Cheap for list pages. */
    public function latestLiveDeployment(): HasOne
    {
        return $this->hasOne(Deployment::class)->where('mode', 'live')->latestOfMany();
    }

    /** The recurring billing agreement for this service (one per service). */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /** Recipes that are opened in a browser and therefore need a web address (domain / subdomain). */
    public const DOMAIN_KINDS = ['hermes_workspace', 'n8n', 'bundle', 'openclaw'];

    /** Recipes that use the customer's own AI provider key. */
    public const AI_KINDS = ['hermes_cli', 'hermes_workspace', 'bundle', 'openclaw', 'claude_code'];

    public function needsDomain(): bool
    {
        return in_array($this->kind, self::DOMAIN_KINDS, true);
    }

    public function usesAi(): bool
    {
        return in_array($this->kind, self::AI_KINDS, true);
    }

    /**
     * The address the application is served on: the customer's own domain once DNS-verified, otherwise the
     * subdomain staff assigned (DeveloperJet controls that DNS, so it needs no customer verification).
     */
    public function appDomain(): ?string
    {
        if ($this->domain_verified && $this->custom_domain) {
            return $this->custom_domain;
        }

        return ($this->config['assigned_domain'] ?? null) ?: null;
    }

    /** Hermes services are "configuration incomplete" until an AI provider and key are supplied. */
    public function aiConfigured(): bool
    {
        return $this->ai_provider !== null && $this->getRawOriginal('ai_api_key') !== null;
    }
}
