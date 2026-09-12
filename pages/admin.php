<?php
/* ============================================================
 * admin.php — Panel admin Knowledge Base Leaders 3.
 * Tampilan memakai gabungan leaders2 (konten lama, MENANG) +
 * leaders3 (section baru & yang diambil alih). Tulis SELALU ke
 * key leaders3 (gmsapp:leaders3_content) — leaders2 tidak pernah
 * diubah. Section leaders2 yang diedit disalin ke leaders3
 * (ambil alih) saat benar-benar ada perubahan.
 * ============================================================ */

require_admin();

require_once dirname(__DIR__) . '/lib/kb.php';

$l3 = get_leaders_content();
$view = get_kb_content();
$content = $l3;

function lget($arr, $key, $default = '') {
  return isset($arr[$key]) ? $arr[$key] : $default;
}

function lines($str) {
  $parts = preg_split('/\r\n|\r|\n/', trim((string)$str));
  $out = [];
  foreach ($parts as $p) {
    $p = trim($p);
    if ($p !== '') $out[] = $p;
  }
  return $out;
}

const ADMIN_FORM_VERSION = '4';

/** Tulis string hanya jika input POST tidak kosong; bila kosong pertahankan nilai lama. */
function form_str(array $post, string $key, string $old): string {
  $v = array_key_exists($key, $post) ? trim((string)$post[$key]) : '';
  return $v !== '' ? $v : $old;
}

/** Tulis daftar baris hanya jika input POST tidak kosong; bila kosong pertahankan nilai lama. */
function form_lines(array $post, string $key, array $old): array {
  $v = array_key_exists($key, $post) ? trim((string)$post[$key]) : '';
  return $v !== '' ? lines($v) : $old;
}

/** Simpan SATU section dari input form admin.
 *  Nama input memakai awalan unik per section (sec_{key}_*) sehingga semua
 *  section bisa ditampilkan sekaligus dalam satu form raksasa. Input yang
 *  kosong mempertahankan nilai lama (keep-old). */
function admin_save_section(array &$content, string $key): void {
  $old = $content['sections'][$key] ?? [];
  $pfx = 'sec_' . $key . '_';
  $c = &$content['sections'][$key];

  // Identitas section
  $c['name'] = form_str($_POST, $pfx . 'name', lget($old, 'name'));
  $c['icon'] = form_str($_POST, $pfx . 'icon', lget($old, 'icon', 'fa-solid fa-file'));
  if (isset($_POST['has_visibility'])) {
    $c['visible'] = array_key_exists($pfx . 'visible', $_POST) ? true : false;
  }

  // Editor satu field besar: teks dipecah otomatis ke struktur lama.
  $bigIn = $pfx . 'big_text';
  if (array_key_exists($bigIn, $_POST)) {
    kb_big_text_apply($key, $c, (string)$_POST[$bigIn]);
    return;
  }

  // Field konten generik (keep-old: hanya menulis bila input tidak kosong)
  $ct = &$c['content'];
  foreach (kb_content_fields($key) as $field) {
    $fmeta = kb_content_meta($field);
    $in = $pfx . 'c_' . $field;
    if (isset($_POST[$in]) && trim((string)$_POST[$in]) !== '') {
      $ct[$field] = $fmeta['type'] === 'lines' ? lines((string)$_POST[$in]) : trim((string)$_POST[$in]);
    }
  }
  unset($ct);

  // Subseksi generik (keep-old, mempertahankan bentuk data lama)
  $subDef = kb_subsection_def($key);
  if ($subDef) {
    if ($subDef['scope'] === 'content') {
      if (!isset($c['content']['subsections'])) $c['content']['subsections'] = [];
      $container = &$c['content']['subsections'];
    } else {
      if (!isset($c['subsections'])) $c['subsections'] = [];
      $container = &$c['subsections'];
    }
    foreach ($subDef['blocks'] as $slug => $block) {
      $item = isset($container[$slug]) && is_array($container[$slug]) ? $container[$slug] : [];
      foreach ($block['fields'] as $f) {
        $in = $pfx . 's_' . $slug . '_' . $f[0];
        if (!array_key_exists($in, $_POST)) continue;
        $raw = trim((string)$_POST[$in]);
        if ($raw === '') continue;
        if ($f[1] === 'lines') {
          $item[$f[0]] = lines($raw);
        } elseif ($f[1] === 'pairs') {
          $item[$f[0]] = kb_parse_pairs(
            $raw,
            isset($f[3]) && $f[3] !== '' ? $f[3] : 'title',
            isset($f[4]) && $f[4] !== '' ? $f[4] : 'text'
          );
        } elseif ($f[1] === 'int') {
          $item[$f[0]] = (int)$raw;
        } else {
          $item[$f[0]] = $raw;
        }
      }
      $container[$slug] = $item;
    }
    unset($container);
  }

  // Field level-section generik (msj: cek_kelulusan)
  foreach (kb_section_fields($key) as $sf) {
    $in = $pfx . 'l_' . $sf['field'];
    if (isset($_POST[$in]) && trim((string)$_POST[$in]) !== '') {
      $c[$sf['field']] = $sf['type'] === 'lines' ? lines((string)$_POST[$in]) : trim((string)$_POST[$in]);
    }
  }

  // Link tutorial (cgt)
  if (kb_has_links($key)) {
    $linksRaw = isset($_POST[$pfx . 'links']) ? trim((string)$_POST[$pfx . 'links']) : '';
    if ($linksRaw !== '') {
      $c['links'] = kb_parse_pairs($linksRaw, 'label', 'url');
    }
  }

  // FAQ (keep-old)
  $faqRaw = isset($_POST[$pfx . 'faq']) ? trim((string)$_POST[$pfx . 'faq']) : '';
  if ($faqRaw !== '') {
    $faqArr = [];
    foreach (lines($faqRaw) as $fl) {
      $parts = explode("\t", $fl, 2);
      if (count($parts) === 2) {
        $faqArr[] = ['q' => trim($parts[0]), 'a' => trim($parts[1])];
      }
    }
    $c['faq'] = $faqArr;
  }
}

