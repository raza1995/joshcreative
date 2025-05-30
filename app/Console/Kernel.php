<?php

namespace App\Console;

use App\Jobs\ExportFacebookAdsToGoogleSheetJob;
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

        $schedule->command('shopify:fetch-orders-mycolean')->everySixHours();
        // $schedule->command('inspire')->hourly();
        // $schedule->command('cache:refresh-analytics')->everyThreeHours();
        // $schedule->command('gmail:process-emails-updated')->everyThreeHours();
        // $schedule->command('php artisan gmail:process-emails-updated')->cron(expression: '0 */3 * * *')->withoutOverlapping();
        // $schedule->command('gmail:process-emails-updated')->hourly();
        // $schedule->command('gmail:check-invoices')->everyFiveMinutes();
        // $schedule->command('emails:fetch')->everyMinute();
        // $schedule->command('emails:fetch-unread')->everyFiveMinutes();
        // $schedule->command('emails:process')->everyFiveMinutes();
        $schedule->command('facebook:sync-daily')->dailyAt('03:00'); // 3 AM daily

        $schedule->job(new ExportFacebookAdsToGoogleSheetJob('daily'))->dailyAt('06:00');
    $schedule->job(new ExportFacebookAdsToGoogleSheetJob('weekly'))->weeklyOn(1, '07:00'); // every Monday
    $schedule->job(new ExportFacebookAdsToGoogleSheetJob('monthly'))->monthlyOn(1, '08:00'); // 1st day of month
        // $schedule->call(function () {
        //     app(SlackService::class)->sendMessage("⏰ Automated check-in from Mycolean AI.");
        // })->everyFiveMinutes();



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
