<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule($schedule): void
    {
        // CIE v2.3.2: Weekly AI audit Monday 09:00 UTC; decay escalation after.
        $schedule->command('decay:run')->weeklyOn(1, '09:00');
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