/** Basis editor untuk sebuah section: salinan leaders3 bila ada, selainnya gabungan (leaders2). */
function admin_section_edit_base(array $l3, array $view, string $key): array {
  return isset($l3['sections'][$key]) && is_array($l3['sections'][$key])
    ? $l3['sections'][$key]
    : ($view['sections'][$key] ?? []);
}

/**
 * Simpan SATU section dari form aktif ke buffer leaders3 dengan aturan
 * ambil-alih (copy-on-save):
 *  - pemilik leaders2 yang TIDAK diubah -> dilewati (leaders2 tetap sumber);
 *  - ada perubahan (teks besar / identitas) -> disalin ke leaders3, diberi
 *    flag _taken, lalu admin_save_section() diterapkan;
 *  - milik leaders3 -> admin_save_section() dengan flag _taken dipastikan set.
 * Mengembalikan nama section bila tersimpan, '' bila dilewati.
 */
function admin_save_section_merged(array &$content, array $view, string $key): string {
  $l3Sections = isset($content['sections']) && is_array($content['sections']) ? $content['sections'] : [];
  $viewSec = isset($view['sections'][$key]) && is_array($view['sections'][$key]) ? $view['sections'][$key] : [];
  $pfx = 'sec_' . $key . '_';

  $txtNew = isset($_POST[$pfx . 'big_text']) ? (string)$_POST[$pfx . 'big_text'] : '';
  $nameNew = isset($_POST[$pfx . 'name']) ? trim((string)$_POST[$pfx . 'name']) : (string)lget($viewSec, 'name', '');
  $iconNew = isset($_POST[$pfx . 'icon']) ? trim((string)$_POST[$pfx . 'icon']) : (string)lget($viewSec, 'icon', '');
  $visNew = isset($_POST['has_visibility'])
    ? isset($_POST[$pfx . 'visible'])
    : (bool)lget($viewSec, 'visible', true);

  $r = kb_big_section_save($l3Sections, $key, $viewSec, $txtNew, $nameNew, $iconNew, $visNew);
  if (!$r['changed'] || !is_array($r['section'])) return '';
  $content['sections'][$key] = $r['section'];
  return (string)lget($r['section'], 'name', $key);
}

/** Sidebar navigasi admin: Pengaturan + daftar section. */
function render_admin_sidebar(array $sections, string $activeKey): void {
  $act = function (?string $k) use ($activeKey): string {
    return $k === $activeKey ? ' adm-sb-item-active' : '';
  };
  ?>
  <aside class="adm-sidebar">
    <a class="adm-sb-item<?php echo $act('__site'); ?>" href="<?php echo app_url('admin?s=__site'); ?>">
      <i class="fa-solid fa-sliders adm-sb-ico"></i><span class="adm-sb-nm">Pengaturan Halaman</span>
    </a>
    <div class="adm-sb-sep"></div>
    <?php foreach ($sections as $key => $kc): ?>
      <a class="adm-sb-item<?php echo $act($key); ?>" href="<?php echo app_url('admin?s=' . urlencode($key)); ?>">
        <i class="<?php echo htmlspecialchars(lget($kc, 'icon', 'fa-solid fa-file')); ?> adm-sb-ico"></i>
        <span class="adm-sb-nm"><?php echo htmlspecialchars(lget($kc, 'name', $key)); ?></span>
      </a>
    <?php endforeach; ?>
    <button type="button" class="adm-sb-add" id="sbAddBtn"><i class="fa-solid fa-plus"></i> Tambah Section</button>
    <div id="sbAddForm" class="adm-sb-addform" hidden>
      <form method="POST" action="<?php echo app_url('admin'); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="form_version" value="<?php echo ADMIN_FORM_VERSION; ?>">
        <input type="hidden" name="action" value="add_section">
        <input type="text" name="new_slug" class="input-gms" placeholder="Slug (cth: baptisan_anak)" required>
        <input type="text" name="new_name" class="input-gms" placeholder="Nama (cth: Baptisan Anak)" required>
        <input type="text" name="new_icon" class="input-gms" placeholder="Ikon (opsional, cth: fa-solid fa-baby)">
        <button type="submit" class="btn-gms-pill w-full justify-center"><i class="fa-solid fa-plus"></i> Buat Section</button>
      </form>
    </div>
  </aside>
  <?php
}

