<?php
/* ============================================================
 * store.php — Dataa layer aplikasi gabungan GMS Lampung.
 *
 * Di Vercel: data disimpan di Upstash Redis via REST API
 * (filesystem serverless read-only, file JSON tidak persisten).
 * Secara lokal (Laragon): otomatis fallback ke file di data/
 * sehingga tetap bisa dikembangkan tanpa Redis.
 *
 * Key Redis:
 *   gmsapp:leaders_content  -> content.json leaders
 *   gmsapp:birthday_data    -> daftar birthday/anniversary
 *   gmsapp:settings         -> pengaturan app (birthday_visible dll.)
 *   gmsapp:reminder_sent    -> penanda tanggal email reminder terkirim
 *   gmsapp:throttle:<ip>    -> pembatas percobaan login admin
 * ============================================================ */

define('STORE_K_CONTENT',   'gmsapp:leaders_content');
define('STORE_K_BIRTHDAYS', 'gmsapp:birthday_data');
define('STORE_K_SETTINGS',  'gmsapp:settings');
define('STORE_K_FLAG',      'gmsapp:reminder_sent');

function app_env(string $key, string $default = ''): string {
  static $loaded = false;
  static $vars = [];
  if (!$loaded) {
    $loaded = true;
    $file = dirname(__DIR__) . '/.env';
    if (is_file($file)) {
      foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $vars[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
      }
    }
  }
  $v = getenv($key);
  if ($v !== false && $v !== '') return $v;
  return isset($vars[$key]) && $vars[$key] !== '' ? $vars[$key] : $default;
}

function store_upstash_configured(): bool {
  return app_env('UPSTASH_REDIS_REST_URL') !== '' && app_env('UPSTASH_REDIS_REST_TOKEN') !== '';
}

function store_local_path(string $name): string {
  return dirname(__DIR__) . '/data/' . $name;
}

/** True bila filesystem lokal bisa ditulisi (false di Vercel/Lambda: read-only). */
function store_fs_writable(): bool {
  static $writable = null;
  if ($writable === null) {
    $dir = dirname(store_local_path('x'));
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $probe = $dir . '/.write_test';
    $writable = @file_put_contents($probe, 'ok') !== false && @unlink($probe);
  }
  return $writable;
}

function store_http(string $method, string $url, array $headers = [], $body = null): array {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  $res = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  return [$code, is_string($res) ? $res : '', $err];
}

/** Ambil nilai string dari Redis. Null jika tidak ada / error. */
function store_get_raw(string $key): ?string {
  if (!store_upstash_configured()) return null;
  $url = rtrim(app_env('UPSTASH_REDIS_REST_URL'), '/') . '/get/' . rawurlencode($key);
  [$code, $res] = store_http('GET', $url, ['Authorization: Bearer ' . app_env('UPSTASH_REDIS_REST_TOKEN')]);
  if ($code !== 200 || $res === '') return null;
  $json = json_decode($res, true);
  if (!is_array($json) || !isset($json['result']) || !is_string($json['result'])) return null;
  return $json['result'];
}

/** Simpan nilai string ke Redis. */
function store_set_raw(string $key, string $value, int $ttlSec = 0): bool {
  if (!store_upstash_configured()) return false;
  $url = rtrim(app_env('UPSTASH_REDIS_REST_URL'), '/') . '/set/' . rawurlencode($key);
  if ($ttlSec > 0) $url .= '?EX=' . $ttlSec;
  [$code] = store_http('POST', $url, [
    'Authorization: Bearer ' . app_env('UPSTASH_REDIS_REST_TOKEN'),
    'Content-Type: text/plain',
  ], $value);
  return $code === 200;
}

/* ---------------- Fallback lokal (Laragon / dev) ---------------- */

function store_local_read(string $name) {
  $f = store_local_path($name);
  if (!file_exists($f)) return null;
  return json_decode(file_get_contents($f), true);
}

