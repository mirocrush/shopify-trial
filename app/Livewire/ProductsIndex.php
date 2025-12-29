<?php

namespace App\Livewire;

use App\Jobs\NotifyLowStock;
use App\Mail\AdminDailySummaryMail;
use App\Mail\DailySalesReportMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\Attributes\On;


class ProductsIndex extends Component
{
    public ?string $statusMessage = null;
    public string $statusType = 'success';
    #[On('cart-updated')]
    public function render()
    {
        $products = Product::orderBy('name')->get();

        return view('livewire.products-index', [
            'products' => $products,
        ]);
    }

    public function addToCart($productId)
    {
        $product = Product::findOrFail($productId);

        if ($product->stock_quantity < 1) {
            $this->dispatch('notify', message: 'Product is out of stock.', type: 'error');
            return;
        }

        $cart = Cart::firstOrCreate(['user_id' => auth()->id()]);

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->first();

        if ($cartItem) {
            if (($cartItem->quantity + 1) > $product->stock_quantity) {
                $this->dispatch('notify', message: 'Not enough stock available.', type: 'error');
                return;
            }
            $cartItem->quantity += 1;
            $cartItem->save();
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'quantity' => 1,
            ]);
        }

        $wasAboveThreshold = $product->stock_quantity > $product->low_stock_threshold;
        $product->stock_quantity -= 1;
        $product->save();

        // Only send low stock notification the FIRST TIME stock goes below threshold
        if ($wasAboveThreshold && $product->stock_quantity <= $product->low_stock_threshold && !$product->low_stock_notified) {
            $product->update(['low_stock_notified' => true]);
            // Queue immediately; requires non-`sync` queue driver and a worker
            NotifyLowStock::dispatch($product->id);
        }

        // Reset notification flag if stock goes back above threshold
        if ($product->stock_quantity > $product->low_stock_threshold && $product->low_stock_notified) {
            $product->update(['low_stock_notified' => false]);
        }

        $this->dispatch('cart-updated');
        $this->dispatch('notify', message: 'Added to cart!', type: 'success');
    }

    /**
     * Trigger test emails (low stock + daily sales report)
     */
    public function testEmails(): void
    {
        $recipient = 'rafael2star@gmail.com';

        // Send low stock email for every low-stock product
        $lowStockProducts = Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')->get();
        $lowStockCount = $lowStockProducts->count();

        foreach ($lowStockProducts as $product) {
            Mail::to($recipient)->send(new LowStockMail($product));
        }

        // If no low stock products, at least send one sample
        if ($lowStockCount === 0) {
            $fallback = Product::first();
            if ($fallback) {
                Mail::to($recipient)->send(new LowStockMail($fallback));
            }
        }

        $start = Carbon::today()->startOfDay();
        $end = Carbon::today()->endOfDay();

        // Fetch all sales made today (all users)
        $sales = Sale::with(['product', 'user'])
            ->whereBetween('sold_at', [$start, $end])
            ->get();

        Mail::to($recipient)->send(new DailySalesReportMail($sales, $start));

        $this->dispatch('notify', message: 'Test emails sent to ' . $recipient . ' (low stock: ' . max($lowStockCount, 1) . ' email(s), sales: ' . $sales->count() . ' records)', type: 'success');
    }

    /**
     * Combined action: send a simple email + low stock + daily sales report
     * to the admin user's inbox.
     */
    public function sendEmails(): void
    {
        // Resolve admin recipient from env and validate
        $recipient = env('MAIL_ADMIN_ADDRESS');
        if (! is_string($recipient)) {
            $recipient = '';
        }
        $recipient = trim($recipient);
        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->statusType = 'error';
            $this->statusMessage = 'Admin email not configured. Please set MAIL_ADMIN_ADDRESS to a valid email in .env.';
            return;
        }

        // Preflight mailer configuration validation
        $mailer = config('mail.default');
        if ($mailer === 'log') {
            $this->statusType = 'error';
            $this->statusMessage = 'Current mailer is "log". Emails are written to storage/logs/laravel.log and will not be delivered. Set MAIL_MAILER=smtp with real credentials to send to inbox.';
            return;
        }
        $mailConfig = config('mail.mailers.' . $mailer);
        if (empty($mailer) || empty($mailConfig)) {
            $this->statusType = 'error';
            $this->statusMessage = 'Mailer not configured. Ensure MAIL_MAILER is set and matches a configured mailer in config/mail.php.';
            return;
        }
        if ($mailer === 'smtp') {
            $host = $mailConfig['host'] ?? env('MAIL_HOST');
            $port = $mailConfig['port'] ?? env('MAIL_PORT');
            $username = env('MAIL_USERNAME');
            $password = env('MAIL_PASSWORD');
            if (empty($host) || empty($port) || empty($username) || empty($password)) {
                $this->statusType = 'error';
                $this->statusMessage = 'SMTP settings incomplete. Please set MAIL_HOST, MAIL_PORT, MAIL_USERNAME and MAIL_PASSWORD in .env.';
                return;
            }
        }
        $from = config('mail.from.address') ?? env('MAIL_FROM_ADDRESS');
        if (! $from || ! filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $this->statusType = 'error';
            $this->statusMessage = 'MAIL_FROM_ADDRESS is missing or invalid in .env.';
            return;
        }

        // Low stock products
        $lowStockProducts = Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')->orderBy('name')->get();
        $lowStockCount = $lowStockProducts->count();

        // Daily sales report for today
        $start = Carbon::today()->startOfDay();
        $end = Carbon::today()->endOfDay();
        $sales = Sale::with(['product', 'user'])
            ->whereBetween('sold_at', [$start, $end])
            ->get();

        // Send single combined email: sales + low stock
        try {
            Mail::to($recipient)->send(new AdminDailySummaryMail($sales, $start, $lowStockProducts));
        } catch (\Throwable $e) {
            $this->statusType = 'error';
            $this->statusMessage = 'Email could not be sent: ' . $e->getMessage();
            return;
        }

        $this->statusType = 'success';
        $this->statusMessage = 'Email sent to ' . $recipient . ' (sales: ' . $sales->count() . ' records, low stock: ' . $lowStockCount . ' products)';
    }
}
