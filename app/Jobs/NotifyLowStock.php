<?php

namespace App\Jobs;

use App\Mail\LowStockSummaryMail;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * NotifyLowStock - Alert when stock gets low
 *
 * Fires whenever a product's stock hits the low_stock_threshold.
 * Dispatched from ProductsIndex when stock quantity <= threshold and runs asynchronously on the queue.
 */
class NotifyLowStock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $productId)
    {
        // Only the id is stored for safe serialization.
    }

    public function handle(): void
    {
        $product = Product::find($this->productId);

        if (! $product) {
            return;
        }

        // Get admin email from .env
        $adminAddress = env('MAIL_ADMIN_ADDRESS');
        if (! is_string($adminAddress) || ! filter_var($adminAddress, FILTER_VALIDATE_EMAIL)) {
            Log::warning('NotifyLowStock: Invalid or missing MAIL_ADMIN_ADDRESS in .env');
            return;
        }

        $lowStockProducts = Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('name')
            ->get();

        try {
            Mail::to($adminAddress)->send(new LowStockSummaryMail($product, $lowStockProducts));
            Log::info("Low stock email sent to {$adminAddress} for product: {$product->name}");
        } catch (\Exception $e) {
            Log::error("Failed to send low stock email: {$e->getMessage()}");
        }
    }
}
