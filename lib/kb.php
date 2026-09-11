<?php
/* ============================================================
 * kb.php — Skema tunggal Knowledge Base Leaders 3.
 *
 * Satu sumber kebenaran untuk bentuk konten semua section:
 *   - lib/kb.php        : definisi field + parser keep-old + renderer
 *   - pages/admin.php   : editor otomatis dari skema ini
 *   - pages/home.php    : renderer publik dari skema ini
 *
 * Menambah section baru cukup lewat tombol "Tambah Section" di
 * admin; semua field memakai pola yang sama persis. Data lama
 * TIDAK pernah diubah bentuk: parser hanya menulis saat input
 * POST tidak kosong (keep-old), dan tidak membuat key baru.
 *
 * Bagian skema/parser TIDAK diubah dari leaders2 karena memakai
 * gmsapp:leaders_content yang SAMA. Hanya renderer publik yang
 * diganti menjadi markup semantik (digerakkan public/css/app.css)
 * agar tampilan leaders3 modern & mudah disesuaikan.
 * ============================================================ */

function kb_lget($arr, $key, $default = '') {
  return isset($arr[$key]) ? $arr[$key] : $default;
}

/** Pecah textarea menjadi daftar baris bersih (abaikan baris kosong). */
function kb_lines($str) {
  $parts = preg_split('/\r\n|\r|\n/', trim((string)$str));
  $out = [];
  foreach ($parts as $p) {
    $p = trim($p);
    if ($p !== '') $out[] = $p;
  }
  return $out;
}

/** Parse textarea "k1<tab>k2" per baris menjadi daftar pasangan. */
function kb_parse_pairs(string $raw, string $k1, string $k2): array {
  $out = [];
  foreach (kb_lines($raw) as $l) {
    $parts = explode("\t", $l, 2);
    if (count($parts) === 2) {
      $out[] = [$k1 => trim($parts[0]), $k2 => trim($parts[1])];
    }
  }
  return $out;
}

/** Ubah daftar pasangan menjadi teks "k1<tab>k2" per baris. */
function kb_encode_pairs(array $items, string $k1, string $k2): string {
  $out = [];
  foreach ($items as $it) {
    if (!is_array($it)) continue;
    $out[] = kb_lget($it, $k1, '') . "\t" . kb_lget($it, $k2, '');
  }
  return implode("\n", $out);
}

/** Normalkan nilai ke daftar baris untuk keperluan tampil (array atau string). */
function kb_lines_value($val): array {
  if (is_array($val)) {
    $out = [];
    foreach ($val as $v) {
      if (is_array($v)) {
        $out[] = (string)kb_lget($v, 'text', (string)kb_lget($v, 'title', ''));
      } else {
        $out[] = (string)$v;
      }
    }
    return array_values(array_filter($out, function ($s) { return trim($s) !== ''; }));
  }
  $out = [];
  foreach (preg_split('/\r\n|\r|\n/', (string)$val) as $l) {
    $l = trim($l);
    if ($l !== '') $out[] = $l;
  }
  return $out;
}

/* ----------------------- Meta & definisi field ----------------------- */

/** Daftar field konten (level content) yang dipakai semua section. */
function kb_content_fields(string $key): array {
  $fields = [
    'intro', 'link_url', 'link_label',
    'definisi', 'syarat', 'cara_cek',
    'syarat_sertifikat', 'data_sertifikat',
    'alur', 'why_active_cg', 'cek_kelulusan', 'questions',
  ];
  if ($key === 'pernikahan') $fields = array_merge($fields, ['tutup', 'tutup2']);
  if ($key === 'msj') $fields = array_values(array_diff($fields, ['cek_kelulusan']));
  return $fields;
}