/** Kartu pengaturan global (judul, welcome, footer). */
function render_site_settings(): void {
  $site = $GLOBALS['site'] ?? [];
  ?>
  <details class="card-gms adm-block" data-has="1" open>
    <summary>
      <span class="adm-sum-icon"><i class="fa-solid fa-sliders"></i></span>
      <span class="adm-title">Setting Halaman</span>
      <i class="fa-solid fa-chevron-down adm-sum-caret"></i>
    </summary>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div>
        <label class="label-gms">Judul Halaman</label>
        <input type="text" name="site_title" class="input-gms" value="<?php echo htmlspecialchars(lget($site, 'title')); ?>">
      </div>
      <div>
        <label class="label-gms">Sub Judul</label>
        <input type="text" name="site_subtitle" class="input-gms" value="<?php echo htmlspecialchars(lget($site, 'subtitle')); ?>">
      </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div>
        <label class="label-gms">Welcome Heading</label>
        <input type="text" name="site_welcome_heading" class="input-gms" value="<?php echo htmlspecialchars(lget($site, 'welcome_heading')); ?>">
      </div>
      <div>
        <label class="label-gms">Welcome Text</label>
        <input type="text" name="site_welcome_text" class="input-gms" value="<?php echo htmlspecialchars(lget($site, 'welcome_text')); ?>">
      </div>
    </div>
    <div class="mb-4">
      <label class="label-gms">Welcome Intro</label>
      <textarea name="site_welcome_intro" class="input-gms" rows="2"><?php echo htmlspecialchars(lget($site, 'welcome_intro')); ?></textarea>
    </div>
    <div class="mb-4">
      <label class="label-gms">Welcome Notes (satu baris per item)</label>
      <textarea name="site_welcome_notes" class="input-gms" rows="4" data-row-editor="lines" data-ph="Catatan / salam"><?php echo htmlspecialchars(implode("\n", lget($site, 'welcome_notes', []))); ?></textarea>
    </div>
    <div class="mb-4">
      <label class="label-gms">Welcome Closing</label>
      <textarea name="site_welcome_closing" class="input-gms" rows="2"><?php echo htmlspecialchars(lget($site, 'welcome_closing')); ?></textarea>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="label-gms">Footer Text</label>
        <input type="text" name="site_footer_text" class="input-gms" value="<?php echo htmlspecialchars(lget($site, 'footer_text')); ?>">
      </div>
      <div>
        <label class="label-gms">Copyright</label>
        <input type="text" name="site_copyright" class="input-gms" value="<?php echo htmlspecialchars(lget($site, 'copyright')); ?>">
      </div>
    </div>
  </details>
  <?php
}

