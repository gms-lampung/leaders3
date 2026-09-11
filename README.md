# Knowledge Base Leaders 3 (GMS Lampung)

Tampilan baru Knowledge Base Leaders GMS Lampung dengan desain **modern & bersih** (tema teal, kartu konten besar, badge & ikon, mobile-first). Data diambil dari **sumber yang SAMA** dengan leaders2 — Upstash Redis (`gmsapp:leaders_content`). Edit konten di salah satu aplikasi langsung tampil di keduanya.

## Stack

- PHP 8.x, front-controller di `api/index.php`
- Upstash Redis via REST (dengan fallback file `data/` untuk dev lokal)
- Deploy Vercel (`vercel.json`: build `vercel-php@0.9.0`, aset statis dari `public/`)

## Skema konten

Satu skema di `lib/kb.php` — definisi field + parser keep-old + renderer generik. Admin (`pages/admin.php`) otomatis membuat form dari skema; section baru bisa ditambah lewat tombol **Tambah Section**. Data lama **tidak pernah** diubah bentuk (parser hanya menulis saat input tidak kosong).

Styling publik di `public/css/app.css` (markup semantik). Admin memakai `public/css/gms.css`.

## Struktur

```
api/index.php        router (/, /login, /logout, /admin, aset statis)
lib/store.php        akses Redis + fallback lokal (.env)
lib/auth.php         login admin / cookie CSRF
lib/ui.php           komponen UI publik
lib/kb.php           skema konten + renderer generik
pages/home.php       tampilan publik modern
pages/admin.php      editor konten (auto-form dari skema)
pages/login.php      login admin
public/              css/app.css, css/gms.css, icons, manifest, sw.js
seed/content.json    data awal
data/                mirror lokal saat dev
```

## Menjalankan lokal (Laragon)

1. Salin `.env.example` ke `.env` lalu isi (UPSTASH Redis + PIN admin).
2. Buka vhost menunjuk ke folder ini (`.htaccess` mengarahkan semua ke `index.php`).
   Atau: `php -S 127.0.0.1:8099 api/index.php`.

## Deploy Vercel

```bash
vercel
```

- SSH: usahakan commit `.env` **tidak** masuk repo (sudah di-ignore).
- Vercel membaca env dari dashboard (atau ambil dari `.env` lokal saat deploy awal).