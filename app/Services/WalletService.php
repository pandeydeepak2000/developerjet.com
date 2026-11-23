<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * All balance movements go through here. Each call runs in a transaction holding a row lock on the wallet, writes an
 * immutable ledger row with the resulting balance, and is idempotent per `reference` (unique constraint backs this up).
 */
class WalletService
{
    public function walletFor(User $user): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $user->id], ['currency' => 'INR', 'balance_minor' => 0]);
    }

    public function credit(User $user, int $amountMinor, string $source, string $reference, ?string $description = null, ?User $actor = null, array $meta = []): WalletTransaction
    {
        return $this->move($user, 'credit', $amountMinor, $source, $reference, $description, $actor, $meta);
    }

    public function debit(User $user, int $amountMinor, string $source, string $reference, ?string $description = null, ?User $actor = null, array $meta = []): WalletTransaction
    {
        return $this->move($user, 'debit', $amountMinor, $source, $reference, $description, $actor, $meta);
    }

    private function move(User $user, string $type, int $amount, string $source, string $reference, ?string $description, ?User $actor, array $meta): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive.');
        }

        $this->walletFor($user);

        try {
            return DB::transaction(function () use ($user, $type, $amount, $source, $reference, $description, $actor, $meta) {
                // Idempotent replay: the same reference never moves money twice.
                if ($existing = WalletTransaction::where('reference', $reference)->first()) {
                    return $existing;
                }

                $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
                $new = $type === 'credit' ? $wallet->balance_minor + $amount : $wallet->balance_minor - $amount;

                if ($new < 0) {
                    throw new InsufficientFundsException('Insufficient wallet balance.');
                }

                $wallet->update(['balance_minor' => $new]);

                return $wallet->transactions()->create([
                    'type' => $type,
                    'amount_minor' => $amount,
                    'description' => $description,
                    'reference' => $reference,
                    'source' => $source,
                    'balance_after_minor' => $new,
                    'actor_id' => $actor?->id,
                    'meta' => $meta ?: null,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Lost a race with an identical request: the winner's row is the result.
            return WalletTransaction::where('reference', $reference)->firstOrFail();
        }
    }
}
