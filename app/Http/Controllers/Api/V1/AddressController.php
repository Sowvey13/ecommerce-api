<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $userId = Auth::id();

        $addresses = Address::where('user_id', $userId)->latest()->get();

        if ($addresses->isEmpty()) {
            return $this->success([], 'Henüz kayıtlı bir adresiniz bulunmuyor.');
        }

        return $this->success(
            AddressResource::collection($addresses),
            'Adresler başarıyla listelendi.'
        );
    }

    public function store(StoreAddressRequest $request)
    {
        $validatedData = $request->validated();

        $userId = Auth::id();

        if (!empty($validatedData['is_default']) && $validatedData['is_default'] === true) {
            Address::where('user_id', $userId)->update(['is_default' => false]);
        } else {
            $hasAddress = Address::where('user_id', $userId)->exists();
            if (!$hasAddress) {
                $validatedData['is_default'] = true;
            }
        }

        $address = Address::create([
            'user_id' => $userId,
            ...$validatedData,
        ]);

        return $this->success(
            new AddressResource($address),
            'Adres başarıyla eklendi.',
            201
        );
    }

    public function show($id)
    {
        $userId = Auth::id();

        $address = Address::where('user_id', $userId)->where('id', $id)->first();

        if (!$address) {
            return $this->error('Adres bulunamadı.', 404);
        }

        return $this->success(
            new AddressResource($address),
            'Adres detayı getirildi.'
        );
    }

    public function update(UpdateAddressRequest $request, $id)
    {
        $userId = Auth::id();

        $address = Address::where('user_id', $userId)->where('id', $id)->first();

        if (!$address) {
            return $this->error('Adres bulunamadı.', 404);
        }

        $validatedData = $request->validated();

        if (!empty($validatedData['is_default']) && $validatedData['is_default'] === true) {
            Address::where('user_id', $userId)->update(['is_default' => false]);
        }

        $address->update($validatedData);

        return $this->success(
            new AddressResource($address->fresh()),
            'Adres başarıyla güncellendi.'
        );
    }

    public function destroy($id)
    {
        $userId = Auth::id();

        $address = Address::where('user_id', $userId)->where('id', $id)->first();

        if (!$address) {
            return $this->error('Adres bulunamadı.', 404);
        }

        $address->delete();

        return $this->success(null, 'Adres başarıyla silindi.');
    }
}