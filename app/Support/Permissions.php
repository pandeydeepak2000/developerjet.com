<?php

namespace App\Support;

class Permissions
{
    /** Every valid "<module>.<action>" string. */
    public static function all(): array
    {
        $out = [];
        foreach (config('permissions.modules') as $module => $def) {
            foreach ($def['actions'] as $action) {
                $out[] = "$module.$action";
            }
        }

        return $out;
    }

    /** Expand wildcard grants ("tickets.*") into concrete permissions. */
    public static function expand(array $grants): array
    {
        $all = self::all();
        $out = [];
        foreach ($grants as $grant) {
            if (str_ends_with($grant, '.*')) {
                $prefix = substr($grant, 0, -1);
                $out = array_merge($out, array_filter($all, fn ($p) => str_starts_with($p, $prefix)));
            } elseif (in_array($grant, $all, true)) {
                $out[] = $grant;
            }
        }

        return array_values(array_unique($out));
    }
}
