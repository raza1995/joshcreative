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

    /** @var Collection<array|object> */
    public Collection $ads;

    public Carbon $startDate;
    public Carbon $endDate;
    public int    $windowDays;   // <-- NEW

    /**
     * @param Collection $ads          Aggregated ads from the command
     * @param Carbon     $startDate    Beginning of the "current" window
     * @param Carbon     $endDate      End of the "current" window
     * @param int        $windowDays   Size of each comparison window (e.g. 30)
     */
    public function __construct(Collection $ads, Carbon $startDate, Carbon $endDate, int $windowDays = 30)
    {
        $this->ads        = $ads;
        $this->startDate  = $startDate;
        $this->endDate    = $endDate;
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
