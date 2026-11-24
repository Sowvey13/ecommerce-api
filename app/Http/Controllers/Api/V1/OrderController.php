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
            'address_id' => 'required|integer|exists:addresses,id',
        ]);

        $userId = Auth::id();

        $address = Address::where('user_id', $userId)
            ->where('id', $validatedData['address_id'])
            ->first();

        if (!$address) {
            return $this->error('Geçersiz adres seçimi.', 422);
        }

        try {
            $order = DB::transaction(function () use ($userId, $address) {
                $cart = Cart::with('items')
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if (!$cart || $cart->items->isEmpty()) {
                    throw new \DomainException('Sepetiniz boş.');
                }

                $totalAmount = 0.0;
                $orderItemsPayload = [];

                foreach ($cart->items as $item) {
                    $product = Product::lockForUpdate()->find($item->product_id);

                    if (!$product || (isset($product->is_active) && !$product->is_active)) {
                        throw new \RuntimeException('Sepetteki bir ürün artık satışta değil.');
                    }

                    if ($product->stock < $item->quantity) {
                        throw new \RuntimeException(
                            "'{$product->name}' ürünü için yeterli stok bulunmuyor."
                        );
                    }

                    $unitPrice = (float) $product->price;
                    $lineTotal = round($unitPrice * $item->quantity, 2);
                    $totalAmount = round($totalAmount + $lineTotal, 2);

                    $orderItemsPayload[] = [
                        'product_id'   => $product->id,
                        'product_name' => $product->name,
                        'unit_price'   => $unitPrice,
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
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->error('Sipariş oluşturulurken bir hata oluştu.', 500);
        }

        return $this->success(
            $order->load('items.product', 'address'),
            'Sipariş başarıyla oluşturuldu.',
            201
        );
    }

    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return $this->error('Bu işlem için yetkiniz bulunmuyor.', 403);
        }

        $validatedData = $request->validate([
            'status' => 'required|string|in:pending,processing,shipped,delivered,cancelled',
        ]);

        try {
            $updatedOrder = DB::transaction(function () use ($id, $validatedData) {
                $order = Order::with('items')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    throw new \DomainException('Sipariş bulunamadı.');
                }

                if (in_array($order->status, ['cancelled', 'delivered'], true)) {
                    throw new \RuntimeException(
                        "'{$order->status}' durumundaki bir siparişin durumu güncellenemez."
                    );
                }

                $newStatus = $validatedData['status'];

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

                return $order;
            });
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->error('Sipariş durumu güncellenirken bir hata oluştu.', 500);
        }

        return $this->success(
            $updatedOrder->load('items.product', 'address'),
            'Sipariş durumu başarıyla güncellendi.'
        );
    }

    public function cancel($id)
    {
        $userId = Auth::id();

        try {
            $cancelledOrder = DB::transaction(function () use ($id, $userId) {
                $order = Order::with('items')
                    ->where('user_id', $userId)
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    throw new \DomainException('Sipariş bulunamadı.');
                }

                if ($order->status === 'cancelled') {
                    throw new \RuntimeException('Bu sipariş zaten iptal edilmiş.');
                }

                if ($order->status !== 'pending') {
                    throw new \RuntimeException('Yalnızca beklemedeki siparişler iptal edilebilir.');
                }

                foreach ($order->items as $item) {
                    $product = Product::lockForUpdate()->find($item->product_id);

                    if ($product) {
                        $product->increment('stock', $item->quantity);
                    }
                }

                $order->update([
                    'status' => 'cancelled',
                ]);

                return $order;
            });
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->error('Sipariş iptal edilirken bir hata oluştu.', 500);
        }

        return $this->success(
            $cancelledOrder->fresh()->load('items.product', 'address'),
            'Sipariş başarıyla iptal edildi ve stoklar iade edildi.'
        );
    }

    public function pay(Request $request, $id)
    {
        $validatedData = $request->validate([
            'payment_method' => 'nullable|string|in:card,bank_transfer,mock',
            'simulate'       => 'nullable|string|in:success,fail',
        ]);

        $userId = Auth::id();
        $simulate = $validatedData['simulate'] ?? 'success';

        try {
            $paidOrder = DB::transaction(function () use ($id, $userId, $simulate) {
                $order = Order::with('items.product', 'address')
                    ->where('user_id', $userId)
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    throw new \DomainException('Sipariş bulunamadı.');
                }

                if ($order->status === 'paid') {
                    throw new \RuntimeException('Bu siparişin ödemesi zaten tamamlanmış.');
                }

                if ($order->status === 'cancelled') {
                    throw new \RuntimeException('İptal edilmiş bir sipariş için ödeme alınamaz.');
                }

                if ($order->status !== 'pending') {
                    throw new \RuntimeException('Yalnızca beklemedeki siparişler için ödeme alınabilir.');
                }

                if ($simulate === 'fail') {
                    throw new \RuntimeException(
                        'Sahte ödeme işlemi başarısız oldu. Sipariş beklemede kaldı.'
                    );
                }

                $order->update([
                    'status' => 'paid',
                ]);

                return $order;
            });
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->error('Ödeme işlemi sırasında bir hata oluştu.', 500);
        }

        return $this->success(
            $paidOrder->fresh()->load('items.product', 'address'),
            'Ödeme başarıyla tamamlandı.'
        );
    }
}