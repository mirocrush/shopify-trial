<div>
    @if($statusMessage)
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show"
             class="mb-4 rounded border p-3 {{ $statusType === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' }}">
            {{ $statusMessage }}
        </div>
    @endif
    <div class="flex items-center justify-end mb-4">
        <div>
            <button
                wire:click="sendEmails"
                wire:loading.attr="disabled"
                wire:target="sendEmails"
                class="group relative inline-flex items-center justify-center gap-3 px-6 py-3 rounded-lg text-base font-semibold text-indigo-700 bg-white border border-indigo-200 shadow-sm hover:bg-indigo-50 hover:border-indigo-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed w-64"
            >
                <!-- Static label to maintain width -->
                <span class="inline-flex items-center gap-2">
                    <svg class="h-5 w-5 opacity-90 group-hover:opacity-100" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 7.5v9a2.25 2.25 0 01-2.25 2.25h-15A2.25 2.25 0 012.25 16.5v-9A2.25 2.25 0 014.5 5.25h15A2.25 2.25 0 0121.75 7.5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 8.25l7.2 5.4a2.25 2.25 0 002.7 0l7.2-5.4" />
                    </svg>
                    <span wire:loading.remove wire:target="sendEmails">Send Email to Manager</span>
                    <span wire:loading wire:target="sendEmails">Sending...</span>
                </span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($products as $product)
           
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden shadow-sm hover:shadow-lg transition">
                <!-- Product Image -->
                <img src="{{ asset($product->image_url ?? 'assets/product/prod_images/placeholder.svg') }}" alt="{{ $product->name }}" class="w-full h-40 object-cover">

                <!-- Product Details -->
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ $product->name }}</h3>
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mb-3">${{ number_format($product->price, 2) }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Stock: <span class="font-semibold">{{ $product->stock_quantity }}</span>
                        @if($product->stock_quantity <= $product->low_stock_threshold)
                            <span class="text-red-500 font-medium">(Low Stock!)</span>
                        @endif
                    </p>

                    <button
                        wire:click="addToCart({{ $product->id }})"
                        @if($product->stock_quantity < 1) disabled @endif
                        class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium py-2 px-4 rounded transition duration-200"
                    >
                        @if($product->stock_quantity < 1)
                            Out of Stock
                        @else
                            Add to Cart
                        @endif
                    </button>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center text-gray-500 dark:text-gray-400 py-12">
                <p class="text-lg">No products available.</p>
            </div>
        @endforelse
    </div>
</div>
