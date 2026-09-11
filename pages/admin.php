<?php
/* ============================================================
 * admin.php — Panel admin Knowledge Base Leaders 3.
 * Kelola konten dari skema tunggal lib/kb.php (tambah/simpan
 * section). Data ditulis ke gmsapp:leaders_content yang SAMA
 * dengan leaders2 (satu sumber data).
 * ============================================================ */

require_admin();

require_once dirname(__DIR__) . '/lib/kb.php';

$content = get_leaders_content();

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

$sections = lget($content, 'sections', []);
$activeKey = isset($_GET['s']) ? $_GET['s'] : '';
if (!isset($sections[$activeKey])) {
  $activeKey = $sections ? array_key_first($sections) : '';
}
$sec = $sections[$activeKey] ?? [];

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
      } elseif (isset($content['sections'][$slug])) {
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

    // ---- Simpan konten section ----
    $postKey = $p('section', $activeKey);
    if (!isset($content['sections'][$postKey])) {
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

    // Section visibility (hanya diproses jika form mengirim penanda has_visibility)
    $hasVis = isset($_POST['has_visibility']);
    foreach ($content['sections'] as $sk => &$sv) {
      if ($hasVis) {
        $sv['visible'] = isset($_POST['visible_' . $sk]) ? true : false;
      }
    }
    unset($sv);

    // Identitas section
    $c = &$content['sections'][$postKey];
    $c['name'] = form_str($_POST, 'name', lget($sec, 'name'));
    $c['icon'] = form_str($_POST, 'icon', lget($sec, 'icon', 'fa-solid fa-file'));
    if ($hasVis) {
      $c['visible'] = isset($_POST['visible']) ? true : false;
    }

    // Field konten generik (keep-old: hanya menulis bila input tidak kosong,
    // key yang belum ada TIDAK dibuat supaya bentuk data lama tidak berubah)
    $ct = &$c['content'];
    foreach (kb_content_fields($postKey) as $field) {
      $fmeta = kb_content_meta($field);
      if (isset($_POST[$field]) && trim((string)$_POST[$field]) !== '') {
        $ct[$field] = $fmeta['type'] === 'lines' ? lines((string)$_POST[$field]) : trim((string)$_POST[$field]);
      }
    }
    unset($ct);

    // Subseksi generik (keep-old, mempertahankan bentuk data lama)
    $subDef = kb_subsection_def($postKey);
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
          $fname = $f[0];
          $ftype = $f[1];
          $in = 'sub_' . $slug . '_' . $fname;
          if (!array_key_exists($in, $_POST)) continue;
          $raw = trim((string)$_POST[$in]);
          if ($raw === '') continue;
          if ($ftype === 'lines') {
            $item[$fname] = lines($raw);
          } elseif ($ftype === 'pairs') {
            $item[$fname] = kb_parse_pairs(
              $raw,
              isset($f[3]) && $f[3] !== '' ? $f[3] : 'title',
              isset($f[4]) && $f[4] !== '' ? $f[4] : 'text'
            );
          } elseif ($ftype === 'int') {
            $item[$fname] = (int)$raw;
          } else {
            $item[$fname] = $raw;
          }
        }
        $container[$slug] = $item;
      }
      unset($container);
    }

    // Field level-section generik (msj: cek_kelulusan)
    foreach (kb_section_fields($postKey) as $sf) {
      $field = $sf['field'];
      $ftype = $sf['type'];
      $in = 'sec_' . $field;
      if (isset($_POST[$in]) && trim((string)$_POST[$in]) !== '') {
        $c[$field] = $ftype === 'lines' ? lines((string)$_POST[$in]) : trim((string)$_POST[$in]);
      }
    }

    // Link tutorial (cgt)
    if (kb_has_links($postKey)) {
      $linksRaw = isset($_POST['links_raw']) ? trim((string)$_POST['links_raw']) : '';
      if ($linksRaw !== '') {
        $c['links'] = kb_parse_pairs($linksRaw, 'label', 'url');
      }
    }

    // FAQ (keep-old)
    $faqRaw = $p('faq_items');
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

    if (!save_leaders_content($content)) {
      $error = 'Gagal menyimpan konten.';
    } else {
      $message = 'Konten ' . htmlspecialchars(lget($c, 'name', $postKey)) . ' berhasil disimpan.';
    }

    $sections = lget($content, 'sections', []);
    $sec = $sections[$postKey] ?? [];
    $activeKey = $postKey;
    }
    }
  }
}

