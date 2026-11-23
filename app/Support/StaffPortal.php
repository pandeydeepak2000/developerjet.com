<?php

namespace App\Support;

use App\Models\Setting as SettingRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * The staff portal lives at a stable route pattern `/staff/{portal}/…`. The {portal} segment is compared with the
 * slug saved in the database on every request (ResolveStaffPortal middleware), so changing it never needs a
 * route rebuild or a route-cache clear, and the previous slug stops working immediately (404).
 *
 * The slug is NOT a security control on its own: password, throttling, MFA, authorization and session checks
 * apply regardless of it.
 */
class StaffPortal
{
    public const SETTING = 'staff_portal.slug';

    public static function slug(): string
    {
        $slug = (string) Setting::get(self::SETTING, '');

        return self::validationError($slug, false) === null ? $slug : (string) config('staff.default_slug');
    }

    /** Absolute URL of a staff-portal page for the current slug, e.g. url('login'). */
    public static function url(string $page = 'login', array $params = []): string
    {
        return route('staff.'.$page, ['portal' => self::slug()] + $params);
    }

    public static function path(?string $slug = null): string
    {
        return '/staff/'.($slug ?? self::slug()).'/login';
    }

    /** Returns a human-readable error, or null when the slug is acceptable. */
    public static function validationError(string $slug, bool $checkCurrent = true): ?string
    {
        $min = (int) config('staff.slug_min');
        $max = (int) config('staff.slug_max');

        if ($slug === '' || strlen($slug) < $min || strlen($slug) > $max) {
            return "Use between {$min} and {$max} characters.";
        }
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return 'Use lowercase letters, numbers and single hyphens only (no leading or trailing hyphen).';
        }
        if (ctype_digit(str_replace('-', '', $slug))) {
            return 'Include at least one letter.';
        }
        if (in_array($slug, (array) config('staff.reserved_slugs'), true)) {
            return 'That slug is reserved. Choose another.';
        }
        if (self::conflictsWithRoute($slug)) {
            return 'That slug conflicts with an existing route.';
        }
        if ($checkCurrent && $slug === self::slug()) {
            return 'That is already the current slug.';
        }

        return null;
    }

    /** Any registered route whose path starts with staff/{slug} literally would shadow or be shadowed. */
    private static function conflictsWithRoute(string $slug): bool
    {
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if ($uri === 'staff/'.$slug || str_starts_with($uri, 'staff/'.$slug.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Atomically replace the slug and audit it. $actor is null for the CLI recovery command.
     *
     * @return array{old: string, new: string}
     */
    public static function change(string $new, ?User $actor, string $source = 'web', bool $allowCurrent = false): array
    {
        $new = strtolower(trim($new));
        $error = self::validationError($new, ! $allowCurrent);
        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }

        $result = DB::transaction(function () use ($new, $actor, $source) {
            // Lock the row (or create it) so two concurrent changes serialise.
            $row = SettingRow::where('key', self::SETTING)->lockForUpdate()->first();
            $old = $row && $row->value ? (string) $row->value : (string) config('staff.default_slug');

            SettingRow::updateOrCreate(['key' => self::SETTING], ['value' => $new, 'is_encrypted' => false]);

            Audit::log('staff_portal.slug_changed', null, [
                'old_path' => self::path($old), 'new_path' => self::path($new), 'source' => $source,
            ], $actor?->id);

            return ['old' => $old, 'new' => $new];
        });

        Setting::flush();

        return $result;
    }
}
