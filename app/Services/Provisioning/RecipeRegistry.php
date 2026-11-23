<?php

namespace App\Services\Provisioning;

use InvalidArgumentException;

/**
 * Read-only access to versioned deployment recipes and their fixed command catalogue.
 * Customer-controlled text can never reach a command: templates are fixed and the only placeholder ({domain}) is
 * validated and shell-escaped here.
 */
class RecipeRegistry
{
    public const DOMAIN_REGEX = '/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/i';

    public function recipe(string $kind): ?array
    {
        return config('provisioning.recipes.'.$kind);
    }

    /** @return array<int, string> */
    public function steps(string $kind): array
    {
        return $this->recipe($kind)['steps'] ?? [];
    }

    public function step(string $id): ?array
    {
        return config('provisioning.step_catalog.'.$id);
    }

    public function label(string $id): string
    {
        return $this->step($id)['label'] ?? $id;
    }

    /** True when the owner has approved a command template for the step. */
    public function approved(string $id): bool
    {
        return ($this->step($id)['command'] ?? null) !== null;
    }

    /** Recipe-level problems (unknown steps, bad versions, duplicates). Empty = valid. */
    public function validate(): array
    {
        $problems = [];
        foreach (config('provisioning.recipes', []) as $kind => $recipe) {
            if (! preg_match('/^\d+\.\d+\.\d+$/', $recipe['version'] ?? '')) {
                $problems[] = "{$kind}: version is not semver";
            }
            if (count($recipe['steps'] ?? []) !== count(array_unique($recipe['steps'] ?? []))) {
                $problems[] = "{$kind}: duplicate steps";
            }
            foreach ($recipe['steps'] ?? [] as $step) {
                if (! $this->step($step)) {
                    $problems[] = "{$kind}: unknown step {$step}";
                }
            }
            if (! in_array($recipe['access_model'] ?? null, ['managed', 'unmanaged'], true)) {
                $problems[] = "{$kind}: access_model missing";
            }
        }

        return $problems;
    }

    /** POSIX single-quote escaping (the remote shell is Linux; PHP's escapeshellarg is OS-dependent). */
    public static function posixQuote(string $value): string
    {
        return "'".str_replace("'", "'\''", $value)."'";
    }

    /** Fixed command with the only allowed placeholder substituted by a validated, quoted domain. */
    public function render(string $id, ?string $domain): string
    {
        $template = $this->step($id)['command'] ?? null;
        if ($template === null) {
            throw new InvalidArgumentException("Step {$id} has no approved command.");
        }
        if (str_contains($template, '{domain}')) {
            if ($domain === null || ! preg_match(self::DOMAIN_REGEX, $domain)) {
                throw new InvalidArgumentException('A verified domain is required.');
            }
            $template = str_replace('{domain}', self::posixQuote(strtolower($domain)), $template);
        }

        return $template;
    }
}
