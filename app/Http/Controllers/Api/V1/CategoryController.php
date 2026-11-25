<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    use ApiResponse;

    private const CACHE_KEY = 'categories:all';

    public function index(Request $request)
    {
        $categories = Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function () use ($request) {
            $models = Category::with('parent', 'children')->get();

            return CategoryResource::collection($models)->resolve($request);
        });

        return $this->success(
            $categories,
            'Kategoriler başarıyla listelendi.'
        );
    }

    public function show($id)
    {
        $category = Category::with('parent', 'children')->find($id);

        if (!$category) {
            return $this->error('Kategori bulunamadı.', 404);
        }

        return $this->success(
            new CategoryResource($category),
            'Kategori başarıyla görüntülendi.'
        );
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:categories,id',
        ]);

        $validatedData['slug'] = Str::slug($validatedData['name']) . '-' . Str::random(5);

        $category = Category::create($validatedData);

        Cache::forget(self::CACHE_KEY);

        return $this->success(
            new CategoryResource($category),
            'Kategori başarıyla oluşturuldu.',
            201
        );
    }

    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return $this->error('Kategori bulunamadı.', 404);
        }

        $descendantIds = $this->getDescendantIds((int) $id);

        $validatedData = $request->validate([
            'name'      => 'sometimes|required|string|max:255',
            'parent_id' => [
                'nullable',
                'sometimes',
                'integer',
                'exists:categories,id',
                Rule::notIn([(int) $id, ...$descendantIds]),
            ],
        ]);

        if (isset($validatedData['name'])) {
            $validatedData['slug'] = Str::slug($validatedData['name']) . '-' . Str::random(5);
        }

        $category->update($validatedData);

        Cache::forget(self::CACHE_KEY);

        return $this->success(
            new CategoryResource($category->fresh()),
            'Kategori başarıyla güncellendi.'
        );
    }

    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return $this->error('Kategori bulunamadı.', 404);
        }

        $category->delete();

        Cache::forget(self::CACHE_KEY);

        return $this->success(null, 'Kategori başarıyla silindi.');
    }

    private function getDescendantIds(int $categoryId): array
    {
        $ids = [];
        $queue = Category::where('parent_id', $categoryId)->pluck('id')->all();

        while (!empty($queue)) {
            $currentId = array_shift($queue);

            if (in_array($currentId, $ids, true)) {
                continue;
            }

            $ids[] = $currentId;

            $childIds = Category::where('parent_id', $currentId)->pluck('id')->all();
            foreach ($childIds as $childId) {
                if (!in_array($childId, $ids, true)) {
                    $queue[] = $childId;
                }
            }
        }

        return $ids;
    }
}