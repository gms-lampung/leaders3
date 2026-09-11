<?php
/* ============================================================
 * login.php — Login admin gabungan (satu PIN untuk seluruh app).
 * ============================================================ */

if (is_admin()) {
  app_redirect('admin');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok()) {
    $error = 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.';
  } else {
    $pin = isset($_POST['admin_pin']) ? (string)$_POST['admin_pin'] : '';
    if (app_env('APP_ADMIN_PIN') === '') {
      $error = 'PIN admin belum dikonfigurasi di server (env APP_ADMIN_PIN).';
    } else {
      $error = admin_login($pin);
      if ($error === '') {
        app_redirect(isset($_GET['next']) && $_GET['next'] === 'admin' ? 'admin' : 'admin');
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <meta name="theme-color" content="#0052cc" />
  <title>Login Admin | GMS Lampung</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/css/gms.css" />
  <link rel="manifest" href="/manifest.json" />
  <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png" />
  <link rel="apple-touch-icon" href="/icons/icon-192.png" />
</head>
<body>
  <nav class="gms-gradient text-white py-3 shadow-[0_2px_10px_rgba(0,43,130,0.15)]">
    <div class="mx-auto w-full max-w-[960px] px-3 flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center"><i class="fa-solid fa-user-shield"></i></div>
      <div>
        <div class="font-extrabold text-sm tracking-wide">LOGIN ADMIN</div>
        <div class="text-[0.7rem] text-white/75">GMS Lampung</div>
      </div>
    </div>
  </nav>

  <main class="mx-auto w-full max-w-[400px] px-3">
    <div class="section-title-wrap">
      <h1 class="page-title"><i class="fa-solid fa-lock mr-1 text-[#0052cc]"></i>Akses Admin</h1>
      <div class="blue-indicator"></div>
    </div>

    <div class="card-gms">
      <?php if ($error): ?>
        <div class="mb-3 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[0.85rem] font-semibold text-red-600">
          <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="post" class="grid grid-cols-1 gap-3">
        <?php echo csrf_field(); ?>
        <div>
          <label class="label-gms"><i class="fa-solid fa-key mr-1 text-[#0052cc]"></i>PIN Admin</label>
          <input type="password" class="input-gms" name="admin_pin" placeholder="Masukkan PIN admin" required autofocus autocomplete="off" inputmode="numeric" />
        </div>
        <div class="pt-2">
          <button type="submit" class="btn-gms-pill w-full justify-center"><i class="fa-solid fa-right-to-bracket"></i> Masuk</button>
        </div>
      </form>

      <p class="text-center text-xs text-[#5e6d82] m-0 mt-3">
        <a href="<?php echo app_url(''); ?>" class="text-[#0052cc] font-semibold no-underline">Kembali ke Beranda</a>
      </p>
    </div>
  </main>
</body>
</html>
