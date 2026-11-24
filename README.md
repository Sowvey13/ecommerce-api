# E-Commerce MVP REST API

Laravel tabanlı e-ticaret MVP backend projesi. Kimlik doğrulama, kullanıcı adres yönetimi, hiyerarşik kategori ve ürün yönetimi, misafir ve üye sepeti birleştirme, stok korumalı sipariş/ödeme/iptal akışları ile temel admin işlemlerini sunar.

## Özellikler

- **Kimlik Doğrulama (Sanctum):** Kayıt (`register`), giriş (`login`), çıkış (`logout`), şifre sıfırlama (`forgot-password`, `reset-password`) ve profil güncelleme.
- **Adres Yönetimi:** Kullanıcıya özel çoklu adres ekleme, listeleme, görüntüleme, güncelleme ve silme.
- **Kategori ve Ürün Yönetimi:** Hiyerarşik (`parent_id`) kategori yapısı, döngüsel kategori atama koruması, ürün CRUD, arama, filtreleme, sayfalama ve liste endpoint'lerinde önbellekleme (`Cache`).
- **Sepet Sistemi:** Misafir sepeti (`cart_token` / header) desteği ve giriş sonrasında üye sepetiyle birleştirme + stok limiti kontrolü.
- **Sipariş, Ödeme ve İptal:** Transaction içinde stok düşümü, sipariş kalemi snapshot kaydı, sahte ödeme (`pay`), sipariş iptali (`cancel`) ve stok iadesi.
- **Admin İşlemleri:** Admin rolü ile kullanıcı listeleme ve sipariş durumu güncelleme.

## Kurulum (Docker / Laravel Sail)

1. Repoyu klonlayın ve proje dizinine girin:

git clone <repo-url>
cd e-commerce-api

2. Ortam dosyasını oluşturun:

cp .env.example .env

3. Bağımlılıkları kurun ve Sail ile konteynerleri başlatın:

composer install
sail up -d

4. Uygulama anahtarını üretin, migration ve seeder komutlarını çalıştırın:

sail artisan key:generate
sail artisan migrate --seed

## Örnek Seed Kullanıcıları

Varsayılan seed verileriyle oluşturulan hesaplar:

- **Admin:** `admin@example.com` / Şifre: `123456`
- **Müşteri:** `customer@example.com` / Şifre: `123456`

## Testleri Çalıştırma

Otomatik feature ve unit testleri çalıştırmak için:

sail artisan test

## API Yanıt Formatı

Başarılı yanıtlar:

{
  "status": true,
  "message": "İşlem başarılı.",
  "data": {}
}

Hata ve doğrulama yanıtları:

{
  "status": false,
  "message": "Validation error",
  "errors": {
    "field": [
      "Hata mesajı"
    ]
  }
}

