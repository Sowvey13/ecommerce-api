<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();

        
        if (!$user || $user->role !== 'admin') {
            return $this->error('Bu işlem için yetkiniz bulunmuyor.', 403);
        }

        $query = User::query();

        
        if ($request->filled('role')) {
            $role = trim($request->string('role')->toString());
            $query->where('role', $role);
        }

        
        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $paginator = $query->latest()->paginate(15);

        return $this->success([
            'items' => UserResource::collection($paginator->items()),
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ], 'Kullanıcılar başarıyla listelendi.');
    }
}