$addedKey = isset($_GET['added']) ? (string)$_GET['added'] : '';
if ($addedKey !== '' && isset($sections[$addedKey])) {
  $message = 'Section "' . htmlspecialchars(lget($sections[$addedKey], 'name', $addedKey)) . '" berhasil ditambahkan. Pilih section lalu isi kontennya.';
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

    <!-- PILIH SECTION -->
    <div class="card-gms !pb-3">
      <p class="label-gms">Pilih Section yang Diedit</p>
      <div class="flex flex-wrap gap-2">
        <?php foreach ($sections as $key => $kc): ?>
          <a href="<?php echo app_url('admin?s=' . urlencode($key)); ?>"
             class="btn-switch <?php echo $key === $activeKey ? 'btn-switch-active' : 'btn-switch-inactive'; ?>">
            <i class="<?php echo htmlspecialchars(lget($kc, 'icon', 'fa-solid fa-file')); ?>"></i>
            <?php echo htmlspecialchars(lget($kc, 'name', $key)); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- TAMBAH SECTION -->
    <div class="card-gms !pb-3 mt-3">
      <p class="label-gms">Tambah Section Baru</p>
      <form method="POST" action="<?php echo app_url('admin'); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="form_version" value="<?php echo ADMIN_FORM_VERSION; ?>">
        <input type="hidden" name="action" value="add_section">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
          <div>
            <label class="label-gms">Slug</label>
            <input type="text" name="new_slug" class="input-gms" value="" placeholder="cth: baptisan_anak">
          </div>
          <div>
            <label class="label-gms">Nama</label>
            <input type="text" name="new_name" class="input-gms" value="" placeholder="cth: Baptisan Anak">
          </div>
          <div>
            <label class="label-gms">Ikon (Font Awesome)</label>
            <input type="text" name="new_icon" class="input-gms" value="" placeholder="fa-solid fa-baby">
          </div>
        </div>
        <p class="hint-gms mb-3">Slug: huruf kecil, angka, dan underscore saja (tanpa spasi). Ikon boleh dikosongkan (dipakai ikon default).</p>
        <div class="flex justify-end">
          <button type="submit" class="btn-gms-pill"><i class="fa-solid fa-plus"></i> Tambah Section</button>
        </div>
      </form>
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

    <form method="POST" action="<?php echo app_url('admin?s=' . urlencode($activeKey)); ?>" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="form_version" value="<?php echo ADMIN_FORM_VERSION; ?>">
      <input type="hidden" name="has_visibility" value="1">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="section" value="<?php echo htmlspecialchars($activeKey); ?>">

      <!-- VISIBILITAS SECTION -->
      <div class="card-gms">
        <div class="flex items-center gap-2 border-b border-[#e2e8f0] pb-3 mb-3">
          <i class="fa-solid fa-eye-slash text-[#0052cc]"></i>
          <span class="font-extrabold uppercase tracking-wide text-sm">Visibilitas Section</span>
        </div>
        <p class="text-xs text-[#5e6d82] m-0 mb-4">Section yang dinonaktifkan tidak akan tampil di halaman publik.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <?php foreach ($sections as $sk => $sv): ?>
            <label class="flex items-center justify-between gap-3 bg-[#f8fafc] border border-[#e2e8f0] rounded-xl px-4 py-3 cursor-pointer select-none">
              <span class="flex items-center gap-2 text-sm font-bold text-[#18233b]">
                <i class="<?php echo htmlspecialchars(lget($sv, 'icon', 'fa-solid fa-file')); ?> text-[#0052cc]"></i>
                <?php echo htmlspecialchars(lget($sv, 'name', $sk)); ?>
              </span>
              <input type="checkbox" name="visible_<?php echo htmlspecialchars($sk); ?>" value="1" class="sr-only"
                     <?php echo lget($sv, 'visible', true) ? 'checked' : ''; ?>>
              <span class="switch"></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- SETTING GLOBAL -->
      <div class="card-gms">
        <div class="flex items-center gap-2 border-b border-[#e2e8f0] pb-3 mb-3">
          <i class="fa-solid fa-sliders text-[#0052cc]"></i>
          <span class="font-extrabold uppercase tracking-wide text-sm">SETTING HALAMAN</span>
        </div>
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
          <textarea name="site_welcome_notes" class="input-gms" rows="4"><?php echo htmlspecialchars(implode("\n", lget($site, 'welcome_notes', []))); ?></textarea>
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
      </div>

      <!-- IDENTITAS SECTION -->
      <div class="card-gms">
        <div class="flex items-center gap-2 border-b border-[#e2e8f0] pb-3 mb-3">
          <i class="fa-solid fa-tag text-[#0052cc]"></i>
          <span class="font-extrabold uppercase tracking-wide text-sm">IDENTITAS SECTION</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="label-gms">Nama Section</label>
            <input type="text" name="name" class="input-gms" value="<?php echo htmlspecialchars(lget($sec, 'name')); ?>">
          </div>
          <div>
            <label class="label-gms">Icon (Font Awesome class)</label>
            <input type="text" name="icon" class="input-gms" value="<?php echo htmlspecialchars(lget($sec, 'icon')); ?>" placeholder="fa-solid fa-book">
          </div>
          <div class="flex items-end">
            <label class="flex items-center justify-between gap-3 bg-[#f8fafc] border border-[#e2e8f0] rounded-xl px-4 py-3 cursor-pointer select-none w-full">
              <span class="flex items-center gap-2 text-sm font-bold text-[#18233b]">
                <i class="fa-solid fa-eye text-[#0052cc]"></i> Visible
              </span>
              <input type="checkbox" name="visible" value="1" class="sr-only"
                     <?php echo lget($sec, 'visible', true) ? 'checked' : ''; ?>>
              <span class="switch"></span>
            </label>
          </div>
        </div>
      </div>

      <!-- KONTEN SECTION (skema tunggal) -->
      <div class="card-gms">
        <div class="flex items-center gap-2 border-b border-[#e2e8f0] pb-3 mb-3">
          <i class="fa-solid fa-file-lines text-[#0052cc]"></i>
          <span class="font-extrabold uppercase tracking-wide text-sm">Konten Section</span>
        </div>
        <?php foreach (kb_content_fields($activeKey) as $cvField):
          $cvMeta = kb_content_meta($cvField);
          $cvVal = kb_field_value($sec, $cvField);
          $cvPre = is_array($cvVal) ? implode("\n", array_map(function ($x) {
            return is_array($x) ? (string)lget($x, 'title', (string)lget($x, 'text', '')) : (string)$x;
          }, $cvVal)) : (string)$cvVal;
        ?>
        <div class="mb-4">
          <label class="label-gms"><?php echo htmlspecialchars($cvMeta['label']); ?></label>
          <?php if ($cvMeta['input'] === 'textarea'): ?>
          <textarea name="<?php echo htmlspecialchars($cvField); ?>" class="input-gms" rows="<?php echo (int)$cvMeta['rows']; ?>"><?php echo htmlspecialchars($cvPre); ?></textarea>
          <?php else: ?>
          <input type="<?php echo $cvMeta['input'] === 'url' ? 'url' : 'text'; ?>" name="<?php echo htmlspecialchars($cvField); ?>" class="input-gms" value="<?php echo htmlspecialchars($cvPre); ?>">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- SUBSEKSI (skema tunggal) -->
      <?php $subDef = kb_subsection_def($activeKey); if ($subDef): ?>
      <div class="card-gms">
        <div class="flex items-center gap-2 border-b border-[#e2e8f0] pb-3 mb-3">
          <i class="fa-solid fa-layer-group text-[#0052cc]"></i>
          <span class="font-extrabold uppercase tracking-wide text-sm">Subseksi</span>
        </div>
        <?php foreach ($subDef['blocks'] as $subSlug => $subBlock): ?>
          <?php $subCur = kb_get_sub($activeKey, $sec, $subSlug); ?>
          <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded-xl p-4 mb-4 last:mb-0">
            <h4 class="text-sm font-extrabold text-[#003399] m-0 mb-3"><?php echo htmlspecialchars($subBlock['label']); ?></h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <?php foreach ($subBlock['fields'] as $sf): ?>
                <?php
                  $sfName = $sf[0];
                  $sfType = $sf[1];
                  $sfLabel = isset($sf[2]) ? $sf[2] : $sfName;
                  $sfInput = 'sub_' . $subSlug . '_' . $sfName;
                  $sv = lget($subCur, $sfName);
                  $svPre = '';
                  if ($sfType === 'lines') {
                    $svPre = is_array($sv) ? implode("\n", $sv) : (string)$sv;
                  } elseif ($sfType === 'pairs' && is_array($sv)) {
                    $svPre = kb_encode_pairs($sv, isset($sf[3]) && $sf[3] !== '' ? $sf[3] : 'title', isset($sf[4]) && $sf[4] !== '' ? $sf[4] : 'text');
                  } else {
                    $svPre = (string)$sv;
                  }
                ?>
                <div class="<?php echo in_array($sfType, ['lines', 'pairs'], true) ? 'md:col-span-2' : ''; ?> mb-3">
                  <label class="label-gms"><?php echo htmlspecialchars($sfLabel); ?></label>
                  <?php if ($sfType === 'lines' || $sfType === 'pairs'): ?>
                  <textarea name="<?php echo htmlspecialchars($sfInput); ?>" class="input-gms" rows="3"><?php echo htmlspecialchars($svPre); ?></textarea>
                  <?php elseif ($sfType === 'int'): ?>
                  <input type="number" name="<?php echo htmlspecialchars($sfInput); ?>" class="input-gms" value="<?php echo htmlspecialchars($svPre); ?>">
                  <?php else: ?>
                  <input type="text" name="<?php echo htmlspecialchars($sfInput); ?>" class="input-gms" value="<?php echo htmlspecialchars($svPre); ?>">
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- FIELD LEVEL SECTION (skema tunggal) -->
      <?php $kbSecFields = kb_section_fields($activeKey); if ($kbSecFields): ?>
      <div class="card-gms">
        <div class="flex items-center gap-2 border-b border-[#e2e8f0] pb-3 mb-3">
          <i class="fa-solid fa-graduation-cap text-[#0052cc]"></i>
          <span class="font-extrabold uppercase tracking-wide text-sm">Informasi Kelulusan</span>
        </div>
        <?php foreach ($kbSecFields as $sf): ?>
        <div class="mb-4">
          <label class="label-gms"><?php echo htmlspecialchars(lget($sf, 'label')); ?></label>
          <textarea name="sec_<?php echo htmlspecialchars(lget($sf, 'field')); ?>" class="input-gms" rows="6"><?php echo htmlspecialchars(implode("\n", lget($sec, lget($sf, 'field'), []))); ?></textarea>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- LINKS (skema tunggal) -->
      <?php if (kb_has_links($activeKey)): ?>
      <div class="card-gms">
        <div class="flex items-center gap-2 border-b border-[#e2e8f0] pb-3 mb-3">
          <i class="fa-solid fa-link text-[#0052cc]"></i>
          <span class="font-extrabold uppercase tracking-wide text-sm">Link Tutorial</span>
        </div>
        <label class="label-gms">Link Tutorial (satu baris per link, format: Label &lt;TAB&gt; URL)</label>
        <textarea name="links_raw" class="input-gms" rows="4"><?php echo htmlspecialchars(kb_encode_pairs(lget($sec, 'links', []), 'label', 'url')); ?></textarea>
      </div>
      <?php endif; ?>

      <!-- FAQ -->
      <div class="card-gms">
        <div class="section-title-wrap !pt-0 !pb-3">
          <h3 class="page-title text-sm"><i class="fa-solid fa-circle-question mr-1 text-[#0052cc]"></i> FAQ</h3>
          <div class="blue-indicator"></div>
        </div>
        <label class="label-gms">FAQ Items (satu baris per item, format: Pertanyaan &lt;TAB&gt; Jawaban)</label>
        <textarea name="faq_items" class="input-gms" rows="8"><?php
          $faqArr = lget($sec, 'faq', []);
          $faqLines = [];
          foreach ($faqArr as $fq) {
            $faqLines[] = lget($fq, 'q', '') . "\t" . lget($fq, 'a', '');
          }
          echo htmlspecialchars(implode("\n", $faqLines));
        ?></textarea>
        <p class="hint-gms">Contoh: Bagaimana cara daftar?&lt;TAB&gt;Buka aplikasi GMS Church lalu pilih menu MSJ. (Pemisahnya tombol Tab, bukan tanda kurung siku.)</p>
      </div>

      <!-- SUBMIT -->
      <div class="card-gms flex flex-col sm:flex-row gap-3 items-center justify-between">
        <p class="text-xs text-[#5e6d82] m-0">Perubahan akan langsung tampil di halaman publik setelah disimpan.</p>
        <button type="submit" class="btn-gms-pill">
          <i class="fa-solid fa-floppy-disk"></i> Simpan Konten
        </button>
      </div>

    </form>

  </main>

  <?php gms_bottom_nav('admin'); ?>

</body>
</html>
