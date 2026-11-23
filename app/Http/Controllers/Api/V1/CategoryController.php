<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $categories = Category::with('parent', 'children', 'products')->get();

        return $this->success($categories, 'Kategoriler başarıyla listelendi.');
    }

    public function show($id)
    {
        $category = Category::with('parent', 'children', 'products')->find($id);

        if (!$category) {
            return $this->error('Kategori bulunamadı.', 404);
        }

        return $this->success($category, 'Kategori başarıyla görüntülendi.');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:categories,id',
        ]);

        $validatedData['slug'] = Str::slug($validatedData['name']) . '-' . Str::random(5);

        $category = Category::create($validatedData);

        return $this->success($category, 'Kategori başarıyla oluşturuldu.', 201);
    }

    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return $this->error('Kategori bulunamadı.', 404);
        }

        $validatedData = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'parent_id' => [
                'nullable',
                'sometimes',
                'integer',
                'exists:categories,id',
                Rule::notIn([$id]),
            ],
        ]);

        if (isset($validatedData['name'])) {
            $validatedData['slug'] = Str::slug($validatedData['name']) . '-' . Str::random(5);
        }

        $category->update($validatedData);

        return $this->success($category, 'Kategori başarıyla güncellendi.');
    }

    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return $this->error('Kategori bulunamadı.', 404);
        }

        $category->delete();

        return $this->success(null, 'Kategori başarıyla silindi.');
    }
}