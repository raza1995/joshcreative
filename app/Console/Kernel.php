<?php

namespace App\Console;

use App\Services\SlackService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('cache:refresh-analytics')->everyThreeHours();
        // $schedule->command('gmail:process-emails-updated')->everyThreeHours();
        // $schedule->command('php artisan gmail:process-emails-updated')->cron(expression: '0 */3 * * *')->withoutOverlapping();
        $schedule->command('gmail:process-emails-updated')->hourly();
        $schedule->command('gmail:check-invoices')->everyFiveMinutes();
        $schedule->call(function () {
            app(SlackService::class)->sendMessage("⏰ Automated check-in from Mycolean AI.");
        })->everyFiveMinutes();



    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