/** Meta setiap field konten: tipe simpan, input form, label, ikon, judul blok publik, gaya tampil. */
function kb_content_meta(string $field): array {
  static $meta = null;
  if ($meta === null) {
    $meta = [
      'intro' => ['type' => 'text', 'input' => 'textarea', 'rows' => 4, 'label' => 'Intro / Deskripsi', 'icon' => 'fa-solid fa-circle-info', 'title' => '', 'style' => 'text'],
      'link_url' => ['type' => 'text', 'input' => 'url', 'rows' => 1, 'label' => 'URL Link (YouTube, dll)', 'icon' => 'fa-brands fa-youtube', 'title' => '', 'style' => ''],
      'link_label' => ['type' => 'text', 'input' => 'text', 'rows' => 1, 'label' => 'Label Link', 'icon' => 'fa-solid fa-tag', 'title' => '', 'style' => ''],
      'definisi' => ['type' => 'lines', 'input' => 'textarea', 'rows' => 3, 'label' => 'Definisi (satu baris per item)', 'icon' => 'fa-solid fa-book', 'title' => 'Definisi', 'style' => 'check'],
      'syarat' => ['type' => 'lines', 'input' => 'textarea', 'rows' => 5, 'label' => 'Syarat (satu baris per syarat)', 'icon' => 'fa-solid fa-clipboard-list', 'title' => 'Persyaratan', 'style' => 'num'],
      'cara_cek' => ['type' => 'lines', 'input' => 'textarea', 'rows' => 3, 'label' => 'Cara Cek (satu baris per langkah)', 'icon' => 'fa-solid fa-magnifying-glass', 'title' => 'Cara Cek', 'style' => 'num'],
      'syarat_sertifikat' => ['type' => 'lines', 'input' => 'textarea', 'rows' => 3, 'label' => 'Syarat Sertifikat (satu baris per syarat)', 'icon' => 'fa-solid fa-file-certificate', 'title' => 'Syarat Sertifikat', 'style' => 'num'],
      'data_sertifikat' => ['type' => 'text', 'input' => 'textarea', 'rows' => 3, 'label' => 'Data yang Perlu Dilengkapi', 'icon' => 'fa-solid fa-triangle-exclamation', 'title' => 'Data yang Perlu Dilengkapi', 'style' => 'amber'],
      'alur' => ['type' => 'lines', 'input' => 'textarea', 'rows' => 8, 'label' => 'Alur / Urutan Pendaftaran (satu baris per langkah)', 'icon' => 'fa-solid fa-list-ol', 'title' => 'Alur', 'style' => 'num'],
      'why_active_cg' => ['type' => 'lines', 'input' => 'textarea', 'rows' => 4, 'label' => 'Mengapa Aktif di CG (satu baris per alasan)', 'icon' => 'fa-solid fa-people-group', 'title' => 'Mengapa Harus Aktif di CG', 'style' => 'dot'],
      'cek_kelulusan' => ['type' => 'lines', 'input' => 'textarea', 'rows' => 6, 'label' => 'Cara Cek Kelulusan (satu baris per langkah)', 'icon' => 'fa-solid fa-graduation-cap', 'title' => 'Cara Cek Kelulusan', 'style' => 'num'],
      'questions' => ['type' => 'lines', 'input' => 'textarea', 'rows' => 12, 'label' => '12 Pertanyaan (satu baris per pertanyaan)', 'icon' => 'fa-solid fa-list-ol', 'title' => '12 Pertanyaan untuk Calon Sponsor & CGL', 'style' => 'num_card'],
      'tutup' => ['type' => 'text', 'input' => 'textarea', 'rows' => 2, 'label' => 'Kata Penutup', 'icon' => 'fa-solid fa-heart', 'title' => '', 'style' => 'text'],
      'tutup2' => ['type' => 'text', 'input' => 'textarea', 'rows' => 1, 'label' => 'Kata Penutup (baris kedua — tebal)', 'icon' => 'fa-solid fa-heart', 'title' => '', 'style' => 'bold'],
    ];
  }
  return $meta[$field] ?? ['type' => 'text', 'input' => 'textarea', 'rows' => 2, 'label' => ucfirst($field), 'icon' => 'fa-solid fa-file', 'title' => '', 'style' => 'text'];
}

