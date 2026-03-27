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
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §12.1 — AI citation audit first, then decay escalation.
        $aiAuditCron = (string) BusinessRules::get('sync.ai_audit_cron_schedule', '0 9 * * 1');
        $schedule->command('audit:run-weekly')->cron($aiAuditCron);

        $decayCron = (string) BusinessRules::get('sync.decay_cron_schedule', '0 10 * * 1');
        $schedule->command('decay:run')->cron($decayCron);
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
