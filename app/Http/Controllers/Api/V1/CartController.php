<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $userId = Auth::id();

        $cart = Cart::with('items.product')
            ->where('user_id', $userId)
            ->first();

        if (!$cart) {
            return $this->error('Sepet bulunamadı.', 404);
        }

        return $this->success($cart, 'Sepet başarıyla görüntülendi.');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        $product = Product::find($validatedData['product_id']);

        if (!$product || (isset($product->is_active) && !$product->is_active)) {
            return $this->error('Bu ürün şu anda satışta değil.', 400);
        }

        $userId = Auth::id();
        $cart = Cart::firstOrCreate(['user_id' => $userId]);

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        $newQuantity = ($cartItem ? $cartItem->quantity : 0) + $validatedData['quantity'];

        if ($product->stock < $newQuantity) {
            return $this->error("'{$product->name}' ürünü için yeterli stok bulunmuyor. (Mevcut stok: {$product->stock})", 400);
        }

        if ($cartItem) {
            $cartItem->update(['quantity' => $newQuantity]);
        } else {
            CartItem::create([
                'cart_id'    => $cart->id,
                'product_id' => $product->id,
                'quantity'   => $validatedData['quantity'],
            ]);
        }

        return $this->success($cart->load('items.product'), 'Ürün sepete başarıyla eklendi.', 201);
    }

    public function update(Request $request, $productId)
    {
        $validatedData = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $userId = Auth::id();
        $cart = Cart::where('user_id', $userId)->first();

        if (!$cart) {
            return $this->error('Sepet bulunamadı.', 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->first();

        if (!$cartItem) {
            return $this->error('Sepette böyle bir ürün bulunamadı.', 404);
        }

        $product = Product::find($productId);

        if (!$product || (isset($product->is_active) && !$product->is_active)) {
            return $this->error('Bu ürün şu anda satışta değil.', 400);
        }

        if ($product->stock < $validatedData['quantity']) {
            return $this->error("'{$product->name}' ürünü için yeterli stok bulunmuyor. (Mevcut stok: {$product->stock})", 400);
        }

        $cartItem->update(['quantity' => $validatedData['quantity']]);

        return $this->success($cart->load('items.product'), 'Sepet başarıyla güncellendi.');
    }

    public function destroy($productId)
    {
        $userId = Auth::id();
        $cart = Cart::where('user_id', $userId)->first();

        if (!$cart) {
            return $this->error('Sepet bulunamadı.', 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->first();

        if (!$cartItem) {
            return $this->error('Sepette böyle bir ürün bulunamadı.', 404);
        }

        $cartItem->delete();

        return $this->success($cart->load('items.product'), 'Ürün sepetten başarıyla silindi.');
    }
}