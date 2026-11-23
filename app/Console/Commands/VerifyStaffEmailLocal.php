<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OtpService;
use App\Support\Audit;
use Illuminate\Auth\Events\Verified;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyStaffEmailLocal extends Command
{
    protected $signature = 'staff:verify-email-local {email=ambitionmanagements@gmail.com}';

    protected $description = 'Mark the existing Super Admin email as verified in the local database only (interactive, local only).';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Refusing: this command runs only when APP_ENV is local (current: '.app()->environment().'). Nothing changed.');

            return self::FAILURE;
        }

        $database = (string) config('database.connections.'.config('database.default').'.database');

        if ($database !== 'developerjet_local') {
            $this->error('Refusing: expected the local database developerjet_local, got '.$database.'. Nothing changed.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('This command must be run interactively so the operator can confirm the email. Nothing changed.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->argument('email')));

        if ($email !== 'ambitionmanagements@gmail.com') {
            $this->error('Refusing: this command only verifies ambitionmanagements@gmail.com. Nothing changed.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error('No account exists for '.$email.' in this database. Nothing changed, and no account was created.');

            return self::FAILURE;
        }

        if (! $user->isSuperAccount() || ! $user->isStaff()) {
            $this->error('Refusing: '.$email.' is not an active Super Admin account. Nothing changed.');

            return self::FAILURE;
        }

        if ($user->hasVerifiedEmail()) {
            $this->info('This email is already verified. Nothing changed.');

            return self::SUCCESS;
        }

        $this->warn('LOCAL ONLY: this marks the existing Super Admin email as verified in developerjet_local because SMTP is not configured locally.');
        $this->line('Password, forced first-login password change, mandatory authenticator enrolment, permissions and sessions are unchanged.');

        if (strtolower(trim((string) $this->ask('Type the email address again to confirm'))) !== $email) {
            $this->error('Confirmation did not match. Nothing changed.');

            return self::FAILURE;
        }

        if (! $this->confirm('Mark '.$email.' as email-verified in developerjet_local only?', false)) {
            $this->line('Cancelled. Nothing changed.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($user) {
            $user->markEmailAsVerified();
            $user->otps()->where('purpose', OtpService::PURPOSE)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            Audit::log('admin.email_verified_local', $user, ['source' => 'cli', 'reason' => 'smtp-not-configured-local-dev-only'], $user->id);
            event(new Verified($user));
        });

        $this->info('Email verified locally for '.$email.'.');
        $this->line('Next: sign in at http://127.0.0.1:8000/staff/developerjet/login, change the password when prompted, then enrol an authenticator app.');

        return self::SUCCESS;
    }
}
