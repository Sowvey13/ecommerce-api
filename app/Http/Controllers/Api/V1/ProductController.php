<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
{
    $query = Product::with('category');

    if ($request->filled('category_id')) {
        $query->where('category_id', $request->integer('category_id'));
    }

    if ($request->filled('search')) {
        $search = trim($request->string('search')->toString());
        $query->where('name', 'like', '%' . $search . '%');
    }

    $products = $query->latest()->paginate(10);

    return $this->success($products, 'Ürünler başarıyla listelendi.');
}

    public function show($id)
    {
        $product = Product::with('category')->find($id);

        if (!$product) {
            return $this->error('Ürün bulunamadı.', 404);
        }

        return $this->success($product, 'Ürün başarıyla görüntülendi.');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
        ]);

        $validatedData['slug'] = Str::slug($validatedData['name']) . '-' . Str::random(5);

        $product = Product::create($validatedData);

        return $this->success($product, 'Ürün başarıyla oluşturuldu.', 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->error('Ürün bulunamadı.', 404);
        }

        $validatedData = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name'        => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'price'       => 'sometimes|numeric|min:0',
            'stock'       => 'sometimes|integer|min:0',
        ]);

        if (isset($validatedData['name'])) {
            $validatedData['slug'] = Str::slug($validatedData['name']) . '-' . Str::random(5);
        }

        $product->update($validatedData);

        return $this->success($product, 'Ürün başarıyla güncellendi.');
    }

    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->error('Ürün bulunamadı.', 404);
        }

        $product->delete();

        return $this->success(null, 'Ürün başarıyla silindi.');
    }
}