/** Judul blok publik per field; ada override agar sesuai judul yang sudah dipakai live. */
function kb_field_title(string $key, string $field): string {
  static $ov = null;
  if ($ov === null) {
    $ov = [
      'nij' => ['syarat' => 'Persyaratan Mendapatkan NIJ', 'cara_cek' => 'Cara Mengecek Persyaratan NIJ'],
      'ministry' => ['syarat' => 'Syarat Pelayanan', 'alur' => 'Urutan Pendaftaran Pelayanan', 'why_active_cg' => 'Mengapa Volunteer Harus Aktif di CG?'],
      'baptisan' => ['syarat' => 'Syarat Baptisan', 'syarat_sertifikat' => 'Syarat Request Sertifikat Baptisan'],
      'twelve_questions' => ['questions' => '12 Pertanyaan untuk Calon Sponsor & CGL'],
    ];
  }
  return isset($ov[$key][$field]) ? $ov[$key][$field] : kb_content_meta($field)['title'];
}

/* ----------------------- Definisi subseksi ----------------------- */

/**
 * Definisikan subseksi per section.
 *  scope : 'section' (sec.subsections) | 'content' (content.subsections)
 *  blocks: [slug => ['label', 'icon', 'fields' => [ [name, type, label, style?, display_title?] ]]]
 *  type field: text | lines | int | pairs (item "k1<tab>k2" per baris, nama kunci via index 3 & 4)
 */
