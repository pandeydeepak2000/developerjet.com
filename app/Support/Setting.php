<?php

namespace App\Support;

use App\Models\Setting as SettingRow;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value application settings. Secrets are stored encrypted (APP_KEY) and are never echoed back to forms:
 * admin screens show only whether a secret is set.
 */
class Setting
{
    /** @var array<string, SettingRow>|null */
    private static ?array $cache = null;

    private static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = Schema::hasTable('settings') ? SettingRow::all()->keyBy('key')->all() : [];
        }

        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = self::all()[$key] ?? null;

        if (! $row || $row->value === null || $row->value === '') {
            return $default;
        }

        return $row->is_encrypted ? Crypt::decryptString($row->value) : $row->value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        return $value === null ? $default : in_array($value, ['1', 1, true, 'true', 'on'], true);
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    public static function set(string $key, mixed $value, bool $encrypt = false): void
    {
        $stored = ($value === null || $value === '') ? null : ($encrypt ? Crypt::encryptString((string) $value) : (string) $value);

        SettingRow::updateOrCreate(['key' => $key], ['value' => $stored, 'is_encrypted' => $encrypt]);
        self::flush();
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
