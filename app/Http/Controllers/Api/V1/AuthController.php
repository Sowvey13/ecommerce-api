<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name'     => $validatedData['name'],
            'email'    => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'role' => 'customer',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'user'  => $user,
            'token' => $token,
        ], 'Kayıt başarıyla oluşturuldu.', 201);
    }

    public function login(Request $request)
    {
        $validatedData = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($validatedData)) {
            return $this->error('Girdiğiniz bilgiler hatalı.', 401);
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'user'  => $user,
            'token' => $token,
        ], 'Giriş başarıyla yapıldı.', 200);
    }

    public function logout(Request $request){
        $token = $request->user()?->currentAccessToken();

        if($token){
            $token->delete();
        }

        return $this->success(null, 'Çıkış başarıyla yapıldı.');
    }
}