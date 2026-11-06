<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Also run by the RenewMaintenanceContracts middleware on the first
        // request of the day, in case no scheduler is set up on the server.
        $schedule->command('contracts:auto-renew')->dailyAt('00:05');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
