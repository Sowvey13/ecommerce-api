<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartController extends Controller
{
    use ApiResponse;

    private function currentUserId(Request $request): ?int
    {
        return $request->user('sanctum')?->id;
    }

    private function findCart(Request $request, bool $createIfMissing = false): array
    {
        $userId = $this->currentUserId($request);

        if ($userId) {
            $cart = Cart::with('items.product')
                ->where('user_id', $userId)
                ->first();

            if (!$cart && $createIfMissing) {
                $cart = Cart::create([
                    'user_id'     => $userId,
                    'guest_token' => null,
                ]);
                $cart->load('items.product');
            }

            return [$cart, null];
        }

        $guestToken = $request->header('X-Cart-Token');

        if ($guestToken) {
            $cart = Cart::with('items.product')
                ->where('guest_token', $guestToken)
                ->first();

            if (!$cart && $createIfMissing) {
                $cart = Cart::create([
                    'user_id'     => null,
                    'guest_token' => $guestToken,
                ]);
                $cart->load('items.product');
            }

            return [$cart, $guestToken];
        }

        if ($createIfMissing) {
            $newToken = (string) Str::uuid();
            $cart = Cart::create([
                'user_id'     => null,
                'guest_token' => $newToken,
            ]);
            $cart->load('items.product');

            return [$cart, $newToken];
        }

        return [null, null];
    }

    public function index(Request $request)
    {
        [$cart, $guestToken] = $this->findCart($request, false);

        if (!$cart) {
            $payload = [
                'items' => [],
            ];

            if ($guestToken) {
                $payload['guest_token'] = $guestToken;
            }

            return $this->success($payload, 'Sepet boş.');
        }

        return $this->success(
            $this->cartPayload($cart, $guestToken ?? $cart->guest_token),
            'Sepet başarıyla görüntülendi.'
        );
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        [$cart, $token] = $this->findCart($request, true);

        try {
            DB::transaction(function () use ($cart, $validatedData) {
                $product = Product::lockForUpdate()->find($validatedData['product_id']);

                if (!$product || (isset($product->is_active) && !$product->is_active)) {
                    throw new \RuntimeException('Bu ürün şu anda satışta değil.');
                }

                $cartItem = CartItem::where('cart_id', $cart->id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                $newQuantity = ($cartItem ? $cartItem->quantity : 0) + $validatedData['quantity'];

                if ($product->stock < $newQuantity) {
                    throw new \RuntimeException(
                        "'{$product->name}' ürünü için yeterli stok bulunmuyor. (Mevcut stok: {$product->stock})"
                    );
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
            });
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->error('Ürün sepete eklenirken bir hata oluştu.', 500);
        }

        $cart->load('items.product');
        $activeToken = $token ?? $cart->guest_token;

        return $this->success(
            $this->cartPayload($cart, $activeToken),
            'Ürün sepete başarıyla eklendi.',
            201
        );
    }

    public function update(Request $request, $productId)
    {
        $validatedData = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        [$cart, $token] = $this->findCart($request, false);

        if (!$cart) {
            return $this->error('Sepet bulunamadı.', 404);
        }

        try {
            DB::transaction(function () use ($cart, $productId, $validatedData) {
                $cartItem = CartItem::where('cart_id', $cart->id)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();

                if (!$cartItem) {
                    throw new \DomainException('Sepette böyle bir ürün bulunamadı.');
                }

                $product = Product::lockForUpdate()->find($productId);

                if (!$product || (isset($product->is_active) && !$product->is_active)) {
                    throw new \RuntimeException('Bu ürün şu anda satışta değil.');
                }

                if ($product->stock < $validatedData['quantity']) {
                    throw new \RuntimeException(
                        "'{$product->name}' ürünü için yeterli stok bulunmuyor. (Mevcut stok: {$product->stock})"
                    );
                }

                $cartItem->update(['quantity' => $validatedData['quantity']]);
            });
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->error('Sepet güncellenirken bir hata oluştu.', 500);
        }

        $cart->load('items.product');

        return $this->success(
            $this->cartPayload($cart, $token ?? $cart->guest_token),
            'Sepet başarıyla güncellendi.'
        );
    }

    public function destroy(Request $request, $productId)
    {
        [$cart, $token] = $this->findCart($request, false);

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
        $cart->load('items.product');

        return $this->success(
            $this->cartPayload($cart, $token ?? $cart->guest_token),
            'Ürün sepetten başarıyla silindi.'
        );
    }

    private function cartPayload(Cart $cart, ?string $guestToken): array
    {
        $data = $cart->toArray();

        if ($guestToken) {
            $data['guest_token'] = $guestToken;
        }

        return $data;
    }

    public function merge(Request $request)
    {
        $userId = $this->currentUserId($request);

        if (!$userId) {
            return $this->error('Sepet birleştirmek için giriş yapmalısınız.', 401);
        }

        $validated = $request->validate([
            'guest_token' => 'nullable|string',
        ]);

        $guestToken = $request->header('X-Cart-Token') ?: ($validated['guest_token'] ?? null);

        if (!$guestToken) {
            return $this->error('Misafir sepet token\'ı bulunamadı.', 400);
        }

        $guestCart = Cart::with('items.product')
            ->where('guest_token', $guestToken)
            ->first();

        $userCart = Cart::with('items.product')
            ->where('user_id', $userId)
            ->first();

        if (!$guestCart || $guestCart->items->isEmpty()) {
            if ($guestCart) {
                $guestCart->delete();
            }

            if (!$userCart) {
                $userCart = Cart::create([
                    'user_id'     => $userId,
                    'guest_token' => null,
                ]);
            }

            $userCart->load('items.product');

            return $this->success(
                $this->cartPayload($userCart, null),
                'Birleştirilecek misafir sepeti bulunamadı.'
            );
        }

        if (!$userCart) {
            $guestCart->update([
                'user_id'     => $userId,
                'guest_token' => null,
            ]);

            $guestCart->load('items.product');

            return $this->success(
                $this->cartPayload($guestCart, null),
                'Misafir sepeti kullanıcı sepetine aktarıldı.'
            );
        }

        try {
            DB::transaction(function () use ($guestCart, $userCart) {
                foreach ($guestCart->items as $guestItem) {
                    $product = Product::lockForUpdate()->find($guestItem->product_id);

                    if (!$product || (isset($product->is_active) && !$product->is_active)) {
                        continue;
                    }

                    if ($product->stock < 1) {
                        continue;
                    }

                    $userItem = $userCart->items()
                        ->where('product_id', $guestItem->product_id)
                        ->lockForUpdate()
                        ->first();

                    $desiredQuantity = ($userItem ? $userItem->quantity : 0) + $guestItem->quantity;
                    $finalQuantity = min($desiredQuantity, $product->stock);

                    if ($userItem) {
                        $userItem->update(['quantity' => $finalQuantity]);
                    } else {
                        $userCart->items()->create([
                            'product_id' => $guestItem->product_id,
                            'quantity'   => $finalQuantity,
                        ]);
                    }
                }

                $guestCart->items()->delete();
                $guestCart->delete();
            });
        } catch (\Throwable $e) {
            return $this->error('Sepetler birleştirilirken bir hata oluştu.', 500);
        }

        $userCart->load('items.product');

        return $this->success(
            $this->cartPayload($userCart, null),
            'Sepetler başarıyla birleştirildi.'
        );
    }
}