function kb_subsection_def(string $key): ?array {
  static $defs = null;
  if ($defs === null) {
    $defs = [
      'mentoring' => [
        'scope' => 'content',
        'blocks' => [
          'pranikah' => ['label' => 'Mentoring Pra-Nikah', 'icon' => 'fa-solid fa-heart', 'fields' => [
            ['title', 'text', 'Judul Mentoring Pra-Nikah'],
            ['points', 'lines', 'Poin Pra-Nikah (satu baris per poin)', 'dot', ''],
          ]],
          'peneguhan' => ['label' => 'Peneguhan Pacaran', 'icon' => 'fa-solid fa-heart', 'fields' => [
            ['title', 'text', 'Judul Peneguhan Pacaran'],
            ['points', 'lines', 'Poin Peneguhan (satu baris per poin)', 'dot', ''],
          ]],
        ],
      ],
      'budaya' => [
        'scope' => 'section',
        'blocks' => [
          'welcome_home' => ['label' => 'Welcome Home', 'icon' => 'fa-solid fa-house', 'fields' => [
            ['title', 'text', 'Judul'], ['text', 'text', 'Isi / Deskripsi'],
          ]],
          'saya_gms' => ['label' => 'Saya GMS', 'icon' => 'fa-solid fa-star', 'fields' => [
            ['title', 'text', 'Judul'], ['text', 'text', 'Isi / Deskripsi'],
            ['items', 'pairs', 'Item (Judul &lt;TAB&gt; Isi, satu baris per item)', 'title', 'text'],
          ]],
          'integritas' => ['label' => 'Integritas', 'icon' => 'fa-solid fa-shield-halved', 'fields' => [
            ['title', 'text', 'Judul'], ['text', 'text', 'Isi / Deskripsi'],
          ]],
          'penundukan' => ['label' => 'Penundukan', 'icon' => 'fa-solid fa-hand', 'fields' => [
            ['title', 'text', 'Judul'], ['text', 'text', 'Isi / Deskripsi'],
          ]],
          'hubungan' => ['label' => 'Hubungan', 'icon' => 'fa-solid fa-people-group', 'fields' => [
            ['title', 'text', 'Judul'], ['text', 'text', 'Isi / Deskripsi'],
          ]],
          'memberi' => ['label' => 'Memberi', 'icon' => 'fa-solid fa-hand-holding-heart', 'fields' => [
            ['title', 'text', 'Judul'], ['text', 'text', 'Isi / Deskripsi'],
          ]],
          'pembapaan' => ['label' => 'Pembapaan', 'icon' => 'fa-solid fa-dove', 'fields' => [
            ['title', 'text', 'Judul'],
            ['text', 'text', 'Isi / Deskripsi'],
            ['contoh', 'lines', 'Contoh (satu baris per contoh)', 'circle', 'Contoh Pembapaan Rohani dalam Alkitab:'],
            ['umum', 'text', 'Penjelasan umum', 'callout', ''],
            ['khusus', 'lines', 'Pembapaan secara khusus (satu baris per poin)', 'check', 'Pembapaan rohani secara khusus adalah:'],
          ]],
          'pinjam_meminjam' => ['label' => 'Pinjam Meminjam', 'icon' => 'fa-solid fa-handshake', 'fields' => [
            ['title', 'text', 'Judul'], ['text', 'text', 'Isi / Deskripsi'],
          ]],
          'produk' => ['label' => 'Produk', 'icon' => 'fa-solid fa-box-open', 'fields' => [
            ['title', 'text', 'Judul'],
            ['points', 'lines', 'Poin (satu baris per poin)', 'dot', ''],
          ]],
        ],
      ],
      'msj' => [
        'scope' => 'section',
        'blocks' => [
          'msj1' => ['label' => 'MSJ 1', 'icon' => 'fa-solid fa-book-open', 'fields' => [
            ['name', 'text', 'Nama'],
            ['jumlah', 'int', 'Jumlah Materi'],
            ['materials', 'lines', 'Materi (satu baris per materi)', 'check', ''],
            ['syarat', 'lines', 'Persyaratan (satu baris per syarat)', 'graybox_num', ''],
          ]],
          'msj2' => ['label' => 'MSJ 2', 'icon' => 'fa-solid fa-book-open', 'fields' => [
            ['name', 'text', 'Nama'],
            ['jumlah', 'int', 'Jumlah Materi'],
            ['materials', 'lines', 'Materi (satu baris per materi)', 'check', ''],
            ['syarat', 'lines', 'Persyaratan (satu baris per syarat)', 'graybox_num', ''],
          ]],
          'msj3' => ['label' => 'MSJ 3', 'icon' => 'fa-solid fa-book-open', 'fields' => [
            ['name', 'text', 'Nama'],
            ['jumlah', 'int', 'Jumlah Materi'],
            ['materials', 'lines', 'Materi (satu baris per materi)', 'check', ''],
            ['syarat', 'lines', 'Persyaratan (satu baris per syarat)', 'graybox_num', ''],
          ]],
        ],
      ],
      'cgt' => [
        'scope' => 'section',
        'blocks' => [
          'cgt1' => ['label' => 'CGT 1', 'icon' => 'fa-solid fa-chalkboard-user', 'fields' => [
            ['name', 'text', 'Nama'],
            ['jumlah', 'int', 'Jumlah Materi'],
            ['materials', 'lines', 'Materi (satu baris per materi)', 'check', ''],
            ['syarat', 'lines', 'Persyaratan (satu baris per syarat)', 'graybox_circle', ''],
          ]],
          'cgt2' => ['label' => 'CGT 2', 'icon' => 'fa-solid fa-chalkboard-user', 'fields' => [
            ['name', 'text', 'Nama'],
            ['jumlah', 'int', 'Jumlah Materi'],
            ['materials', 'lines', 'Materi (satu baris per materi)', 'check', ''],
            ['syarat', 'lines', 'Persyaratan (satu baris per syarat)', 'graybox_circle', ''],
          ]],
          'cgt3' => ['label' => 'CGT 3', 'icon' => 'fa-solid fa-chalkboard-user', 'fields' => [
            ['name', 'text', 'Nama'],
            ['jumlah', 'int', 'Jumlah Materi'],
            ['materials', 'lines', 'Materi (satu baris per materi)', 'check', ''],
            ['syarat', 'lines', 'Persyaratan (satu baris per syarat)', 'graybox_circle', ''],
          ]],
          'cgt4' => ['label' => 'CGT 4', 'icon' => 'fa-solid fa-chalkboard-user', 'fields' => [
            ['name', 'text', 'Nama'],
            ['jumlah', 'int', 'Jumlah Materi'],
            ['materials', 'lines', 'Materi (satu baris per materi)', 'check', ''],
            ['syarat', 'lines', 'Persyaratan (satu baris per syarat)', 'graybox_circle', ''],
          ]],
        ],
      ],
      'pernikahan' => [
        'scope' => 'section',
        'blocks' => [
          'single' => ['label' => 'Pemberkatan Nikah (Single)', 'icon' => 'fa-solid fa-ring', 'fields' => [
            ['title', 'text', 'Judul'],
            ['syarat', 'lines', 'Syarat (satu baris per syarat)', 'num', ''],
          ]],
          'divorce' => ['label' => 'Pemberkatan Nikah (Cerai Mati)', 'icon' => 'fa-solid fa-ring', 'fields' => [
            ['title', 'text', 'Judul'],
            ['syarat', 'lines', 'Syarat (satu baris per syarat)', 'num', ''],
          ]],
        ],
      ],
    ];
  }
  return $defs[$key] ?? null;
}