$sections = lget($view, 'sections', []);
$activeKey = isset($_GET['s']) ? (string)$_GET['s'] : '';
if (!isset($sections[$activeKey])) {
  $activeKey = $sections ? array_key_first($sections) : '';
}
$sec = $activeKey === '' ? [] : admin_section_edit_base($l3, $view, $activeKey);
$viewSites = isset($_GET['s']) && $_GET['s'] === '__site';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!csrf_ok()) {
    http_response_code(403);
    $error = 'Sesi kedaluwarsa atau token keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
  } else {
    $formStale = ($_POST['form_version'] ?? '') !== ADMIN_FORM_VERSION;
    if ($formStale) {
      http_response_code(400);
      $error = 'Formulir admin sudah tidak berlaku. Muat ulang halaman, lalu simpan ulang - tidak ada data yang diubah.';
    }
    if (!$formStale) {

    $p = function ($key, $default = '') {
      return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
    };

    $action = $p('action');

    // ---- Aksi khusus: tambah section baru ----
    if ($action === 'add_section') {
      $slug = strtolower($p('new_slug'));
      $name = $p('new_name');
      $icon = $p('new_icon');
      if (!preg_match('/^[a-z0-9_]{2,50}$/', $slug)) {
        $error = 'Slug section baru harus 2-50 karakter: huruf kecil, angka, atau underscore (tanpa spasi).';
      } elseif ($name === '') {
        $error = 'Nama section baru wajib diisi.';
      } elseif (isset($content['sections'][$slug]) || isset($view['sections'][$slug])) {
        $error = 'Slug "' . htmlspecialchars($slug) . '" sudah dipakai oleh section lain.';
      } elseif ($icon !== '' && !preg_match('/^[a-zA-Z0-9 _-]{2,80}$/', $icon)) {
        $error = 'Kelas ikon Font Awesome tidak valid (contoh: fa-solid fa-book).';
      } else {
        $content['sections'][$slug] = [
          'name' => $name,
          'icon' => $icon !== '' ? $icon : 'fa-solid fa-file',
          'visible' => true,
          'content' => [],
          'faq' => [],
        ];
        if (!save_leaders_content($content)) {
          $error = 'Gagal menyimpan section baru.';
        } else {
          app_redirect('admin?s=' . urlencode($slug) . '&added=' . urlencode($slug));
        }
      }
    } else {

    // ---- Simpan konten / pengaturan ----
    $postKey = $p('section', $activeKey);
    if ($postKey !== '__all' && !isset($content['sections'][$postKey])) {
      $postKey = $activeKey;
    }

    // Site settings (input kosong => pertahankan nilai lama)
    $siteOld = $content['site'] ?? [];
    $content['site']['title'] = form_str($_POST, 'site_title', lget($siteOld, 'title'));
    $content['site']['subtitle'] = form_str($_POST, 'site_subtitle', lget($siteOld, 'subtitle'));
    $content['site']['welcome_heading'] = form_str($_POST, 'site_welcome_heading', lget($siteOld, 'welcome_heading'));
    $content['site']['welcome_text'] = form_str($_POST, 'site_welcome_text', lget($siteOld, 'welcome_text'));
    $content['site']['welcome_intro'] = form_str($_POST, 'site_welcome_intro', lget($siteOld, 'welcome_intro'));
    $content['site']['welcome_notes'] = form_lines($_POST, 'site_welcome_notes', lget($siteOld, 'welcome_notes', []));
    $content['site']['welcome_closing'] = form_str($_POST, 'site_welcome_closing', lget($siteOld, 'welcome_closing'));
    $content['site']['footer_text'] = form_str($_POST, 'site_footer_text', lget($siteOld, 'footer_text'));
    $content['site']['copyright'] = form_str($_POST, 'site_copyright', lget($siteOld, 'copyright', 'GMS Lampung'));

    // Simpan konten: form menulis SATU section aktif; pemilik leaders2
    // hanya diambil alih (disalin ke leaders3) saat benar-benar berubah.
    // Form pengaturan (__site) hanya menyimpan pengaturan global.
    $savedName = '';
    if ($postKey === '__all') {
      $savedNames = [];
      foreach (array_keys($view['sections']) as $sk) {
        $nm = admin_save_section_merged($content, $view, $sk);
        if ($nm !== '') $savedNames[] = $nm;
      }
      $savedName = $savedNames ? 'semua section' : '';
    } elseif ($postKey !== '' && $postKey !== '__site') {
      $savedName = admin_save_section_merged($content, $view, $postKey);
    }

    if (!save_leaders_content($content)) {
      $error = 'Gagal menyimpan konten.';
    } else {
      $message = $savedName !== ''
        ? 'Konten ' . htmlspecialchars($savedName) . ' berhasil disimpan.'
        : 'Pengaturan halaman berhasil disimpan.';
    }

    $l3 = get_leaders_content();
    $view = get_kb_content();
    $sections = lget($view, 'sections', []);
    if (!isset($sections[$activeKey])) {
      $activeKey = $sections ? array_key_first($sections) : '';
    }
    $sec = $activeKey === '' ? [] : admin_section_edit_base($l3, $view, $activeKey);
    }
    }
  }
}

$addedKey = isset($_GET['added']) ? (string)$_GET['added'] : '';
if ($addedKey !== '' && isset($sections[$addedKey])) {
  $message = 'Section "' . htmlspecialchars(lget($sections[$addedKey], 'name', $addedKey)) . '" berhasil ditambahkan. Isi kontennya di daftar bawah lalu simpan.';
}

