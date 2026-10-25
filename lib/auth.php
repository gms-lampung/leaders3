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

function sso_key(): string {
  $s = app_env('GOOGLE_CLIENT_SECRET');
  if ($s === '') $s = app_env('APP_ADMIN_PIN', 'gms-lampung');
  return hash('sha256', 'gms-sso|' . $s);
}

function sso_set(string $email): void {
  $exp = time() + 43200;
  $email = mb_strtolower(trim($email));
  $payload = $email . '|' . $exp;
  $sig = hash_hmac('sha256', $payload, sso_key());
  $val = base64_encode($payload) . '.' . $sig;
  header('Set-Cookie: gms_sso=' . urlencode($val) . '; expires=' . gmdate('D, d M Y H:i:s', $exp) . ' GMT; Max-Age=43200; path=/; domain=.gmslampung.online; HttpOnly; SameSite=Lax' . (https_on() ? '; Secure' : ''), false);
}

function sso_get(): string {
  $c = $_COOKIE['gms_sso'] ?? '';
  $parts = explode('.', $c, 2);
  if (count($parts) !== 2) return '';
  $payload = base64_decode($parts[0], true);
  if ($payload === false) return '';
  $pp = explode('|', $payload, 2);
  if (count($pp) !== 2 || !ctype_digit($pp[1])) return '';
  $email = $pp[0];
  $exp = (int)$pp[1];
  if ($exp < time()) return '';
  $sig = hash_hmac('sha256', $payload, sso_key());
  if (!hash_equals($sig, $parts[1])) return '';
  return $email;
}

function is_admin(): bool {
  if (!isset($_COOKIE[AUTH_COOKIE])) {
    if (sso_get() !== '') return true;
    return false;
  }
  $parts = explode('.', (string)$_COOKIE[AUTH_COOKIE], 2);
  if (count($parts) !== 2) {
    if (sso_get() !== '') return true;
    return false;
  }
  [$exp, $sig] = $parts;
  if (!ctype_digit((string)$exp) || (int)$exp < time()) {
    if (sso_get() !== '') return true;
    return false;
  }
  if (hash_equals(auth_sign((int)$exp), (string)$sig)) return true;
  if (sso_get() !== '') return true;
  return false;
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
  header('Set-Cookie: ' . AUTH_COOKIE . '=' . urlencode($exp . '.' . auth_sign($exp)) . '; expires=' . gmdate('D, d M Y H:i:s', $exp) . ' GMT; Max-Age=' . AUTH_TTL . '; path=/; domain=.gmslampung.online; HttpOnly; SameSite=Lax' . (https_on() ? '; Secure' : ''), false);
  sso_set('admin');
  return '';
}

function admin_logout(): void {
  if (isset($_COOKIE[AUTH_COOKIE])) {
    header('Set-Cookie: ' . AUTH_COOKIE . '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; path=/; domain=.gmslampung.online; HttpOnly; SameSite=Lax' . (https_on() ? '; Secure' : ''), false);
  }
}

/** Paksa halaman hanya untuk admin. Redirect ke /login bila belum masuk. */
function require_admin(): void {
  if (!is_admin()) {
    header('Location: ' . app_url('login?next=admin'));
    exit;
  }
}

/* ------------------- SSO Google (OAuth2 manual, tanpa library) -------------------
 * Env:
 *   GOOGLE_CLIENT_ID        : client ID dari Google Cloud Console (wajib utk SSO)
 *   GOOGLE_CLIENT_SECRET    : client secret (wajib utk SSO)
 *   APP_ADMIN_EMAILS        : allowlist email admin, pisah dgn koma/spasi (wajib)
 *   APP_PUBLIC_URL          : URL publik app (opsional; default dari request)
 *   APP_GOOGLE_REDIRECT_URI : override redirect URI (opsional)
 * Redirect URI yang wajib didaftarkan di OAuth client:
 *   <APP_PUBLIC_URL>/oauth/google/callback
 * Sesi tetap memakai cookie gmsa_auth yang sama dengan login PIN.
 * ============================================================================== */

const GOOGLE_STATE_COOKIE = 'gmsa_oauth_state';

function google_oauth_configured(): bool {
  return app_env('GOOGLE_CLIENT_ID') !== '' && app_env('GOOGLE_CLIENT_SECRET') !== '';
}

