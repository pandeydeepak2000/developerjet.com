<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Immutable ledger row: corrections are made with new compensating entries, never edits. */
class WalletTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Wallet ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Wallet ledger entries cannot be deleted.'));
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
