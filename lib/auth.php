<?php
/* ============================================================
 * auth.php — Autentikasi admin gabungan (satu PIN untuk semua).
 *
 * Tidak memakai PHP session karena tidak reliable di lingkungan
 * serverless (Vercel). Sebagai gantinya: cookie HMAC-signed:
 *   gmsa_auth = <expiry>.<hmac_sha256("admin|<expiry>", SECRET)>
 *
 * Env yang dipakai:
 *   APP_ADMIN_PIN   : PIN admin (wajib)
 *   APP_AUTH_SECRET : secret HMAC (opsional; auto-turunan PIN jika kosong)
 * ============================================================ */

require_once __DIR__ . '/store.php';

const AUTH_COOKIE = 'gmsa_auth';
const AUTH_TTL    = 43200; // 12 jam

function auth_secret(): string {
  $secret = app_env('APP_AUTH_SECRET');
  if ($secret !== '') return $secret;
  // Turunan deterministik dari PIN bila secret belum diatur.
  return hash('sha256', 'gmsapp|' . app_env('APP_ADMIN_PIN', 'gms-lampung'));
}

function https_on(): bool {
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
  return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function auth_sign(int $exp): string {
  return hash_hmac('sha256', 'admin|' . $exp, auth_secret());
}

function is_admin(): bool {
  if (!isset($_COOKIE[AUTH_COOKIE])) return false;
  $parts = explode('.', (string)$_COOKIE[AUTH_COOKIE], 2);
  if (count($parts) !== 2) return false;
  [$exp, $sig] = $parts;
  if (!ctype_digit((string)$exp) || (int)$exp < time()) return false;
  return hash_equals(auth_sign((int)$exp), (string)$sig);
}

/**
 * Coba login admin. Return '' jika sukses, atau pesan error.
 * Dilengkapi throttle: 5x gagal -> blokir 15 menit.
 */
function admin_login(string $pin): string {
  $pin = trim($pin);
  $st = login_throttle_state();
  $blocked = (int)$st['blocked_until'] - time();
  if ($blocked > 0) {
    return 'Terlalu banyak percobaan. Coba lagi dalam ' . ceil($blocked / 60) . ' menit.';
  }
  if (!hash_equals(app_env('APP_ADMIN_PIN'), $pin) || $pin === '') {
    $st['fails'] = (int)$st['fails'] + 1;
    if ($st['fails'] >= 5) {
      $st['fails'] = 0;
      $st['blocked_until'] = time() + 900;
      login_throttle_save($st);
      return 'PIN salah. Terlalu banyak percobaan, coba lagi dalam 15 menit.';
    }
    login_throttle_save($st);
    return 'PIN yang Anda masukkan salah.';
  }
  login_throttle_save(['fails' => 0, 'blocked_until' => 0]);
  $exp = time() + AUTH_TTL;
  setcookie(AUTH_COOKIE, $exp . '.' . auth_sign($exp), [
    'expires'  => $exp,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => https_on(),
  ]);
  return '';
}

function admin_logout(): void {
  if (isset($_COOKIE[AUTH_COOKIE])) {
    setcookie(AUTH_COOKIE, '', ['expires' => time() - 42000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => https_on()]);
  }
}

/** Paksa halaman hanya untuk admin. Redirect ke /login bila belum masuk. */
function require_admin(): void {
  if (!is_admin()) {
    header('Location: ' . app_url('login?next=admin'));
    exit;
  }
}

/* ------------------- CSRF (berbasis cookie) ------------------- */

function csrf_token(): string {
  if (empty($_COOKIE['gmsa_csrf'])) {
    $tok = bin2hex(random_bytes(32));
    setcookie('gmsa_csrf', $tok, [
      'expires'  => time() + 86400 * 7,
      'path'     => '/',
      'httponly' => true,
      'samesite' => 'Lax',
      'secure'   => https_on(),
    ]);
    $_COOKIE['gmsa_csrf'] = $tok; // tersedia untuk request saat ini juga
  }
  return $_COOKIE['gmsa_csrf'];
}

function csrf_field(): string {
  return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_ok(): bool {
  return isset($_POST['csrf'], $_COOKIE['gmsa_csrf'])
    && is_string($_POST['csrf'])
    && hash_equals((string)$_COOKIE['gmsa_csrf'], $_POST['csrf']);
}
