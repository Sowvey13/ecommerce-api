<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ApiResponse;

    private const LIST_KEYS_CACHE_KEY = 'products:list:keys';

    public function index(Request $request)
    {
        $queryParams = $request->query();
        ksort($queryParams);
        $cacheKey = 'products:list:' . md5(json_encode($queryParams));

        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($request) {
            $query = Product::with('category');

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->integer('category_id'));
            }

            if ($request->filled('search')) {
                $search = trim($request->string('search')->toString());
                $query->where('name', 'like', '%' . $search . '%');
            }

            $paginator = $query->latest()->paginate(10);

            return [
                'items' => $paginator->items(),
                'meta'  => [
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                ],
            ];
        });

        $this->trackListCacheKey($cacheKey);

        return $this->success([
            'items' => ProductResource::collection($payload['items']),
            'meta'  => $payload['meta'],
        ], 'Ürünler başarıyla listelendi.');
    }

    public function show($id)
    {
        $product = Product::with('category')->find($id);

        if (!$product) {
            return $this->error('Ürün bulunamadı.', 404);
        }

        return $this->success(new ProductResource($product), 'Ürün başarıyla görüntülendi.');
    }

    public function store(StoreProductRequest $request)
    {
        $validatedData = $request->validated();

        $validatedData['slug'] = Str::slug($validatedData['name']) . '-' . Str::random(5);

        $product = Product::create($validatedData);

        $this->clearProductListCache();

        return $this->success(new ProductResource($product), 'Ürün başarıyla oluşturuldu.', 201);
    }

    public function update(UpdateProductRequest $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->error('Ürün bulunamadı.', 404);
        }

        $validatedData = $request->validated();

        if (isset($validatedData['name'])) {
            $validatedData['slug'] = Str::slug($validatedData['name']) . '-' . Str::random(5);
        }

        $product->update($validatedData);

        $this->clearProductListCache();

        return $this->success(new ProductResource($product->fresh()), 'Ürün başarıyla güncellendi.');
    }

    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->error('Ürün bulunamadı.', 404);
        }

        $product->delete();

        $this->clearProductListCache();

        return $this->success(null, 'Ürün başarıyla silindi.');
    }

    private function trackListCacheKey(string $key): void
    {
        $keys = Cache::get(self::LIST_KEYS_CACHE_KEY, []);

        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::put(self::LIST_KEYS_CACHE_KEY, $keys, now()->addHours(24));
        }
    }

    private function clearProductListCache(): void
    {
        $keys = Cache::get(self::LIST_KEYS_CACHE_KEY, []);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Cache::forget(self::LIST_KEYS_CACHE_KEY);
    }
}