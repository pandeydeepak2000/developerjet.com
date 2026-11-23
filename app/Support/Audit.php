<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    /** Never record secrets: callers pass only identifiers and non-sensitive values. */
    public static function log(string $action, ?Model $subject = null, array $meta = [], ?int $userId = null): AuditLog
    {
        $request = app()->bound('request') ? request() : null;

        return AuditLog::create([
            // On staff-portal pages the default guard is the customer guard: attribute to the staff guard instead.
            'user_id' => $userId ?? ($request?->is('staff/*') ? auth('staff')->id() : auth()->id()),
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => $request?->ip(),
        ]);
    }
}
