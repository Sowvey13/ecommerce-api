<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Admin Kullanıcı',
                'password' => Hash::make('123456'),
                'role'     => 'admin',
            ]
        );

        $customer = User::updateOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name'     => 'Müşteri Kullanıcı',
                'password' => Hash::make('123456'),
                'role'     => 'customer',
            ]
        );

        Address::updateOrCreate(
            [
                'user_id' => $customer->id,
                'title'   => 'Ev',
            ],
            [
                'address_line' => 'Örnek Mah. Cumhuriyet Cad. No:10 Daire:2',
                'district'     => 'Kadıköy',
                'city'         => 'İstanbul',
                'postal_code'  => '34710',
                'is_default'   => true,
            ]
        );

        $electronics = Category::updateOrCreate(
            ['slug' => 'elektronik'],
            [
                'name'      => 'Elektronik',
                'parent_id' => null,
            ]
        );

        $phones = Category::updateOrCreate(
            ['slug' => 'telefon'],
            [
                'name'      => 'Telefon',
                'parent_id' => $electronics->id,
            ]
        );

        $books = Category::updateOrCreate(
            ['slug' => 'kitap'],
            [
                'name'      => 'Kitap',
                'parent_id' => null,
            ]
        );

        Product::updateOrCreate(
            ['slug' => 'akilli-telefon'],
            [
                'category_id' => $phones->id,
                'name'        => 'Akıllı Telefon',
                'description' => '128 GB depolama alanına sahip akıllı telefon.',
                'price'       => 18500.00,
                'stock'       => 15,
                'is_active'   => true,
            ]
        );

        Product::updateOrCreate(
            ['slug' => 'kablosuz-kulaklik'],
            [
                'category_id' => $electronics->id,
                'name'        => 'Kablosuz Kulaklık',
                'description' => 'Bluetooth bağlantılı kulak üstü kulaklık.',
                'price'       => 1250.00,
                'stock'       => 30,
                'is_active'   => true,
            ]
        );

        Product::updateOrCreate(
            ['slug' => 'klasik-roman'],
            [
                'category_id' => $books->id,
                'name'        => 'Klasik Roman',
                'description' => 'Ciltli baskı klasik edebiyat kitabı.',
                'price'       => 220.00,
                'stock'       => 50,
                'is_active'   => true,
            ]
        );
    }
}