/** Field level section (bukan content). msj menyimpan cek_kelulusan di level section. */
function kb_section_fields(string $key): array {
  if ($key === 'msj') {
    return [[
      'field' => 'cek_kelulusan',
      'type' => 'lines',
      'label' => 'Cara Cek Kelulusan MSJ (satu baris per langkah)',
      'title' => 'Cara Cek Kelulusan MSJ',
      'icon' => 'fa-solid fa-graduation-cap',
      'style' => 'num',
    ]];
  }
  return [];
}

function kb_has_links(string $key): bool {
  return $key === 'cgt';
}

/** Baca nilai field konten dari sebuah section. */
function kb_field_value(array $sec, string $field) {
  return kb_lget(kb_lget($sec, 'content', []), $field);
}

/** Baca subseksi tertentu sesuai scope definisi. */
function kb_get_sub(string $key, array $sec, string $slug): array {
  $def = kb_subsection_def($key);
  if (!$def) return [];
  $container = $def['scope'] === 'content'
    ? kb_lget(kb_lget($sec, 'content', []), 'subsections', [])
    : kb_lget($sec, 'subsections', []);
  return kb_lget($container, $slug, []);
}

function kb_sub_heading(array $block, array $sub): string {
  if (isset($sub['name']) && trim((string)$sub['name']) !== '') return (string)$sub['name'];
  if (isset($sub['title']) && trim((string)$sub['title']) !== '') return (string)$sub['title'];
  return $block['label'];
}

/* ----------------------- Renderer publik ----------------------- */
/* Markup semantik; lihat public/css/app.css untuk styling tema.  */

function kb_card_open(string $icon, string $title): void {
  echo '<div class="kb-card">';
  echo '<div class="kb-head">';
  echo '<span class="kb-head-icon"><i class="' . htmlspecialchars($icon) . '"></i></span>';
  echo '<h2 class="kb-title">' . htmlspecialchars($title) . '</h2>';
  echo '</div>';
}

function kb_render_list(array $lines, string $style): void {
  if (!$lines) return;
  switch ($style) {
    case 'num':
      echo '<ol class="kb-list kb-list-num">';
      foreach ($lines as $l) echo '<li>' . htmlspecialchars((string)$l) . '</li>';
      echo '</ol>';
      break;
    case 'check':
      echo '<ul class="kb-list kb-list-check">';
      foreach ($lines as $l) echo '<li>' . htmlspecialchars((string)$l) . '</li>';
      echo '</ul>';
      break;
    case 'circle':
      echo '<ul class="kb-list kb-list-circle">';
      foreach ($lines as $l) echo '<li>' . htmlspecialchars((string)$l) . '</li>';
      echo '</ul>';
      break;
    case 'dot':
      echo '<ul class="kb-list kb-list-dot">';
      foreach ($lines as $l) echo '<li>' . htmlspecialchars((string)$l) . '</li>';
      echo '</ul>';
      break;
    case 'num_card':
      echo '<ol class="kb-list kb-list-numcard">';
      foreach ($lines as $l) echo '<li>' . htmlspecialchars((string)$l) . '</li>';
      echo '</ol>';
      break;
  }
}

