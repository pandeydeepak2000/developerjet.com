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

    /**
     * The command template for a step: a recipe-specific override (recipes.<kind>.commands.<step>) wins over the
     * shared catalogue. Null = not approved. "@path" means the script file resources/provisioning/<path>.sh.
     */
    public function command(string $id, ?string $kind = null): ?string
    {
        $override = $kind ? ($this->recipe($kind)['commands'][$id] ?? null) : null;

        return $override ?? ($this->step($id)['command'] ?? null);
    }

    /** True when the owner has approved a command template for the step (for this recipe, when given). */
    public function approved(string $id, ?string $kind = null): bool
    {
        return $this->command($id, $kind) !== null;
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

    /** A reviewed installer script shipped in the repo (resources/provisioning/<name>.sh). */
    public static function script(string $name): string
    {
        if (! preg_match('#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#', $name)) {
            throw new InvalidArgumentException("Invalid script name {$name}.");
        }
        $path = resource_path('provisioning/'.$name.'.sh');
        if (! is_file($path)) {
            throw new InvalidArgumentException("Installer script {$name} is missing.");
        }

        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    /** POSIX single-quote escaping (the remote shell is Linux; PHP's escapeshellarg is OS-dependent). */
    public static function posixQuote(string $value): string
    {
        return "'".str_replace("'", "'\''", $value)."'";
    }

    /** Fixed command with the only allowed placeholder substituted by a validated, quoted domain. */
    public function render(string $id, ?string $domain, ?string $kind = null): string
    {
        $template = $this->command($id, $kind);
        if ($template === null) {
            throw new InvalidArgumentException("Step {$id} has no approved command.");
        }
        if (str_starts_with($template, '@')) {
            $template = self::script(substr($template, 1));
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
