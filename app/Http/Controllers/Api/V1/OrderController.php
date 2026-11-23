<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $userId = Auth::id();

        $orders = Order::with('items.product', 'address')
            ->where('user_id', $userId)
            ->latest()
            ->get();

        if ($orders->isEmpty()) {
            return $this->success([], 'Henüz bir siparişiniz bulunmuyor.');
        }

        return $this->success($orders, 'Siparişler başarıyla listelendi.');
    }

    public function show($id)
    {
        $userId = Auth::id();

        $order = Order::with('items.product', 'address')
            ->where('user_id', $userId)
            ->where('id', $id)
            ->first();

        if (!$order) {
            return $this->error('Sipariş bulunamadı.', 404);
        }

        return $this->success($order, 'Sipariş detayları başarıyla getirildi.');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'address_id' => 'required|exists:addresses,id',
        ]);

        $userId = Auth::id();

        $address = Address::where('user_id', $userId)
            ->where('id', $validatedData['address_id'])
            ->first();

        if (!$address) {
            return $this->error('Geçersiz adres seçimi.', 422);
        }

        $cart = Cart::with('items')
            ->where('user_id', $userId)
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return $this->error('Sepetiniz boş.', 400);
        }

        try {
            $order = DB::transaction(function () use ($cart, $userId, $address) {
                $totalAmount = 0;
                $orderItemsPayload = [];

                foreach ($cart->items as $item) {
                    $product = Product::lockForUpdate()->find($item->product_id);

                    if (!$product || (isset($product->is_active) && !$product->is_active)) {
                        throw new \RuntimeException("Sepetteki bir ürün artık satışta değil.");
                    }

                    if ($product->stock < $item->quantity) {
                        throw new \RuntimeException("'{$product->name}' ürünü için yeterli stok bulunmuyor.");
                    }

                    $totalAmount += $product->price * $item->quantity;

                    $orderItemsPayload[] = [
                        'product_id'   => $product->id,
                        'product_name' => $product->name,
                        'unit_price'   => $product->price,
                        'quantity'     => $item->quantity,
                    ];

                    $product->decrement('stock', $item->quantity);
                }

                $order = Order::create([
                    'user_id'      => $userId,
                    'address_id'   => $address->id,
                    'total_amount' => $totalAmount,
                    'status'       => 'pending',
                ]);

                $order->items()->createMany($orderItemsPayload);

                $cart->items()->delete();
                $cart->delete();

                return $order;
            });
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->success($order->load('items.product', 'address'), 'Sipariş başarıyla oluşturuldu.', 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $validatedData = $request->validate([
            'status' => 'required|string|in:pending,processing,shipped,delivered,cancelled',
        ]);

        $order = Order::with('items')
            ->where('id', $id)
            ->first();

        if (!$order) {
            return $this->error('Sipariş bulunamadı.', 404);
        }

        if (in_array($order->status, ['cancelled', 'delivered'], true)) {
            return $this->error("'{$order->status}' durumundaki bir siparişin durumu güncellenemez.", 400);
        }

        $newStatus = $validatedData['status'];

        try {
            DB::transaction(function () use ($order, $newStatus) {
                if ($newStatus === 'cancelled') {
                    foreach ($order->items as $item) {
                        $product = Product::lockForUpdate()->find($item->product_id);

                        if ($product) {
                            $product->increment('stock', $item->quantity);
                        }
                    }
                }

                $order->update([
                    'status' => $newStatus,
                ]);
            });
        } catch (\Throwable $e) {
            return $this->error('Sipariş durumu güncellenirken bir hata oluştu.', 500);
        }

        return $this->success(
            $order->load('items.product', 'address'),
            'Sipariş durumu başarıyla güncellendi.'
        );
    }

    public function cancel($id)
    {
        $userId = Auth::id();

        $order = Order::with('items')
            ->where('user_id', $userId)
            ->where('id', $id)
            ->first();

        if (!$order) {
            return $this->error('Sipariş bulunamadı.', 404);
        }

        if ($order->status === 'cancelled') {
            return $this->error('Bu sipariş zaten iptal edilmiş.', 400);
        }

        if ($order->status !== 'pending') {
            return $this->error('Yalnızca beklemedeki siparişler iptal edilebilir.', 400);
        }

        try {
            DB::transaction(function () use ($order) {
                foreach ($order->items as $item) {
                    $product = Product::lockForUpdate()->find($item->product_id);

                    if ($product) {
                        $product->increment('stock', $item->quantity);
                    }
                }

                $order->update([
                    'status' => 'cancelled',
                ]);
            });
        } catch (\Throwable $e) {
            return $this->error('Sipariş iptal edilirken bir hata oluştu.', 500);
        }

        return $this->success($order->fresh()->load('items.product', 'address'), 'Sipariş başarıyla iptal edildi ve stoklar iade edildi.');
    }

    public function pay(Request $request, $id)  
    {
        $validatedData = $request->validate([
            'payment_method' => 'nullable|string|in:card,bank_transfer,mock',
            'simulate'       => 'nullable|string|in:success,fail',
        ]);

        $userId = Auth::id();

        $order = Order::with('items.product', 'address')
            ->where('user_id', $userId)
            ->where('id', $id)
            ->first();

        if (!$order) {
            return $this->error('Sipariş bulunamadı.', 404);
        }

        if ($order->status === 'paid') {
            return $this->error('Bu siparişin ödemesi zaten tamamlanmış.', 400);
        }

        if ($order->status === 'cancelled') {
            return $this->error('İptal edilmiş bir sipariş için ödeme alınamaz.', 400);
        }

        if ($order->status !== 'pending') {
            return $this->error('Yalnızca beklemedeki siparişler için ödeme alınabilir.', 400);
        }

        $simulate = $validatedData['simulate'] ?? 'success';

        if ($simulate === 'fail') {
            return $this->error('Sahte ödeme işlemi başarısız oldu. Sipariş beklemede kaldı.', 400);
        }

        $order->update([
            'status' => 'paid',
        ]);

        return $this->success(
            $order->fresh()->load('items.product', 'address'), 'Ödeme başarıyla tamamlandı.');
    }
}