$site = lget($content, 'site', []);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <title>Panel Admin - GMS Lampung</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/css/gms.css" />
  <link rel="manifest" href="/manifest.json" />
  <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png" />
  <link rel="apple-touch-icon" href="/icons/icon-192.png" />

  <style>
    :root {
      --gms-blue: #0052cc;
      --gms-gradient: linear-gradient(90deg, #0038a8 0%, #0052cc 60%, #0066ff 100%);
      --bg-page: #f4f6fa;
      --text-main: #18233b;
      --text-sub: #5e6d82;
      --border-input: #d0d7de;
    }
    body {
      background-color: var(--bg-page);
      color: var(--text-main);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      -webkit-font-smoothing: antialiased;
      padding-bottom: calc(72px + env(safe-area-inset-bottom));
      margin: 0;
    }
    .btn-switch {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1.2rem;
      border-radius: 50px;
      font-weight: 700;
      font-size: 0.8rem;
      transition: all 0.2s ease;
      cursor: pointer;
      border: 1px solid var(--gms-blue);
      text-decoration: none;
    }
    .btn-switch-active {
      background-image: var(--gms-gradient);
      color: #ffffff;
      box-shadow: 0 4px 12px rgba(0, 82, 204, 0.25);
    }
    .btn-switch-inactive {
      background: #ffffff;
      color: var(--gms-blue);
    }
    .btn-switch-inactive:hover { background-color: #e0edff; }
    .hint-gms { font-size: 0.7rem; color: var(--text-sub); margin-top: 0.25rem; }
    .switch { position: relative; width: 44px; height: 24px; background: #d0d7de; border-radius: 9999px; transition: background 0.2s; flex-shrink: 0; display: inline-block; }
    .switch::after { content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background: #fff; border-radius: 9999px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); transition: transform 0.2s; }
    input:checked + .switch { background: var(--gms-blue); }
    input:checked + .switch::after { transform: translateX(20px); }
    .big-konten {
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Courier New", monospace;
      font-size: 0.8125rem;
      line-height: 1.65;
      min-height: 420px;
    }

    /* ===== Form ringkas: baris/blok dapat dilipat ===== */
    details.adm-row > summary, details.adm-block > summary {
      list-style: none;
      cursor: pointer;
      user-select: none;
      display: flex;
      align-items: center;
      gap: 10px;
      -webkit-tap-highlight-color: transparent;
    }
    details.adm-row > summary::-webkit-details-marker,
    details.adm-block > summary::-webkit-details-marker { display: none; }
    .adm-sum-icon {
      width: 28px; height: 28px; border-radius: 9px;
      background: var(--gms-blue, #0052cc); color: #fff;
      display: grid; place-items: center; font-size: 12px; flex-shrink: 0;
    }
    .adm-title {
      font-weight: 800; font-size: 12.5px; letter-spacing: 0.06em; text-transform: uppercase;
      color: #18233b; flex: 1; min-width: 0;
    }
    .adm-sum-label {
      font-weight: 700; font-size: 12.5px; color: #18233b;
      flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .adm-sum-hint {
      font-size: 10.5px; font-weight: 700; color: #8a94a6;
      background: #eef2f7; padding: 3px 9px; border-radius: 999px;
      white-space: nowrap; flex-shrink: 0;
    }
    .adm-sum-caret { color: #8a94a6; font-size: 11px; transition: transform 0.15s ease; flex-shrink: 0; }
    details[open] > summary .adm-sum-caret { transform: rotate(180deg); }

    details.card-gms.adm-block > summary { padding: 0 0 12px; border-bottom: 1px solid #e2e8f0; margin-bottom: 12px; }

    .adm-row {
      border: 1px solid #e2e8f0; border-radius: 12px; background: #fff;
      margin-bottom: 10px; overflow: hidden; transition: border-color 0.15s ease;
    }
    .adm-row[open] { border-color: #b9cbe8; }
    .adm-row > summary { padding: 10px 12px; }
    .adm-row[open] > summary { border-bottom: 1px solid #eef2f7; }
    .adm-row-body { padding: 4px 14px 14px; }
    .adm-row[open] .adm-row-body { padding-top: 12px; }

    .adm-row.adm-row-sec { background: linear-gradient(180deg, #f0f5ff 0%, #ffffff 55%); border-color: #bcd3f5; }
    .adm-row.adm-row-sec > summary .adm-sum-icon { background: var(--gms-gradient, linear-gradient(135deg, #0052cc, #003399)); }

    details.adm-block.adm-block-sub {
      border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc;
      margin-bottom: 10px; overflow: hidden;
    }
    details.adm-block.adm-block-sub > summary { padding: 10px 12px; border-bottom: none; margin-bottom: 0; }
    details.adm-block.adm-block-sub[open] > summary { border-bottom: 1px solid #eef2f7; }
    details.adm-block.adm-block-sub > summary .adm-sum-icon { background: #003399; }

    /* ===== Row editor (google-sites style) ===== */
    .row-editor { margin-top: 10px; }
    .row-editor-list { margin-bottom: 2px; }
    .row-editor-item { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
    .row-editor-item .row-inp { flex: 1; min-width: 0; margin: 0; }
    .row-del {
      flex-shrink: 0; width: 38px; height: 42px; border-radius: 10px;
      border: 1px solid #fee2e2; background: #fef2f2; color: #dc2626;
      cursor: pointer; font-size: 14px;
    }
    .row-del:hover { background: #fee2e2; }
    .row-add {
      width: 100%; padding: 11px 14px; border-radius: 12px;
      border: 1.5px dashed #c3d2ec; background: #f8fafc; color: #0052cc;
      font-weight: 700; font-size: 13px; cursor: pointer;
    }
    .row-add:hover { background: #eef3fb; border-color: #0052cc; }

    /* ===== Shell admin: sidebar + konten ===== */
    .adm-shell { display: grid; grid-template-columns: 252px 1fr; gap: 16px; align-items: start; }
    @media (max-width: 860px) {
      .adm-shell { grid-template-columns: 1fr; }
      .adm-sidebar { position: static; flex-direction: row; flex-wrap: wrap; }
      .adm-sidebar .adm-sb-sep { display: none; }
      .adm-sidebar .adm-sb-add { width: auto; margin-top: 6px; }
      .adm-sidebar .adm-sb-addform { width: 100%; }
    }
    .adm-sidebar {
      background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
      padding: 12px; position: sticky; top: 12px;
      display: flex; flex-direction: column; gap: 2px;
    }
    .adm-sb-item {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px; border-radius: 12px;
      color: #18233b; font-weight: 700; font-size: 13px;
      text-decoration: none; border: 1px solid transparent;
    }
    .adm-sb-item:hover { background: #f1f5f9; }
    .adm-sb-item-active { background: var(--gms-gradient, linear-gradient(135deg, #0052cc, #003399)); color: #fff; }
    .adm-sb-item-active i { color: #fff; }
    .adm-sb-ico { width: 18px; text-align: center; flex-shrink: 0; font-size: 13px; color: #0052cc; }
    .adm-sb-nm { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .adm-sb-sep { height: 1px; background: #eef1f5; margin: 8px 2px; }
    .adm-sb-add {
      width: 100%; margin-top: 10px; padding: 10px 12px;
      border: 1.5px dashed #c3d2ec; border-radius: 12px; background: #f8fafc;
      color: #0052cc; font-weight: 700; font-size: 13px; cursor: pointer;
    }
    .adm-sb-add:hover { background: #eef3fb; border-color: #0052cc; }
    .adm-sb-addform {
      margin-top: 10px; border: 1px solid #e2e8f0; border-radius: 12px;
      padding: 12px; background: #f8fafc;
    }
    .adm-sb-addform .input-gms { margin-bottom: 8px; }
    .adm-sb-addform .btn-gms-pill { margin-top: 2px; }
    .adm-main { min-width: 0; }
  </style>
</head>
<body>

  <nav class="gms-gradient flex items-center justify-between px-4 md:px-5 py-3 text-white shadow-[0_2px_10px_rgba(0,43,130,0.15)] sticky top-0 z-40">
    <div class="flex items-center gap-2 min-w-0">
      <i class="fa-solid fa-gear text-lg text-white"></i>
      <h1 class="m-0 text-base md:text-lg font-extrabold tracking-wide">Panel Admin</h1>
    </div>
    <div class="flex items-center gap-2 shrink-0">
      <a href="<?php echo app_url(''); ?>" class="text-xs bg-white/20 hover:bg-white/30 text-white font-bold px-3 py-1.5 rounded-full transition">
        <i class="fa-solid fa-eye mr-1"></i>Lihat Halaman
      </a>
      <a href="<?php echo app_url('logout'); ?>" class="text-xs bg-white/20 hover:bg-white/30 text-white font-bold px-3 py-1.5 rounded-full transition">
        <i class="fa-solid fa-sign-out-alt mr-1"></i>Keluar
      </a>
    </div>
  </nav>

  <main class="mx-auto w-full max-w-[960px] px-3">

    <!-- ============ KELOLA KONTEN LEADERS ============ -->
    <div class="section-title-wrap">
      <h2 class="page-title">
        <i class="fa-solid fa-pen-to-square mr-1 text-[#0052cc]"></i> Kelola Konten Halaman
      </h2>
      <div class="blue-indicator"></div>
    </div>

    <?php if ($message): ?>
      <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl p-3 mb-4">
        <i class="fa-solid fa-circle-check mr-1"></i><?php echo $message; ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl p-3 mb-4">
        <i class="fa-solid fa-triangle-exclamation mr-1"></i><?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <div class="adm-shell">
      <?php render_admin_sidebar($sections, $viewSites ? '__site' : $activeKey); ?>

      <div class="adm-main">
        <?php if ($viewSites): ?>

        <div class="card-gms !pb-3 mb-3">
          <p class="label-gms m-0"><i class="fa-solid fa-sliders mr-1 text-[#0052cc]"></i> Pengaturan Halaman — berlaku untuk seluruh halaman publik</p>
        </div>
        <form method="POST" action="<?php echo app_url('admin?s=__site'); ?>">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="form_version" value="<?php echo ADMIN_FORM_VERSION; ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="section" value="<?php echo htmlspecialchars($activeKey); ?>">
          <?php render_site_settings(); ?>
          <div class="card-gms flex flex-col sm:flex-row gap-3 items-center justify-between">
            <p class="text-xs text-[#5e6d82] m-0">Perubahan langsung tampil di halaman publik setelah disimpan.</p>
            <button type="submit" class="btn-gms-pill"><i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan</button>
          </div>
        </form>

        <?php else: ?>

        <?php if ($activeKey === ''): ?>

        <!-- KOSONG: belum ada kategori -->
        <div class="card-gms text-center py-8">
          <div class="mx-auto w-14 h-14 rounded-2xl bg-[#e0edff] flex items-center justify-center mb-3">
            <i class="fa-solid fa-folder-plus text-[#0052cc] text-2xl"></i>
          </div>
          <p class="label-gms mb-1">Belum ada kategori</p>
          <p class="hint-gms mb-4 max-w-sm mx-auto">Database masih kosong. Buat kategori pertama lewat tombol <strong>+ Tambah Section</strong> di menu samping, lalu isi kontennya di form yang muncul.</p>
          <button type="button" class="btn-gms-pill" onclick="document.getElementById('sbAddBtn').dispatchEvent(new Event('click')); return false;">
            <i class="fa-solid fa-plus"></i> Tambah Kategori Pertama
          </button>
        </div>

        <?php else: ?>

        <!-- TAMPILAN FORM -->
        <div class="card-gms !pb-3 mb-3">
          <p class="label-gms mb-2"><i class="fa-solid fa-file-pen mr-1 text-[#0052cc]"></i> Form Isi: <?php echo htmlspecialchars(lget($sec, 'name', $activeKey)); ?></p>
          <p class="hint-gms mb-0">Semua konten kategori ini jadi <strong>satu field besar</strong>: tulis dengan penanda <code># nama</code>, simpan, lalu sistem memecahnya otomatis ke bagian-bagian yang tampil di halaman publik. Identitas section (nama, ikon, tampil) tetap di kolom paling atas.</p>
          <p class="hint-gms mb-0 mt-1">Konten yang sudah ada di leaders2 tetap tampil dari leaders2 dan menang. Penambahan baru &amp; section yang Anda ubah lalu simpan di sini otomatis <strong>mengambil alih</strong> (disalin ke leaders3).</p>
        </div>

        <form method="POST" action="<?php echo app_url('admin?s=' . urlencode($activeKey)); ?>" enctype="multipart/form-data">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="form_version" value="<?php echo ADMIN_FORM_VERSION; ?>">
          <input type="hidden" name="has_visibility" value="1">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="section" value="<?php echo htmlspecialchars($activeKey); ?>">
          <?php
          $spfx = 'sec_' . $activeKey . '_';
          $sName = lget($sec, 'name', $activeKey);
          $markerHint = [];
          foreach (kb_content_fields($activeKey) as $fHint) {
            if (in_array($fHint, ['link_url', 'link_label'], true)) continue;
            $markerHint[] = '# ' . $fHint;
          }
          $markerHint[] = '# link';
          $subDefHint = kb_subsection_def($activeKey);
          if ($subDefHint) {
            foreach ($subDefHint['blocks'] as $subSlug2 => $subBlock2) {
              $markerHint[] = '# sub: ' . $subSlug2;
            }
          }
          foreach (kb_section_fields($activeKey) as $sfHint) {
            $markerHint[] = '# ' . $sfHint['field'];
          }
          if (kb_has_links($activeKey)) $markerHint[] = '# links';
          $markerHint[] = '# faq';
          ?>

          <!-- IDENTITAS -->
          <details class="adm-row adm-row-sec" data-has="1" open>
            <summary>
              <span class="adm-sum-icon"><i class="<?php echo htmlspecialchars(lget($sec, 'icon', 'fa-solid fa-file')); ?>"></i></span>
              <span class="adm-sum-label"><?php echo htmlspecialchars($sName); ?></span>
              <span class="adm-sum-hint">Identitas</span>
              <i class="fa-solid fa-chevron-down adm-sum-caret"></i>
            </summary>
            <div class="adm-row-body">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <div>
                  <label class="label-gms">Nama Section</label>
                  <input type="text" name="<?php echo htmlspecialchars($spfx . 'name'); ?>" class="input-gms" value="<?php echo htmlspecialchars(lget($sec, 'name')); ?>">
                </div>
                <div>
                  <label class="label-gms">Icon (Font Awesome class)</label>
                  <input type="text" name="<?php echo htmlspecialchars($spfx . 'icon'); ?>" class="input-gms" value="<?php echo htmlspecialchars(lget($sec, 'icon')); ?>" placeholder="fa-solid fa-book">
                </div>
                <div class="flex items-end">
                  <label class="flex items-center justify-between gap-3 bg-[#f8fafc] border border-[#e2e8f0] rounded-xl px-4 py-3 cursor-pointer select-none w-full">
                    <span class="flex items-center gap-2 text-sm font-bold text-[#18233b]">
                      <i class="fa-solid fa-eye text-[#0052cc]"></i> Tampil di halaman
                    </span>
                    <input type="checkbox" name="<?php echo htmlspecialchars($spfx . 'visible'); ?>" value="1" class="sr-only"
                           <?php echo lget($sec, 'visible', true) ? 'checked' : ''; ?>>
                    <span class="switch"></span>
                  </label>
                </div>
              </div>
            </div>
          </details>

          <!-- KONTEN UTUH — SATU TEXTAREA BESAR -->
          <details class="adm-row" data-has="1" open>
            <summary>
              <span class="adm-sum-icon"><i class="fa-solid fa-text-height"></i></span>
              <span class="adm-sum-label">Konten (satu field besar)</span>
              <span class="adm-sum-hint"><?php echo $sName; ?></span>
              <i class="fa-solid fa-chevron-down adm-sum-caret"></i>
            </summary>
            <div class="adm-row-body">
              <textarea name="<?php echo htmlspecialchars($spfx . 'big_text'); ?>" class="input-gms big-konten" rows="30" spellcheck="false"><?php echo htmlspecialchars(kb_big_text($activeKey, $sec)); ?></textarea>
              <p class="hint-gms mt-1"><strong>Cara pakai:</strong> tulis penanda <code># nama</code> di satu baris, lalu isinya di baris-baris berikutnya. Baris biasa = satu item (mis. satu syarat per baris). FAQ &amp; Link: <code>Kolom || Isi</code> (dua pipa; TAB lama tetap terbaca; isi/jawaban bisa lanjut di baris berikutnya tanpa <code>||</code>). Subseksi: <code># sub: slug</code> lalu tiap field dengan <code>## nama</code>. Penanda tanpa tanda khusus (mis. <code># Persiapan</code>) otomatis jadi blok isi bebas dengan judul = nama penanda. Penanda yang dihapus dari teks ikut dihapus isinya.</p>
              <details class="mt-2">
                <summary class="text-xs font-bold text-[#5e6d82] cursor-pointer"><i class="fa-solid fa-list-ul mr-1"></i> Penanda yang tersedia di kategori ini</summary>
                <p class="hint-gms mb-0 mt-1"><?php echo htmlspecialchars(implode(' ', $markerHint)); ?></p>
              </details>
            </div>
          </details>

          <!-- SUBMIT -->
          <div class="card-gms flex flex-col sm:flex-row gap-3 items-center justify-between">
            <p class="text-xs text-[#5e6d82] m-0">Perubahan akan langsung tampil di halaman publik setelah disimpan.</p>
            <button type="submit" class="btn-gms-pill">
              <i class="fa-solid fa-floppy-disk"></i> Simpan Konten
            </button>
          </div>
        </form>

        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

  </main>

  <?php gms_bottom_nav('admin'); ?>

  <script>
    (function () {
      function editor(ta) {
        if (ta._rowEditorReady) return;
        ta._rowEditorReady = true;
        var pairs = ta.getAttribute('data-row-editor') === 'pairs';
        var cols = pairs ? 2 : 1;
        var ph = [
          ta.getAttribute('data-ph1') || (pairs ? 'Label / Pertanyaan' : 'Isi'),
          ta.getAttribute('data-ph2') || 'URL / Jawaban'
        ];
        var rows = [];
        String(ta.value).split('\n').forEach(function (ln) {
          var p = ln.split('\t');
          var r = [];
          for (var c = 0; c < cols; c++) r.push(p[c] !== undefined ? p[c] : '');
          if (r.join('').replace(/\t/g, '').trim() !== '') rows.push(r);
        });

        var wrap = document.createElement('div');
        wrap.className = 'row-editor';
        var list = document.createElement('div');
        list.className = 'row-editor-list';
        wrap.appendChild(list);

        function sync() {
          var out = [];
          rows.forEach(function (r) {
            if (r.join('').replace(/\t/g, '').trim() !== '') out.push(r.join('\t'));
          });
          ta.value = out.join('\n');
        }
        function render() {
          list.innerHTML = '';
          rows.forEach(function (r, i) {
            var item = document.createElement('div');
            item.className = 'row-editor-item';
            for (var c = 0; c < cols; c++) (function (c) {
              var inp = document.createElement('input');
              inp.type = 'text';
              inp.className = 'input-gms row-inp';
              inp.placeholder = ph[c];
              inp.value = r[c] || '';
              inp.addEventListener('input', function () { r[c] = this.value; sync(); });
              item.appendChild(inp);
            })(c);
            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'row-del';
            del.title = 'Hapus baris';
            del.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            del.addEventListener('click', function () { rows.splice(i, 1); render(); });
            item.appendChild(del);
            list.appendChild(item);
          });
          sync();
        }

        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'row-add';
        addBtn.innerHTML = '<i class="fa-solid fa-plus"></i> Tambah';
        addBtn.addEventListener('click', function () {
          var r = [];
          for (var c = 0; c < cols; c++) r.push('');
          rows.push(r);
          render();
        });
        wrap.appendChild(addBtn);

        ta.insertAdjacentElement('afterend', wrap);
        ta.style.display = 'none';
        render();
      }
      document.querySelectorAll('[data-row-editor]').forEach(editor);
    })();

    (function () {
      var btn = document.getElementById('sbAddBtn');
      var frm = document.getElementById('sbAddForm');
      if (btn && frm) {
        btn.addEventListener('click', function () {
          frm.hidden = !frm.hidden;
          if (!frm.hidden) {
            var slug = frm.querySelector('input[name="new_slug"]');
            if (slug) slug.focus();
          }
        });
      }
    })();
  </script>

</body>
</html>
