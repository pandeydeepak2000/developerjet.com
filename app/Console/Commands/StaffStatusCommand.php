<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\StaffPortal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * READ-ONLY staff sign-in diagnostics for the server operator. Explains why "These credentials do not match an active
 * staff account." appears for a given email and prints the safe next step. It never changes data and never prints
 * password hashes, MFA secrets, recovery codes or tokens. (The login page itself stays generic so it cannot be used
 * to discover which staff accounts exist.)
 */
class StaffStatusCommand extends Command
{
    protected $signature = 'staff:status {email? : Staff email address to check}';

    protected $description = 'Read-only staff login diagnostics (account state and the safe next step)';

    public function handle(): int
    {
        $this->line('Database connection: '.config('database.default').' / '.config('database.connections.'.config('database.default').'.database'));
        $this->line('Staff login URL: '.StaffPortal::url('login'));

        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
            $this->error('The users/roles tables are missing: run "php artisan migrate" first.');

            return self::FAILURE;
        }

        $superRoles = Role::where('is_super', true)->pluck('id');
        $this->line('Staff accounts: '.User::where('role', User::ROLE_STAFF)->count()
            .' · active Super Admins: '.User::where('role', User::ROLE_STAFF)->whereIn('role_id', $superRoles)->where('is_active', true)->count());

        $email = $this->argument('email');
        if (! $email) {
            return self::SUCCESS;
        }

        $email = strtolower(trim((string) $email));
        $user = User::where('email', $email)->first();
        $this->newLine();

        if (! $user) {
            $this->warn("No account exists for {$email} in this database — this alone causes the login error.");
            $this->line('Create the Super Admin interactively (you will type the password at a hidden prompt; it is never shown or stored in plain text):');
            $this->line("  php artisan staff:create-super {$email} \"Full Name\"");

            return self::SUCCESS;
        }

        $type = $user->isAdmin() ? 'legacy owner' : ($user->role === User::ROLE_STAFF ? 'staff' : 'customer');
        $rows = [
            ['Account type', $type],
            ['Staff role', $user->staffRole?->name ?? '—'],
            ['Super Admin', $user->isSuperAccount() ? 'yes' : 'no'],
            ['Active', ($user->is_active ?? true) ? 'yes' : 'no (suspended)'],
            ['Email verified', $user->email_verified_at ? 'yes' : 'no — a code is emailed at first staff sign-in'],
            ['Password change required', $user->password_change_required ? 'yes — at next sign-in' : 'no'],
            ['Authenticator (MFA) enrolled', $user->hasTwoFactor() ? 'yes' : 'no'.($user->isSuperAccount() ? ' — mandatory for Super Admin, enrolment is requested after sign-in' : '')],
        ];
        $this->table(['Check', 'Result'], $rows);

        if ($type === 'customer') {
            $this->warn('This is a customer account, so the staff login rejects it. It is never promoted automatically.');
            $this->line('Only if this really is the owner and they know its current password:');
            $this->line("  php artisan staff:create-super {$email} --promote   (asks for confirmation and the account's current password)");
        } elseif (! ($user->is_active ?? true)) {
            $this->warn('The account is suspended. Another Super Admin can reactivate it in Admin → Staff (with password re-confirmation).');
        } elseif (! $user->isStaff()) {
            $this->warn('The account has no valid staff role. A Super Admin must assign one in Admin → Staff.');
        } else {
            $this->info('The account can sign in. If the password is unknown, use "Forgot password?" on the staff login page:');
            $this->line('  '.StaffPortal::url('password.request'));
            $this->line('The reset link is emailed (with MAIL_MAILER=log locally it is written to storage/logs/laravel.log). Too many failed attempts lock sign-in for 5 minutes.');
        }

        return self::SUCCESS;
    }
}
