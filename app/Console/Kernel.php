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

        // $schedule->command('shopify:fetch-orders-mycolean')->everySixHours();
        // $schedule->command('inspire')->hourly();
        // $schedule->command('cache:refresh-analytics')->everyThreeHours();
        // $schedule->command('gmail:process-emails-updated')->everyThreeHours();
        // $schedule->command('php artisan gmail:process-emails-updated')->cron(expression: '0 */3 * * *')->withoutOverlapping();
        // $schedule->command('gmail:process-emails-updated')->hourly();
        // $schedule->command('gmail:check-invoices')->everyFiveMinutes();
        // $schedule->command('emails:fetch')->everyMinute();
        // $schedule->command('emails:fetch-unread')->everyFiveMinutes();
        // $schedule->command('emails:process')->everyFiveMinutes();
        $schedule->command('facebook:sync-daily')->everyThreeHours();
        // $schedule->job(new ExportFacebookAdsToGoogleSheetJob('daily'))->dailyAt('06:00');
        // $schedule->job(new ExportFacebookAdsToGoogleSheetJob('weekly'))->weeklyOn(1, '07:00'); // every Monday
        // $schedule->job(new ExportFacebookAdsToGoogleSheetJob('monthly'))->monthlyOn(1, '08:00'); // 1st day of month
        $schedule->command('facebook:aggregate-stats')->everyThirtyMinutes();
        $schedule->command('facebook:export daily')->dailyAt('06:00');
        $schedule->command('facebook:export weekly')->weeklyOn(1, '07:00');
        $schedule->command('facebook:export monthly')->monthlyOn(1, '08:00');


        $schedule->command('facebook:aggregate-custom-metrics --days=60')->weeklyOn(0, '1:00'); // Sunday
        $schedule->command('facebook:aggregate-custom-metrics --days=90')->weeklyOn(0, '1:15');
        $schedule->command('facebook:aggregate-custom-metrics --days=15')->weeklyOn(0, '1:30');
        $schedule->command('facebook:aggregate-custom-metrics --days=30')->weeklyOn(0, '1:45');
        $schedule->command('facebook:aggregate-custom-metrics --days=60')->weeklyOn(0, '2:00');
        $schedule->command('facebook:aggregate-custom-metrics --days=90')->weeklyOn(0, '2:15');
        $schedule->command('facebook:aggregate-custom-metrics --days=120')->weeklyOn(0, '2:30');
        $schedule->command('facebook:aggregate-custom-metrics --days=150')->weeklyOn(0, '2:45');
        $schedule->command('facebook:aggregate-custom-metrics --days=180')->weeklyOn(0, '3:00');
        $schedule->command('facebook:aggregate-custom-metrics --days=210')->weeklyOn(0, '3:15');
        $schedule->command('facebook:aggregate-custom-metrics --days=240')->weeklyOn(0, '4:30');
        $schedule->command('facebook:aggregate-custom-metrics --days=270')->weeklyOn(0, '5:45');
        $schedule->command('facebook:aggregate-custom-metrics --days=300')->weeklyOn(0, '6:00');
        $schedule->command('facebook:aggregate-custom-metrics --days=330')->weeklyOn(0, '7:15');
        $schedule->command('facebook:aggregate-custom-metrics --days=360')->weeklyOn(0, '8:30');
     
        // Run twice a month: 1st and 15th
        $schedule->command('facebook:aggregate-custom-metrics --days=120')
            ->cron('0 1 1,15 * *');
    
        // Run monthly on 1st
        $schedule->command('facebook:aggregate-custom-metrics --days=365')
            ->monthlyOn(1, '1:30');
        // $schedule->call(function () {
        //     app(SlackService::class)->sendMessage("⏰ Automated check-in from Mycolean AI.");
        // })->everyFiveMinutes();
        for ($m = 1; $m <= 12; $m++) {
            $schedule->command("facebook:sync-metrics --month={$m}")
                     ->monthlyOn(1, '01:30')
                     ->when(function () use ($m) {
                         return (int) now()->subMonth()->format('n') === $m;
                     });
        }


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
