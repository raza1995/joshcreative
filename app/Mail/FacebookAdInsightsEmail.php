<?php


namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class FacebookAdInsightsEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $ads;
    public Carbon $startDate;
    public Carbon $endDate;
    public int    $windowDays;

    public function __construct(Collection $ads, Carbon $startDate, Carbon $endDate, int $windowDays)
    {
        $this->ads = $ads;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->windowDays = $windowDays;
        
    }

    public function build()
    {
        $label = "{$this->windowDays}‑day";

        return $this->subject(
                    "Facebook Ad Insights ({$label}) " .
                    $this->startDate->format('M j') . ' – ' . $this->endDate->format('M j')
                )
                ->view('emails.facebook_ad_insights')
                ->with([
                    'ads'        => $this->ads,
                    'start'      => $this->startDate,
                    'end'        => $this->endDate,
                    'windowDays' => $this->windowDays,   // <-- pass to Blade
                ]);
    }
}

