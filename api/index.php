<?php
/* ============================================================
 * api/index.php — Front controller Knowledge Base Leaders 3.
 *
 * Semua request (Vercel: rewrite; Apache lokal: .htaccess) masuk
 * lewat file ini lalu dipetakan ke modul di pages/.
 *
 * Route:
 *   /                     -> Knowledge Base Leaders (index / section)
 *   /login, /logout       -> Login/keluar admin
 *   /admin                -> Panel admin (edit konten dari skema kb.php)
 * ============================================================ */

require_once dirname(__DIR__) . '/lib/store.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/ui.php';

date_default_timezone_set(app_env('APP_TIMEZONE', 'Asia/Jakarta'));

/* Buffer output sampai akhir request agar setcookie()/header() yang
   dipanggil dari tengah halaman tidak gagal dengan "headers already sent". */
ob_start();

/* ------------------- Resolve path ------------------- */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = app_base_path();
if ($base !== '' && strpos($uri, $base) === 0) {
  $uri = substr($uri, strlen($base));
}
$path = trim(preg_replace('#^/index\.php$#', '/', $uri), '/');

/* --------- Penyajian aset statis untuk dev lokal --------- */
/* Di Vercel aset ini dilayani langsung dari folder public/
   lewat rewrite vercel.json sebelum sampai ke sini.          */
function serve_public_file(string $rel): bool {
  $map = [
    'css/gms.css'    => 'text/css; charset=utf-8',
    'css/app.css'    => 'text/css; charset=utf-8',
    'manifest.json'  => 'application/manifest+json',
    'sw.js'          => 'application/javascript; charset=utf-8',
  ];
  if (isset($map[$rel])) {
    $f = dirname(__DIR__) . '/public/' . $rel;
    if (is_file($f)) {
      header('Content-Type: ' . $map[$rel]);
      header('Cache-Control: no-cache');
      readfile($f);
      exit;
    }
  }
  if (strpos($rel, 'icons/') === 0 && preg_match('#^icons/[a-z0-9._-]+\.png$#', $rel)) {
    $f = dirname(__DIR__) . '/public/' . $rel;
    if (is_file($f)) {
      header('Content-Type: image/png');
      header('Cache-Control: public, max-age=86400');
      readfile($f);
      exit;
    }
  }
  return false;
}

if (in_array($path, ['css/gms.css', 'css/app.css', 'manifest.json', 'sw.js'], true) || strpos($path, 'icons/') === 0) {
  serve_public_file($path);
}

/* ------------------- Security headers ------------------- */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* ------------------- Router ------------------- */

switch ($path) {
  case '':
  case 'home':
    require dirname(__DIR__) . '/pages/home.php';
    break;

  case 'login':
    require dirname(__DIR__) . '/pages/login.php';
    break;

  case 'logout':
    admin_logout();
    app_redirect('');
    break;

  case 'admin':
    require dirname(__DIR__) . '/pages/admin.php';
    break;

  default:
    http_response_code(404);
    gms_head('Halaman Tidak Ditemukan');
    gms_navbar('fa-face-frown', 'Knowledge Base Leaders', 'Halaman tidak ditemukan');
    ?>
    <main class="mx-auto w-full max-w-[720px] px-4">
      <div class="section-title-wrap">
        <h1 class="page-title">404</h1>
        <div class="blue-indicator"></div>
      </div>
      <div class="card-gms text-center py-10">
        <div class="w-16 h-16 rounded-full bg-[#ccfbf1] text-[#0e7c66] flex items-center justify-center mx-auto mb-3 text-2xl"><i class="fa-solid fa-map-signs"></i></div>
        <p class="text-sm text-[#5e6d82] m-0 mb-4">Halaman yang Anda cari tidak tersedia.</p>
        <a href="<?php echo app_url(''); ?>" class="btn-gms-pill"><i class="fa-solid fa-house mr-1"></i> Kembali ke Beranda</a>
      </div>
    </main>
    <?php
    gms_bottom_nav('home');
    gms_service_worker();
    ?></body></html><?php
}