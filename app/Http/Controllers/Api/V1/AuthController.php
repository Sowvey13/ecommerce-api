<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'role' => 'customer',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token,
        ], 'Kayıt başarıyla oluşturuldu.', 201);
    }

    public function login(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($validatedData)) {
            return $this->error('Girdiğiniz bilgiler hatalı.', 401);
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token,
        ], 'Giriş başarıyla yapıldı.', 200);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return $this->success(null, 'Çıkış başarıyla yapıldı.');
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $plainToken = Str::random(64);

        DB::table('password_reset_token')->updateOrInsert(
            ['email' => $validated['email']],
            [
               'token' => Hash::make($plainToken),
               'created_at' => Carbon::now(),
            ]);

        Mail::raw(
            "Şifre sıfırlama kodunuz / token'ınız:\n\n{$plainToken}\n\n Bu kod 60 dakika geçerlidir.",
            function ($message) use ($validated) {
                $message->to($validated['email'])
                    ->subject('Şifre Sıfırlama Talebi');
            }
        );

        return $this->success(
            null,
            'Şifre sıfırlama bağlantısı/kodu e-posta adresinize gönderildi.'
        );
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$record || !Hash::check($validated['token'], $record->token)) {
            return $this->error('Geçersiz veya hatalı şifre sıfırlama kodu.', 400);
        }

        $isExpired = Carbon::parse($record->created_at)->addMinutes(60)->isPast();
        if ($isExpired) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            return $this->error('Şifre sıfırlama kodunun süresi dolmuş.', 400);
        }

        $user = User::where('email', $validated['email'])->firstOrFail();
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
        $user->tokens()->delete();

        return $this->success(null, 'Şifreniz başarıyla güncellendi. Lütfen tekrar giriş yapın.');
    }
}