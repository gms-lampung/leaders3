<?php
/* ============================================================
 * ui.php — Komponen UI Knowledge Base Leaders 3.
 * Tema publik: "modern & bersih" (lihat public/css/app.css).
 * Admin tetap memakai Design System GMS (gms.css) sebagai alat.
 * ============================================================ */

require_once __DIR__ . '/store.php';
require_once __DIR__ . '/auth.php';

/** Base path aplikasi (kosong di Vercel/Laragon vhost; set APP_BASE_PATH bila di-subfolder). */
function app_base_path(): string {
  return rtrim(app_env('APP_BASE_PATH', ''), '/');
}

function app_url(string $path = ''): string {
  return app_base_path() . '/' . ltrim($path, '/');
}

function app_redirect(string $path): void {
  header('Location: ' . app_url($path));
  exit;
}

/** <head> standar (dipakai halaman 404). Halaman publik lain memakai tampilannya sendiri. */
function gms_head(string $title, array $opts = []): void {
  $extra = isset($opts['extra_head']) ? $opts['extra_head'] : '';
  ?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <meta name="theme-color" content="#0e7c66" />
  <title><?php echo htmlspecialchars($title); ?></title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/css/app.css" />
  <link rel="manifest" href="/manifest.json" />
  <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png" />
  <link rel="apple-touch-icon" href="/icons/icon-192.png" />
  <script>
    try { if (localStorage.getItem('leaders3_dark')) document.documentElement.style.colorScheme = 'dark'; } catch (e) {}
  </script>
  <?php echo $extra; ?>
</head>
<body>
<?php
}

/** Bilah atas sederhana (tidak dipakai halaman publik utama yang punya topbar sendiri). */
function gms_navbar(string $icon, string $title, string $subtitle): void {
  ?>
  <header class="topbar">
    <div class="wrap topbar-inner">
      <a class="brand" href="<?php echo app_url(''); ?>">
        <span class="brand-badge"><i class="<?php echo htmlspecialchars($icon); ?>"></i></span>
        <span class="brand-text">
          <span class="brand-name"><?php echo htmlspecialchars($title); ?></span>
          <span class="brand-sub"><?php echo htmlspecialchars($subtitle); ?></span>
        </span>
      </a>
      <div class="topbar-actions">
        <?php if (is_admin()): ?>
          <a class="chip chip-admin" href="<?php echo app_url('admin'); ?>"><i class="fa-solid fa-user-shield"></i> Panel</a>
          <a class="chip chip-ghost" href="<?php echo app_url('logout'); ?>" title="Keluar"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
        <?php else: ?>
          <a class="chip chip-ghost" href="<?php echo app_url('login'); ?>"><i class="fa-solid fa-user-shield"></i> Admin</a>
        <?php endif; ?>
      </div>
    </div>
  </header>
<?php
}

/**
 * Bottom navigation ala aplikasi native (Beranda + Admin untuk admin).
 * $active: 'home' | 'admin'
 */
function gms_bottom_nav(string $active = 'home'): void {
  $admin = is_admin();
  ?>
  <nav class="tabbar" role="navigation" aria-label="Navigasi bawah">
    <span class="tabbar-inner">
      <a class="tab-item <?php echo $active === 'home' ? 'is-active' : ''; ?>" href="<?php echo app_url(''); ?>">
        <i class="fa-solid fa-house"></i><span>Beranda</span>
      </a>
      <?php if ($admin): ?>
      <a class="tab-item <?php echo $active === 'admin' ? 'is-active' : ''; ?>" href="<?php echo app_url('admin'); ?>">
        <i class="fa-solid fa-user-gear"></i><span>Panel</span>
      </a>
      <?php endif; ?>
    </span>
  </nav>
<?php
}

function gms_footer(): void {
  $c = get_leaders_content();
  $site = isset($c['site']) ? $c['site'] : [];
  $footer_text = isset($site['footer_text']) ? (string)$site['footer_text'] : '';
  ?>
  <footer class="footer">
    <?php if ($footer_text !== ''): ?>
      <p class="footer-note"><?php echo htmlspecialchars($footer_text); ?></p>
    <?php endif; ?>
    <p class="footer-copy"><?php echo htmlspecialchars(isset($site['copyright']) && $site['copyright'] !== '' ? $site['copyright'] : 'GMS Lampung'); ?> &middot; Knowledge Base Leaders</p>
  </footer>
<?php
}

function gms_service_worker(): void {
  ?>
  <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js').catch(function () {});
    }
  </script>
<?php
}