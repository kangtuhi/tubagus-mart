# Tubagus Mart

Tubagus Mart adalah aplikasi **supermarket management & POS** yang dibangun sebagai fondasi bisnis tunggal (*single business*), bukan multi-cabang. Aplikasi ini dirancang untuk berkembang menjadi sistem operasional supermarket yang kompleks, terstruktur, aman, dan mudah dirawat.

## Project Foundation

- **Framework:** Laravel 13
- **PHP:** 8.5.9
- **Database:** MySQL
- **Architecture direction:** domain-oriented Laravel application
- **Business model:** satu entitas bisnis, tanpa konsep cabang
- **Authorization:** role & permission based access control (RBAC)
- **Testing:** Pest
- **Code style:** Laravel Pint

## Domain Direction

Tubagus Mart tidak diposisikan sebagai project Laravel generik. Laravel menjadi application framework, sedangkan domain bisnis menjadi pusat desain.

Fondasi domain dirancang untuk mencakup:

- Business Profile & Settings
- Identity, Authentication & Authorization
- Product & Catalog Management
- Inventory & Stock Control
- Purchasing & Suppliers
- Point of Sale & Sales Transactions
- Customers & Loyalty
- Promotions & Pricing
- Payments & Cash Management
- Reporting & Operational Analytics
- Audit Trail & System Administration

## Business Foundation

### Business Profile

`business_profiles` menyimpan identitas dan konfigurasi inti Tubagus Mart, termasuk nama bisnis, informasi kontak, alamat, timezone, mata uang, informasi pajak, dan logo.

Tubagus Mart saat ini **single-business / non-branch**. Karena itu, model domain tidak memperkenalkan `branches` atau `branch_id` sebagai bagian dari fondasi.

### Settings

`settings` menyediakan konfigurasi aplikasi berbasis key-value yang dapat dikelompokkan berdasarkan domain, misalnya:

- `business.*`
- `sales.*`
- `inventory.*`
- `system.*`

`App\Services\Business\BusinessSettings` menjadi service layer awal untuk membaca profile, membaca setting bertipe, mengambil settings per group, dan menyimpan setting secara konsisten.

## Current Phase

### Phase 1 — Foundation

- [x] Laravel application foundation
- [x] Authentication foundation
- [x] Role & Permission foundation
- [x] Authorization middleware & tests
- [x] **1.2 Business Foundation — Profile & Settings**
- [ ] Product foundation
- [ ] Inventory foundation
- [ ] Sales/POS foundation
- [ ] Supporting business domains

## Development Principles

1. **Business-first architecture** — desain mengikuti kebutuhan supermarket, bukan sekadar mengikuti struktur CRUD.
2. **Laravel conventions first** — gunakan konvensi Laravel 13 sebelum memperkenalkan abstraksi custom.
3. **Explicit domain boundaries** — domain penting memiliki model, service, policy, action, atau value object sesuai kebutuhan.
4. **Database integrity** — aturan penting ditegakkan sedekat mungkin dengan database dan domain.
5. **Test before expansion** — fondasi yang menjadi dependency domain lain harus memiliki test yang memadai.
6. **No premature multi-branch design** — Tubagus Mart adalah satu bisnis tanpa cabang; kompleksitas harus datang dari operasi bisnis, bukan dari fitur yang tidak diperlukan.
7. **Backward-safe evolution** — perubahan schema dan domain harus mempertimbangkan data yang sudah ada.

## Getting Started

Install dependency:

```bash
composer install
npm install
```

Siapkan environment dan database MySQL, lalu jalankan:

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
```

Untuk development:

```bash
composer run dev
```

Untuk menjalankan test:

```bash
php artisan test
```

Untuk memastikan coding style:

```bash
vendor/bin/pint
```

## Repository

Source code resmi project ini berada di:

https://github.com/kangtuhi/tubagus-mart

## License

Project ini menggunakan lisensi MIT.
