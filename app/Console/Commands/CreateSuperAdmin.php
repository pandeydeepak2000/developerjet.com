<?php

namespace App\Console\Commands;

use App\Http\Controllers\Staff\StaffPasswordController;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use App\Support\StaffPortal;
use App\Support\StaffSecurity;
use Database\Seeders\RbacSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Secure, interactive Super Admin initialisation. There are no default credentials:
 *  - a NEW account's password is typed by the operator at a hidden prompt (twice), never passed as an argument,
 *    printed or logged; the email must then be verified with an emailed code at first staff sign-in, the password
 *    must be changed at first sign-in, and an authenticator app must be enrolled (mandatory for Super Admins);
 *  - an EXISTING account is never promoted just because the email matches: it requires --promote, an explicit typed
 *    confirmation of the email, and the account's current password (proof of control). Customers who cannot prove
 *    control must first reset their password through the normal email flow.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'staff:create-super {email} {name?} {--promote : Allow promoting an existing account (requires its current password)}';

    protected $description = 'Initialise a Super Admin account interactively (password prompted, never shown).';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('This command must be run interactively so the password can be typed at a hidden prompt.');

            return self::FAILURE;
        }

        (new RbacSeeder)->run();
        $role = Role::where('slug', 'super-admin')->where('is_super', true)->firstOrFail();
        $email = strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:190']])->fails()) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        return $user ? $this->promote($user, $role) : $this->create($email, $role);
    }

    private function create(string $email, Role $role): int
    {
        $name = (string) ($this->argument('name') ?: $this->ask('Full name'));
        if (trim($name) === '' || mb_strlen($name) > 120) {
            $this->error('A name (max 120 characters) is required.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (12+ characters, upper and lower case, number, symbol)');
        $confirm = (string) $this->secret('Repeat the password');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirm],
            ['password' => ['required', 'confirmed', StaffPasswordController::rule()]],
        );
        if ($validator->fails()) {
            $this->error($validator->errors()->first('password'));

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($email, $name, $password, $role) {
            $user = new User(['name' => trim($name), 'email' => $email, 'password' => $password]);
            $user->forceFill([
                'role' => User::ROLE_STAFF, 'role_id' => $role->id, 'is_active' => true,
                'email_verified_at' => null, 'password_change_required' => true,
            ])->save();
            Audit::log('staff.super_created', $user, ['source' => 'cli'], null);

            return $user;
        });
        unset($password, $confirm);

        $this->info("Super Admin account created for {$user->email}.");
        $this->line('Sign in at: '.StaffPortal::url('login'));
        $this->line('At first sign-in you must: confirm the email with the code we send, choose a new password, and enrol an authenticator app.');

        return self::SUCCESS;
    }

    private function promote(User $user, Role $role): int
    {
        $current = $user->isAdmin() ? 'legacy owner' : ($user->role === User::ROLE_STAFF ? 'staff ('.($user->staffRole?->name ?? 'no role').')' : 'customer');
        $this->line("An account for {$user->email} already exists. Current type: {$current}. Active: ".(($user->is_active ?? true) ? 'yes' : 'no').'.');

        if ($user->isSuperAccount() && ($user->is_active ?? true)) {
            $this->info('This account is already an active Super Admin. Nothing changed.');

            return self::SUCCESS;
        }

        if (! ($user->is_active ?? true)) {
            $this->error('The account is suspended/inactive. Reactivate it through an approved process first; it is not reactivated automatically.');

            return self::FAILURE;
        }

        if (! $this->option('promote')) {
            $this->error('Refusing to promote an existing account without --promote. Promotion also requires the account\'s current password.');

            return self::FAILURE;
        }

        if (strtolower(trim((string) $this->ask('Type the email address again to confirm promotion to Super Admin'))) !== $user->email) {
            $this->error('Confirmation did not match. Nothing changed.');

            return self::FAILURE;
        }

        $verified = false;
        for ($i = 0; $i < 3 && ! $verified; $i++) {
            $verified = Hash::check((string) $this->secret('Current password of this account (proof of control)'), (string) $user->password);
        }
        if (! $verified) {
            Audit::log('staff.super_promotion_failed', $user, ['source' => 'cli'], null);
            $this->error('Proof of control failed. Nothing changed. If the owner cannot sign in, they must reset the password by email first.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $role, $current) {
            $user->forceFill(['role' => User::ROLE_STAFF, 'role_id' => $role->id, 'password_change_required' => true])->save();
            StaffSecurity::revokeSessions($user);
            Audit::log('staff.super_promoted', $user, ['source' => 'cli', 'from' => $current], null);
        });

        $this->info("{$user->email} is now a Super Admin.");
        $this->line('Sign in at: '.StaffPortal::url('login'));
        $this->line('At first staff sign-in: '.($user->email_verified_at ? '' : 'confirm the email with the code we send, ').'choose a new password, and enrol an authenticator app.');

        return self::SUCCESS;
    }
}