/** URL publik aplikasi (env APP_PUBLIC_URL menang; bila kosong dari request). */
function app_public_base(): string {
  $pub = rtrim(app_env('APP_PUBLIC_URL', ''), '/');
  if ($pub !== '') return $pub;
  $scheme = https_on() ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host;
}

/** redirect_uri SSO — wajib sama persis dengan yang terdaftar di Google. */
function google_redirect_uri(): string {
  $override = trim(app_env('APP_GOOGLE_REDIRECT_URI'));
  if ($override !== '') return $override;
  return app_public_base() . app_base_path() . '/oauth/google/callback';
}

/** Daftar email admin (lowercase) dari env APP_ADMIN_EMAILS. */
function google_admin_emails(): array {
  $list = [];
  foreach (preg_split('/[\s,;]+/', (string)app_env('APP_ADMIN_EMAILS')) as $e) {
    $e = mb_strtolower(trim($e));
    if ($e !== '') $list[] = $e;
  }
  return $list;
}

/** Terbitkan cookie admin utk email yang lolos allowlist. Return '' bila sukses. */
function admin_login_email(string $email): string {
  $email = mb_strtolower(trim($email));
  $list = google_admin_emails();
  if (!$list) return 'Allowlist admin belum diisi di server (env APP_ADMIN_EMAILS).';
  if (!in_array($email, $list, true)) return 'Email ' . $email . ' tidak terdaftar sebagai admin.';
  $exp = time() + AUTH_TTL;
  header('Set-Cookie: ' . AUTH_COOKIE . '=' . urlencode($exp . '.' . auth_sign($exp)) . '; expires=' . gmdate('D, d M Y H:i:s', $exp) . ' GMT; Max-Age=' . AUTH_TTL . '; path=/; domain=.gmslampung.online; HttpOnly; SameSite=Lax' . (https_on() ? '; Secure' : ''), false);
  sso_set($email);
  return '';
}

/** Redirect ke consent Google (state HMAC + nonce, berlaku 10 menit). */
function google_oauth_start(): void {
  if (!google_oauth_configured()) {
    app_redirect('login?oauth_err=config');
  }
  $exp   = time() + 600;
  $nonce = bin2hex(random_bytes(16));
  $sig   = hash_hmac('sha256', 'oauth_state|' . $exp . '|' . $nonce, auth_secret());
  $state = $exp . '.' . $nonce . '.' . $sig;
  header('Set-Cookie: ' . GOOGLE_STATE_COOKIE . '=' . urlencode($state) . '; expires=' . gmdate('D, d M Y H:i:s', $exp) . ' GMT; Max-Age=600; path=/; domain=.gmslampung.online; HttpOnly; SameSite=Lax' . (https_on() ? '; Secure' : ''), false);
  $q = http_build_query([
    'client_id'     => app_env('GOOGLE_CLIENT_ID'),
    'redirect_uri'  => google_redirect_uri(),
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
  ]);
  header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $q);
  exit;
}

/** Validasi state OAuth (cookie vs query, expiry, dan tanda tangan HMAC). */
function google_oauth_state_ok(string $state): bool {
  if ($state === '' || !isset($_COOKIE[GOOGLE_STATE_COOKIE])) return false;
  if (!hash_equals((string)$_COOKIE[GOOGLE_STATE_COOKIE], $state)) return false;
  $parts = explode('.', $state, 3);
  if (count($parts) !== 3) return false;
  [$exp, $nonce, $sig] = $parts;
  if (!ctype_digit($exp) || (int)$exp < time()) return false;
  $calc = hash_hmac('sha256', 'oauth_state|' . (int)$exp . '|' . $nonce, auth_secret());
  return hash_equals($calc, $sig);
}

/* ------------------- CSRF (berbasis cookie) ------------------- */

function csrf_token(): string {
  if (empty($_COOKIE['gmsa_csrf'])) {
    $tok = bin2hex(random_bytes(32));
    header('Set-Cookie: gmsa_csrf=' . urlencode($tok) . '; expires=' . gmdate('D, d M Y H:i:s', time() + 86400 * 7) . ' GMT; Max-Age=' . (86400 * 7) . '; path=/; domain=.gmslampung.online; HttpOnly; SameSite=Lax' . (https_on() ? '; Secure' : ''), false);
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
