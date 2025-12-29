<?php

namespace App\Livewire;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Sale;
use Livewire\Component;
use Livewire\Attributes\On;

class ShoppingCart extends Component
{
    #[On('cart-updated')]
    public function render()
    {
        $cart = auth()->user()->cart()->with('items.product')->first();

        return view('livewire.shopping-cart', [
            'cart' => $cart,
        ]);
    }

    public function incrementQuantity($cartItemId)
    {
        $cartItem = CartItem::findOrFail($cartItemId);

        if (($cartItem->quantity + 1) > $cartItem->product->stock_quantity) {
            $this->dispatch('notify', message: 'Not enough stock.', type: 'error');
            return;
        }

        $cartItem->quantity += 1;
        $cartItem->save();

        $product = $cartItem->product;
        $product->stock_quantity -= 1;
        $product->save();

        $this->dispatch('cart-updated');
        $this->dispatch('notify', message: 'Quantity updated.', type: 'success');
    }

    public function decrementQuantity($cartItemId)
    {
        $cartItem = CartItem::findOrFail($cartItemId);

        if ($cartItem->quantity > 1) {
            $cartItem->quantity -= 1;
            $cartItem->save();

            $product = $cartItem->product;
            $product->stock_quantity += 1;
            $product->save();

            $this->dispatch('cart-updated');
            $this->dispatch('notify', message: 'Quantity updated.', type: 'success');
        } else {
            $this->removeItem($cartItemId);
        }
    }

    public function removeItem($cartItemId)
    {
        $cartItem = CartItem::findOrFail($cartItemId);

        $product = $cartItem->product;
        $product->stock_quantity += $cartItem->quantity;
        $product->save();

        $cartItem->delete();

        $this->dispatch('cart-updated');
        $this->dispatch('notify', message: 'Item removed from cart.', type: 'success');
    }

    public function checkout()
    {
        $cart = auth()->user()->cart()->with('items.product')->first();

        if (! $cart || $cart->items->isEmpty()) {
            $this->dispatch('notify', message: 'Your cart is empty.', type: 'error');
            return;
        }

        foreach ($cart->items as $item) {
            Sale::create([
                'user_id' => auth()->id(),
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'total_price' => $item->product->price * $item->quantity,
            ]);
        }

        $cart->items()->delete();
        $cart->delete();

        $this->dispatch('cart-updated');
        $this->dispatch('notify', message: 'Order placed successfully!', type: 'success');
    }
}
