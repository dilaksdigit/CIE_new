<?php

namespace App\Console;

use App\Support\BusinessRules;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule($schedule): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §12.1 — ai_audit_cron_schedule
        $aiAuditCron = (string) BusinessRules::get('sync.ai_audit_cron_schedule', '0 9 * * 1');
        $schedule->command('decay:run')->cron($aiAuditCron);
        $schedule->command('cie:decay-check')->weeklyOn(1, '09:30');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
