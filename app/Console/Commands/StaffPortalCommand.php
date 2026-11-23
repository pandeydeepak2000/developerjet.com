<?php

namespace App\Console\Commands;

use App\Support\StaffPortal;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Lock-out recovery for the staff login URL. Requires shell access to the server (the trust root), prints only
 * the URL, and audits every change with source "cli".
 */
class StaffPortalCommand extends Command
{
    protected $signature = 'staff:portal
        {--show : Print the current staff login URL}
        {--set= : Change the slug to this value (validated)}
        {--reset : Restore the default slug from config/staff.php}
        {--force : Do not ask for confirmation}';

    protected $description = 'Show, change or reset the staff portal login URL (recovery tool)';

    public function handle(): int
    {
        $new = $this->option('reset') ? (string) config('staff.default_slug') : $this->option('set');

        if ($new === null || $new === false) {
            $this->line('Current staff login URL: '.StaffPortal::url('login'));

            return self::SUCCESS;
        }

        $new = strtolower(trim((string) $new));
        if ($new === StaffPortal::slug()) {
            $this->info('The staff login URL is already '.StaffPortal::url('login'));

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Change the staff login URL to '.url(StaffPortal::path($new)).'? The current URL will stop working.')) {
            return self::FAILURE;
        }

        try {
            // --reset may restore the default even if it is somehow reserved by a later config change.
            StaffPortal::change($new, null, 'cli');
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Staff login URL is now: '.StaffPortal::url('login'));

        return self::SUCCESS;
    }
}