/** Kata pembuka section + tombol link (YouTube, dll). */
function kb_render_intro(array $sec): void {
  $data = kb_lget($sec, 'content', []);
  $intro = kb_lget($data, 'intro', '');
  if ((string)$intro === '') return;
  $url = (string)kb_lget($data, 'link_url');
  echo '<div class="kb-card kb-intro">';
  echo '<p class="kb-intro-text">' . htmlspecialchars((string)$intro) . '</p>';
  if ($url !== '') {
    $label = (string)kb_lget($data, 'link_label', 'Tonton');
    echo '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener" class="kb-linkbtn">';
    echo '<i class="fa-brands fa-youtube"></i> ' . htmlspecialchars($label);
    echo '</a>';
  }
  echo '</div>';
}

/** Kartu untuk satu field konten (dilewati bila kosong). */
function kb_render_content_card(string $key, string $field, array $sec): void {
  $meta = kb_content_meta($field);
  $lines = kb_lines_value(kb_field_value($sec, $field));
  if (!$lines) return;
  $title = kb_field_title($key, $field);
  kb_card_open($meta['icon'], $title);
  if ($meta['style'] === 'amber') {
    echo '<div class="kb-amber">';
    echo '<p class="kb-amber-title"><i class="fa-solid fa-triangle-exclamation"></i> Data yang perlu dilengkapi:</p>';
    echo '<ul class="kb-amber-list">';
    foreach ($lines as $l) echo '<li>' . htmlspecialchars((string)$l) . '</li>';
    echo '</ul></div>';
  } else {
    kb_render_list($lines, $meta['style']);
  }
  echo '</div>';
}

/** Kartu satu blok subseksi. */
function kb_render_subsection_block(array $block, array $sub): void {
  if (!$sub) return;
  kb_card_open((string)kb_lget($block, 'icon', 'fa-solid fa-file'), kb_sub_heading($block, $sub));
  foreach ($block['fields'] as $f) {
    $fname = $f[0];
    if ($fname === 'name' || $fname === 'title') continue;
    if (!array_key_exists($fname, $sub)) continue;
    $ftype = $f[1];
    $label = $f[2] ?? '';
    $style = $f[3] ?? '';
    $dTitle = isset($f[4]) && $f[4] !== '' ? $f[4] : null;

    if ($ftype === 'pairs') {
      $items = $sub[$fname];
      if (!$items) continue;
      if ($dTitle !== null) echo '<p class="kb-eyebrow">' . htmlspecialchars($dTitle) . '</p>';
      echo '<div class="kb-pairs">';
      foreach ($items as $it) {
        if (!is_array($it)) continue;
        echo '<div class="kb-pair">';
        echo '<h4 class="kb-pair-title">' . htmlspecialchars((string)kb_lget($it, 'title')) . '</h4>';
        echo '<p class="kb-pair-text">' . htmlspecialchars((string)kb_lget($it, 'text')) . '</p>';
        echo '</div>';
      }
      echo '</div>';
      continue;
    }

    $val = $sub[$fname];
    if ($ftype === 'int') {
      echo '<p class="kb-stat"><i class="fa-solid fa-layer-group"></i> ' . htmlspecialchars((string)$label) . ': <strong>' . (int)$val . '</strong></p>';
      continue;
    }
    $lines = kb_lines_value($val);
    if (!$lines) continue;
    if ($style === 'graybox_num' || $style === 'graybox_circle') {
      echo '<div class="kb-graybox">';
      echo '<p class="kb-graybox-label">' . htmlspecialchars((string)$label) . ':</p>';
      kb_render_list($lines, $style === 'graybox_num' ? 'num' : 'circle');
      echo '</div>';
      continue;
    }
    if ($style === 'callout') {
      echo '<div class="kb-callout">' . htmlspecialchars((string)$val) . '</div>';
      continue;
    }
    if ($dTitle !== null) {
      echo '<p class="kb-eyebrow">' . htmlspecialchars((string)$dTitle) . '</p>';
    }
    kb_render_list($lines, $style !== '' ? $style : 'dot');
  }
  echo '</div>';
}