function store_local_write(string $name, $data): bool {
  if (!store_fs_writable()) return false;
  return file_put_contents(store_local_path($name), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

function store_seed_json(string $seedName) {
  $f = dirname(__DIR__) . '/seed/' . $seedName;
  if (!is_file($f)) return null;
  return json_decode(file_get_contents($f), true);
}

/**
 * Ambil struktur data (array) dari Redis; bila kosong isi dari seed.
 * Tanpa Redis: pakai file lokal di data/ (di-seed dari seed/).
 */
function store_get_data(string $redisKey, string $localName, string $seedName, bool $assocObject = true): array {
  $raw = store_upstash_configured() ? store_get_raw($redisKey) : null;
  if ($raw !== null) {
    // Selalu decode ke array asosiatif agar konsisten dengan jalur
    // file/seed; konsumen (halaman) mengharapkan array, bukan objek.
    $d = json_decode($raw, true);
    if (is_array($d)) return $assocObject ? $d : array_values($d);
  }
  // Fallback / seed awal
  $local = store_local_read($localName);
  if (is_array($local)) {
    if ($raw === null && store_upstash_configured()) {
      store_set_raw($redisKey, json_encode($assocObject ? $local : array_values($local)));
    }
    return $local;
  }
  $seed = store_seed_json($seedName);
  if (!is_array($seed)) $seed = [];
  if ($assocObject === false) $seed = array_values($seed);
  store_local_write($localName, $seed);
  if ($raw === null && store_upstash_configured()) {
    store_set_raw($redisKey, json_encode($assocObject ? $seed : array_values($seed)));
  }
  return $seed;
}

function store_save_data(string $redisKey, string $localName, array $data, bool $assocObject = true): bool {
  if (store_upstash_configured()) {
    // Daftar (assocObject=false) disimpan sebagai JSON array murni,
    // bukan objek ber-key numerik.
    $ok = store_set_raw($redisKey, json_encode($assocObject ? $data : array_values($data)));
    if (store_fs_writable()) store_local_write($localName, $data); // mirror opsional di dev
    return $ok;
  }
  return store_local_write($localName, $data);
}

/* ---------------- API tingkat tinggi ---------------- */

function get_leaders_content(): array {
  return store_get_data(STORE_K_CONTENT, 'leaders_content.json', 'content.json');
}

function save_leaders_content(array $content): bool {
  return store_save_data(STORE_K_CONTENT, 'leaders_content.json', $content);
}

function get_birthdays(): array {
  return store_get_data(STORE_K_BIRTHDAYS, 'birthdays.json', 'birthdays.json', false);
}

function save_birthdays(array $data): bool {
  return store_save_data(STORE_K_BIRTHDAYS, 'birthdays.json', array_values($data), false);
}

function get_settings(): array {
  $s = store_get_data(STORE_K_SETTINGS, 'settings.json', '');
  $defaults = ['birthday_visible' => true];
  foreach ($defaults as $k => $v) {
    if (!array_key_exists($k, $s)) $s[$k] = $v;
  }
  return $s;
}

function save_settings(array $settings): bool {
  return store_save_data(STORE_K_SETTINGS, 'settings.json', $settings);
}

function birthday_tab_visible(): bool {
  $s = get_settings();
  return !empty($s['birthday_visible']);
}

/* ---------------- Flag & throttle (Redis TTL) ---------------- */

function get_reminder_sent_flag(): string {
  $raw = store_upstash_configured() ? store_get_raw(STORE_K_FLAG) : null;
  if ($raw === null) {
    $f = store_local_path('reminder_sent.txt');
    return is_file($f) ? trim((string)file_get_contents($f)) : '';
  }
  return $raw;
}

function set_reminder_sent_flag(string $dateYmd): void {
  if (store_upstash_configured()) {
    // Simpan 3 hari agar flag tidak menumpuk
    store_set_raw(STORE_K_FLAG, $dateYmd, 259200);
  } elseif (store_fs_writable()) {
    @file_put_contents(store_local_path('reminder_sent.txt'), $dateYmd);
  }
}

function login_throttle_key(): string {
  $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'cli');
  $ip = trim(explode(',', (string)$ip)[0]);
  return 'gmsapp:throttle:' . md5($ip);
}

function login_throttle_state(): array {
  $raw = store_upstash_configured() ? store_get_raw(login_throttle_key()) : null;
  if ($raw === null) {
    $local = store_local_read('login_throttle_' . substr(login_throttle_key(), -12) . '.json');
    return is_array($local) ? $local : ['fails' => 0, 'blocked_until' => 0];
  }
  $d = json_decode($raw, true);
  return is_array($d) ? $d : ['fails' => 0, 'blocked_until' => 0];
}

function login_throttle_save(array $state): void {
  $ttl = max(60, (int)$state['blocked_until'] - time());
  if (store_upstash_configured()) {
    store_set_raw(login_throttle_key(), json_encode($state), min($ttl, 900));
  } else {
    store_local_write('login_throttle_' . substr(login_throttle_key(), -12) . '.json', $state);
  }
}
