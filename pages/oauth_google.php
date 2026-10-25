<?php
/* ============================================================
 * oauth_google.php — SSO Google (mulai + callback).
 * Route: /oauth/google/start dan /oauth/google/callback.
 * Tanpa query ?code => dianggap entry point, redirect ke Google.
 * Callback: validasi state -> tukar code -> ambil email ->
 *           cek allowlist -> terbitkan cookie gmsa_auth.
 * ============================================================ */

$code  = isset($_GET['code'])  ? (string)$_GET['code']  : '';
$state = isset($_GET['state']) ? (string)$_GET['state'] : '';

/* --------- Entry point: mulai flow, redirect ke Google --------- */
if ($code === '' || $state === '') {
  google_oauth_start(); // selalu exit (atau redirect dgn oauth_err=config)
}

/* --------- Callback: konsusmi state agar tidak bisa dipakai ulang --------- */
if (!google_oauth_state_ok($state)) {
  app_redirect('login?oauth_err=state');
}
header('Set-Cookie: ' . GOOGLE_STATE_COOKIE . '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; path=/; domain=.gmslampung.online; HttpOnly; SameSite=Lax' . (https_on() ? '; Secure' : ''), false);

/* --------- Tukar authorization code -> access token --------- */
[$tokCode, $tokRes] = store_http('POST', 'https://oauth2.googleapis.com/token', [
  'Content-Type: application/x-www-form-urlencoded',
], http_build_query([
  'code'          => $code,
  'client_id'     => app_env('GOOGLE_CLIENT_ID'),
  'client_secret' => app_env('GOOGLE_CLIENT_SECRET'),
  'redirect_uri'  => google_redirect_uri(),
  'grant_type'    => 'authorization_code',
]));
$tok = is_string($tokRes) ? json_decode($tokRes, true) : null;
if ($tokCode !== 200 || !is_array($tok) || !isset($tok['access_token'])) {
  app_redirect('login?oauth_err=token');
}

/* --------- Ambil profil (email) via endpoint OpenID Connect --------- */
[$uiCode, $uiRes] = store_http('GET', 'https://openidconnect.googleapis.com/v1/userinfo', [
  'Authorization: Bearer ' . $tok['access_token'],
]);
$me = is_string($uiRes) ? json_decode($uiRes, true) : null;
$email = is_array($me) ? (string)($me['email'] ?? '') : '';
if ($uiCode !== 200 || $email === '') {
  app_redirect('login?oauth_err=userinfo');
}
if (empty($me['email_verified'])) {
  app_redirect('login?oauth_err=unverified');
}

/* --------- Allowlist, lalu terbitkan sesi admin --------- */
if (admin_login_email($email) !== '') {
  app_redirect('login?oauth_err=denied');
}
app_redirect('admin');
