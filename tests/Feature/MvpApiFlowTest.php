<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MvpApiFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_login_and_logout(): void
    {
        $registerRes = $this->postJson('/api/v1/register', [
            'name'     => 'Test Kullanıcı',
            'email'    => 'test@example.com',
            'password' => '123456',
        ]);

        $registerRes->assertStatus(201)
            ->assertJsonPath('status', true);

        $loginRes = $this->postJson('/api/v1/login', [
            'email'    => 'test@example.com',
            'password' => '123456',
        ]);

        $loginRes->assertStatus(200)
            ->assertJsonPath('status', true);

        $token = $loginRes->json('data.token');
        $this->assertNotEmpty($token);

        $logoutRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/logout');

        $logoutRes->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    public function test_guest_cart_merges_into_user_cart_on_login_and_respects_stock(): void
    {
        $category = Category::create([
            'name' => 'Elektronik',
            'slug' => 'elektronik-test',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kablosuz Mouse',
            'slug'        => 'kablosuz-mouse-test',
            'description' => 'Test ürün',
            'price'       => 500,
            'stock'       => 5,
        ]);

        $guestToken = 'guest-token-12345';

        $addRes = $this->withHeader('X-Cart-Token', $guestToken)
            ->postJson('/api/v1/cart', [
                'product_id' => $product->id,
                'quantity'   => 4,
            ]);

        $addRes->assertStatus(201)
            ->assertJsonPath('status', true);

        $user = User::factory()->create([
            'email' => 'alici@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity'   => 2,
        ])->assertStatus(201);

        $mergeRes = $this->postJson('/api/v1/cart/merge', [
            'guest_token' => $guestToken,
        ]);

        $mergeRes->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity'   => 5,
        ]);
    }

    public function test_order_decrements_stock_and_blocks_insufficient_stock(): void
    {
        $category = Category::create([
            'name' => 'Aksesuar',
            'slug' => 'aksesuar-test',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Mekanik Klavye',
            'slug'        => 'mekanik-klavye-test',
            'description' => 'Test klavye',
            'price'       => 1500,
            'stock'       => 3,
        ]);

        $user = User::factory()->create();
        $address = Address::create([
            'user_id'      => $user->id,
            'title'        => 'Ev',
            'address_line' => 'Örnek Mah. Test Sok. No:1',
            'district'     => 'Bakırköy',
            'city'         => 'İstanbul',
            'postal_code'  => '34140',
            'is_default'   => true,
        ]);

        Sanctum::actingAs($user);

        // 1) Yeterli stokla sipariş (3 stoktan 2 adet -> kalan stok 1)
        $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity'   => 2,
        ])->assertStatus(201);

        $orderRes = $this->postJson('/api/v1/orders', [
            'address_id' => $address->id,
        ]);

        $orderRes->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('products', [
            'id'    => $product->id,
            'stock' => 1,
        ]);

        // 2) Sepete 1 adet ekle, sonra sipariş anında stok yetersiz kalsın diye stoğu 0 yap
        $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity'   => 1,
        ])->assertStatus(201);

        $product->update(['stock' => 0]);

        $failOrderRes = $this->postJson('/api/v1/orders', [
            'address_id' => $address->id,
        ]);

        $failOrderRes->assertStatus(400)
            ->assertJsonPath('status', false);

        $this->assertDatabaseHas('products', [
            'id'    => $product->id,
            'stock' => 0,
        ]);
    }

    public function test_user_cannot_view_other_order_and_non_admin_cannot_update_status(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $otherUser = User::factory()->create(['role' => 'customer']);

        $address = Address::create([
            'user_id'      => $owner->id,
            'title'        => 'Ev',
            'address_line' => 'Test Cad. No:5',
            'district'     => 'Ümraniye',
            'city'         => 'İstanbul',
            'postal_code'  => '34760',
            'is_default'   => true,
        ]);

        $category = Category::create([
            'name' => 'Genel',
            'slug' => 'genel-test',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Kulaklık',
            'slug'        => 'kulaklik-test',
            'description' => 'Test kulaklık',
            'price'       => 800,
            'stock'       => 10,
        ]);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 1])->assertStatus(201);
        $created = $this->postJson('/api/v1/orders', ['address_id' => $address->id])->assertStatus(201);
        $orderId = $created->json('data.id');

        Sanctum::actingAs($otherUser);
        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertStatus(404);

        $this->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'shipped'])
            ->assertStatus(403);
    }
}