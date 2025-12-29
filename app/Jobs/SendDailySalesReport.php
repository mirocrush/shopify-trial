<?php

namespace App\Jobs;

use App\Mail\DailySalesReportMail;
use App\Models\Sale;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * SendDailySalesReport Job
 * 
 * Scheduled to run daily at 6 PM (18:00).
 * Fetches all sales from the current day and emails summary to admin.
 * 
 * Scheduled in: routes/console.php
 * Schedule::job(SendDailySalesReport::class)->dailyAt('18:00');
 * 
 * TODO: Add CSV export option for better reporting
 */
class SendDailySalesReport implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $start = Carbon::today()->startOfDay();
        $end = Carbon::today()->endOfDay();

        $sales = Sale::with(['product', 'user'])
            ->whereBetween('sold_at', [$start, $end])
            ->get();

        $adminAddress = config('mail.admin_address');

        if ($adminAddress) {
            Mail::to($adminAddress)->send(new DailySalesReportMail($sales, $start));
        }
    }
}
