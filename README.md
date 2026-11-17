# ksmdesa

KSM Desa — platform multi-tenant: master di `ksmdesa.my.id`, satu KSM per desa di
subdomain (`<slug>.ksmdesa.my.id`). Laravel 13 + Filament 5, PHP 8.4.

## Lokal

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
composer run dev   # http://localhost:8000, tenant: http://<slug>.localhost:8000
```

`*.localhost` resolve ke 127.0.0.1 tanpa setup, jadi subdomain tenant bisa diuji lokal.

## Produksi

DirectAdmin `103.126.232.5`, akun `ksmdesa`, app di `~/ksmdesa`, `public_html` → `~/ksmdesa/public`.
Deploy manual: `~/deploy/deploy.sh` (dijalankan sebagai user `ksmdesa`).
