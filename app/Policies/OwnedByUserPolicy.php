<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer-area records are owner-only. Staff (including the legacy owner role) work on customer records through the
 * staff portal, which has its own guard, MFA, revocation and audit — never through a customer (web) session.
 */
abstract class OwnedByUserPolicy
{
    public function view(User $user, Model $record): bool
    {
        return $record->user_id === $user->id;
    }
}