function kb_render_subsections(string $key, array $sec): void {
  $def = kb_subsection_def($key);
  if (!$def) return;
  foreach ($def['blocks'] as $slug => $block) {
    $sub = kb_get_sub($key, $sec, $slug);
    if ($sub) kb_render_subsection_block($block, $sub);
  }
}

/** Kartu penutup pernikahan (tutup + tutup2). */
function kb_render_closing(array $sec): void {
  $data = kb_lget($sec, 'content', []);
  $tutup = kb_lget($data, 'tutup', '');
  if ((string)$tutup === '') return;
  echo '<div class="kb-card kb-closing">';
  echo '<p class="kb-closing-line">' . htmlspecialchars((string)$tutup) . '</p>';
  $tutup2 = kb_lget($data, 'tutup2', '');
  if ((string)$tutup2 !== '') {
    echo '<p class="kb-closing-strong">' . htmlspecialchars((string)$tutup2) . '</p>';
  }
  echo '</div>';
}

function kb_faq_title(string $key, array $sec): string {
  if ($key === 'gms_menjawab') return 'Jawaban atas Pertanyaan yang Ditujukan kepada GMS/ROSC';
  if ($key === 'aplikasi_gms') return 'FAQ Aplikasi GMS Church';
  return 'FAQ ' . kb_lget($sec, 'name', $key);
}

function kb_render_faq(string $key, array $sec): void {
  $faq = kb_lget($sec, 'faq', []);
  if (!$faq) return;
  kb_card_open('fa-solid fa-circle-question', kb_faq_title($key, $sec));
  echo '<div class="kb-faq">';
  foreach ($faq as $item) {
    $q = (string)kb_lget($item, 'q');
    $a = (string)kb_lget($item, 'a');
    echo '<div class="faq-item">';
    echo '<button type="button" class="faq-q" onclick="toggleFaq(this)">';
    echo '<span>' . htmlspecialchars($q) . '</span><i class="fa-solid fa-chevron-down"></i>';
    echo '</button>';
    echo '<div class="faq-a"><p>' . htmlspecialchars($a) . '</p></div>';
    echo '</div>';
  }
  echo '</div></div>';
}

function kb_render_links(array $sec): void {
  $links = kb_lget($sec, 'links', []);
  if (!$links) return;
  kb_card_open('fa-solid fa-link', 'Link Tutorial');
  echo '<div class="kb-linklist">';
  foreach ($links as $link) {
    if (!is_array($link)) continue;
    echo '<a href="' . htmlspecialchars((string)kb_lget($link, 'url')) . '" target="_blank" rel="noopener" class="kb-linkbtn">';
    echo '<i class="fa-brands fa-youtube"></i> ' . htmlspecialchars((string)kb_lget($link, 'label'));
    echo '</a>';
  }
  echo '</div></div>';
}

/**
 * Render seluruh isi section secara generik dari skema.
 * Panggil dari dalam wrapper konten (mis. <div class="wrap kb-content">).
 */
function kb_render_section(string $key, array $sec): void {
  kb_render_intro($sec);
  foreach (kb_content_fields($key) as $field) {
    if (in_array($field, ['intro', 'link_url', 'link_label', 'tutup', 'tutup2'], true)) continue;
    kb_render_content_card($key, $field, $sec);
  }
  kb_render_subsections($key, $sec);
  kb_render_closing($sec);
  kb_render_faq($key, $sec);
  foreach (kb_section_fields($key) as $sf) {
    $lines = kb_lines_value(kb_lget($sec, $sf['field'], []));
    if (!$lines) continue;
    kb_card_open((string)kb_lget($sf, 'icon', 'fa-solid fa-file'), (string)kb_lget($sf, 'title'));
    kb_render_list($lines, (string)kb_lget($sf, 'style', 'num'));
    echo '</div>';
  }
  if (kb_has_links($key)) kb_render_links($sec);
}