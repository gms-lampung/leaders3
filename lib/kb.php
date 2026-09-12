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
 * bentuk konten yang sama. Data leaders3 disimpan di key Redis
 * terpisah (gmsapp:leaders3_content) agar tidak saling menimpa
 * konten leaders2. Renderer publik diganti menjadi markup semantik
 * (digerakkan public/css/app.css) agar tampilan leaders3 modern
 * & mudah disesuaikan.
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
    echo '<div class="faq-a">';
    foreach (preg_split('/\r\n|\r|\n/', $a) as $ap) {
      $ap = trim($ap);
      if ($ap !== '') echo '<p>' . htmlspecialchars($ap) . '</p>';
    }
    echo '</div>';
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
  foreach (kb_lget(kb_lget($sec, 'content', []), 'custom', []) as $cb) {
    if (!is_array($cb)) continue;
    $cbTitle = (string)kb_lget($cb, 'title', 'Informasi');
    $cbText = trim((string)kb_lget($cb, 'text', ''));
    if ($cbText === '') continue;
    kb_card_open('fa-solid fa-note-sticky', $cbTitle);
    echo '<div class="kb-text">';
    foreach (preg_split('/\r\n|\r|\n/', $cbText) as $cbPara) {
      $cbPara = trim($cbPara);
      if ($cbPara !== '') echo '<p>' . htmlspecialchars($cbPara) . '</p>';
    }
    echo '</div></div>';
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

/* ============================================================
 * Editor satu field besar (admin).
 *
 * Semua konten sebuah section ditampilkan sebagai SATU textarea
 * dengan penanda baris `# nama`. Saat disimpan, penanda dipecah
 * kembali ke struktur lama sehingga halaman publik & data Redis
 * (dipakai juga leaders2) tidak berubah. Penanda yang TIDAK ada
 * di teks ikut DIKOSONGKAN (hapus penanda dari teks = hapus isi).
 *
 * Format:
 *   # intro                 -> text
 *   # link                  -> baris 1 = URL, baris 2 = Label
 *   # definisi              -> lines (satu item per baris)
 *   # sub: <slug>           -> blok subseksi
 *     ## <nama field>       -> isi satu field blok tersebut
 *   # links / # faq         -> pasangan Kolom || Isi per baris
 *   # <penanda lain bebas>  -> blok isi bebas (judul = nama penanda)
 * ============================================================ */

/** Pecah teks besar menjadi [['m' => penanda, 'b' => baris], ...]. */
function kb_big_chunks(string $text): array {
  $text = str_replace(["\r\n", "\r"], "\n", $text);
  $chunks = [];
  $marker = null;
  $body = [];
  foreach (preg_split('/\n/', $text) as $ln) {
    if (preg_match('/^# (.+)$/', $ln, $m)) {
      if ($marker !== null) $chunks[] = ['m' => $marker, 'b' => $body];
      $marker = trim($m[1]);
      $body = [];
    } elseif ($marker !== null) {
      $body[] = $ln;
    }
  }
  if ($marker !== null) $chunks[] = ['m' => $marker, 'b' => $body];
  return $chunks;
}

/** Buang baris kosong di tepi (pertahankan baris kosong di tengah). */
function kb_big_clean_lines(array $body): array {
  $body = array_values($body);
  while ($body && trim($body[0]) === '') array_shift($body);
  while ($body && trim($body[count($body) - 1]) === '') array_pop($body);
  return $body;
}

function kb_big_clean_text(array $body): string {
  return implode("\n", kb_big_clean_lines($body));
}

/** Baris non-kosong sebagai daftar item (untuk tipe lines / int text). */
function kb_big_line_items(array $body): array {
  $out = [];
  foreach (kb_big_clean_lines($body) as $ln) {
    $t = trim($ln);
    if ($t !== '') $out[] = $t;
  }
  return $out;
}

/** Pasangan Kolom || Isi per baris (FAQ, link, dan pairs di subseksi).
 *  TAB tetap diterima untuk kompatibilitas data lama. Baris TANPA pemisah
 *  setelah sebuah pasangan dianggap LANJUTAN isi baris berikutnya
 *  (mendukung jawaban/isi multi-baris), dipisah baris baru (\\n). */
function kb_big_pair_items(array $body, string $k1, string $k2): array {
  $out = [];
  $cur = null;
  foreach (kb_big_clean_lines($body) as $ln) {
    $line = rtrim($ln);
    if (trim($line) === '') continue;
    $p = explode('||', $line, 2);
    if (count($p) !== 2) $p = explode("\t", $line, 2);
    if (count($p) === 2) {
      $a = trim($p[0]);
      $b = trim($p[1]);
      if ($a !== '') {
        $out[] = [$k1 => $a, $k2 => $b];
        $cur = count($out) - 1;
      }
    } elseif ($cur !== null) {
      // Lanjutan isi jawaban/kolom isi ke baris berikutnya.
      $line = ltrim($line);
      if ($line !== '') {
        $out[$cur][$k2] = ($out[$cur][$k2] === '' ? '' : $out[$cur][$k2] . "\n") . $line;
      }
    }
  }
  return $out;
}

/** Seriakan seluruh konten sebuah section menjadi satu teks besar. */
function kb_big_text(string $key, array $sec): string {
  $content = kb_lget($sec, 'content', []);
  $groups = [];

  // Markers: pasangan [teks blok, isi] lalu dirapikan di akhir.

  $intro = (string)kb_lget($content, 'intro', '');
  $groups[] = ['# intro', $intro];

  $url = (string)kb_lget($content, 'link_url', '');
  $lbl = (string)kb_lget($content, 'link_label', '');
  if ($url !== '' || $lbl !== '') {
    $groups[] = ['# link', rtrim($url . "\n" . $lbl)];
  }

  foreach (kb_content_fields($key) as $f) {
    if (in_array($f, ['intro', 'link_url', 'link_label'], true)) continue;
    $meta = kb_content_meta($f);
    $val = kb_lget($content, $f);
    if ($meta['type'] === 'lines') {
      $v = kb_lines_value($val);
      $groups[] = ['# ' . $f, $v ? implode("\n", $v) : ''];
    } else {
      $groups[] = ['# ' . $f, trim((string)$val)];
    }
  }

  // Penanda bebas (custom blocks): '# <judul>' dijadikan blok isi bebas.
  foreach (kb_lget($content, 'custom', []) as $cbSlug => $cb) {
    if (!is_array($cb)) continue;
    $groups[] = [
      '# ' . (string)kb_lget($cb, 'title', (string)$cbSlug),
      (string)kb_lget($cb, 'text', ''),
    ];
  }

  $subDef = kb_subsection_def($key);
  if ($subDef) {
    $container = $subDef['scope'] === 'content'
      ? kb_lget($content, 'subsections', [])
      : kb_lget($sec, 'subsections', []);
    foreach ($subDef['blocks'] as $slug => $block) {
      $sub = kb_lget($container, $slug, []);
      $parts = ['# sub: ' . $slug];
      foreach ($block['fields'] as $f) {
        $fname = $f[0];
        $ftype = $f[1];
        $v = kb_lget($sub, $fname);
        $body = '';
        if ($ftype === 'lines') {
          $arr = kb_lines_value($v);
          $body = $arr ? implode("\n", $arr) : '';
        } elseif ($ftype === 'pairs') {
          $k1 = isset($f[3]) && $f[3] !== '' ? $f[3] : 'title';
          $k2 = isset($f[4]) && $f[4] !== '' ? $f[4] : 'text';
          $items = is_array($v) ? $v : [];
          $pl = [];
          foreach ($items as $it) {
            if (is_array($it)) $pl[] = (string)kb_lget($it, $k1) . '||' . (string)kb_lget($it, $k2);
          }
          $body = $pl ? implode("\n", $pl) : '';
        } elseif ($ftype === 'int') {
          $raw = (string)$v;
          $body = $raw !== '' ? (string)(int)$raw : '';
        } else {
          $body = trim((string)$v);
        }
        if ($body !== '') $parts[] = '## ' . $fname . "\n" . $body;
      }
      $groups[] = [implode("\n", $parts), ''];
    }
  }

  foreach (kb_section_fields($key) as $sf) {
    $v = kb_lines_value(kb_lget($sec, $sf['field'], []));
    $groups[] = ['# ' . $sf['field'], $v ? implode("\n", $v) : ''];
  }

  if (kb_has_links($key)) {
    $items = kb_lget($sec, 'links', []);
    $pl = [];
    if (is_array($items)) {
      foreach ($items as $it) {
        if (is_array($it)) $pl[] = (string)kb_lget($it, 'label') . '||' . (string)kb_lget($it, 'url');
      }
    }
    $groups[] = ['# links', $pl ? implode("\n", $pl) : ''];
  }

  $faq = kb_lget($sec, 'faq', []);
  $fl = [];
  if (is_array($faq)) {
    foreach ($faq as $it) {
      if (is_array($it)) $fl[] = (string)kb_lget($it, 'q') . '||' . (string)kb_lget($it, 'a');
    }
  }
  $groups[] = ['# faq', $fl ? implode("\n", $fl) : ''];

  $out = [];
  foreach ($groups as $g) {
    $blockTxt = $g[0];
    if ($g[1] !== '') $blockTxt .= "\n" . $g[1];
    $out[] = $blockTxt;
  }
  return implode("\n\n", $out);
}

/** Terapkan teks besar kembali ke struktur section.
 *  Authoritatif: penanda yang tidak ada di teks ikut DIKOSONGKAN
 *  (menghapus penanda dari teks = menghapus isinya). */
function kb_big_text_apply(string $key, array &$sec, string $text): void {
  if (!isset($sec['content']) || !is_array($sec['content'])) $sec['content'] = [];
  // Blok bebas dibangun ulang dari teks setiap simpan (hapus = hilang).
  $sec['content']['custom'] = [];
  $seen = [];

  $subDef = kb_subsection_def($key);
  foreach (kb_big_chunks($text) as $c) {
    $marker = $c['m'];
    $body = $c['b'];
    $seen[$marker] = true;

    if ($marker === 'link') {
      $lines = kb_big_clean_lines($body);
      $sec['content']['link_url'] = isset($lines[0]) ? trim($lines[0]) : '';
      $sec['content']['link_label'] = isset($lines[1]) ? trim($lines[1]) : '';
      continue;
    }

    if (preg_match('/^sub: (.+)$/', $marker, $mm)) {
      $slug = trim($mm[1]);
      if (!$subDef || !isset($subDef['blocks'][$slug])) continue;
      $scope = $subDef['scope'];
      if ($scope === 'content') {
        if (!isset($sec['content']['subsections']) || !is_array($sec['content']['subsections'])) {
          $sec['content']['subsections'] = [];
        }
        if (!isset($sec['content']['subsections'][$slug]) || !is_array($sec['content']['subsections'][$slug])) {
          $sec['content']['subsections'][$slug] = [];
        }
        $item = $sec['content']['subsections'][$slug];
      } else {
        if (!isset($sec['subsections']) || !is_array($sec['subsections'])) $sec['subsections'] = [];
        if (!isset($sec['subsections'][$slug]) || !is_array($sec['subsections'][$slug])) $sec['subsections'][$slug] = [];
        $item = $sec['subsections'][$slug];
      }

      // Pecah isi blok menjadi chunk per "## nama field"
      $fkey = null;
      $fbody = [];
      $fchunks = [];
      foreach ($body as $ln) {
        if (preg_match('/^## (.+)$/', $ln, $fm)) {
          if ($fkey !== null) $fchunks[] = [$fkey, $fbody];
          $fkey = trim($fm[1]);
          $fbody = [];
        } else {
          $fbody[] = $ln;
        }
      }
      if ($fkey !== null) $fchunks[] = [$fkey, $fbody];

      $byName = [];
      foreach ($subDef['blocks'][$slug]['fields'] as $f) $byName[$f[0]] = $f;
      foreach ($fchunks as $fc) {
        $fname = $fc[0];
        if (!isset($byName[$fname])) continue;
        $f = $byName[$fname];
        if ($f[1] === 'lines') {
          $item[$fname] = kb_big_line_items($fc[1]);
        } elseif ($f[1] === 'pairs') {
          $k1 = isset($f[3]) && $f[3] !== '' ? $f[3] : 'title';
          $k2 = isset($f[4]) && $f[4] !== '' ? $f[4] : 'text';
          $item[$fname] = kb_big_pair_items($fc[1], $k1, $k2);
        } elseif ($f[1] === 'int') {
          $item[$fname] = (int)kb_big_clean_text($fc[1]);
        } else {
          $item[$fname] = kb_big_clean_text($fc[1]);
        }
      }

      if ($scope === 'content') {
        $sec['content']['subsections'][$slug] = $item;
      } else {
        $sec['subsections'][$slug] = $item;
      }
      continue;
    }

    if ($marker === 'links') {
      if (kb_has_links($key)) $sec['links'] = kb_big_pair_items($body, 'label', 'url');
      continue;
    }

    if ($marker === 'faq') {
      $sec['faq'] = kb_big_pair_items($body, 'q', 'a');
      continue;
    }

    // Field konten generik
    $contentFields = kb_content_fields($key);
    if (in_array($marker, $contentFields, true)) {
      $meta = kb_content_meta($marker);
      if ($meta['type'] === 'lines') {
        $sec['content'][$marker] = kb_big_line_items($body);
      } else {
        $sec['content'][$marker] = kb_big_clean_text($body);
      }
      continue;
    }

    // Field level-section (msj: cek_kelulusan)
    $isSectionField = false;
    foreach (kb_section_fields($key) as $sf) {
      if ($sf['field'] === $marker) {
        $isSectionField = true;
        $sec[$marker] = $sf['type'] === 'lines'
          ? kb_big_line_items($body)
          : kb_big_clean_text($body);
      }
    }
    if ($isSectionField) continue;

    // Penanda bebas -> blok isi bebas (judul = nama penanda)
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $marker));
    $slug = trim($slug, '-');
    if ($slug === '') $slug = 'blok';
    $sec['content']['custom'][$slug] = [
      'title' => $marker,
      'text' => kb_big_clean_text($body),
    ];
  }

  // Pass pengosongan: penanda yang tidak ada di teks => kosongkan.
  foreach (kb_content_fields($key) as $f) {
    if ($f === 'link_url' || $f === 'link_label') continue;
    if (isset($seen[$f])) continue;
    $meta = kb_content_meta($f);
    $sec['content'][$f] = $meta['type'] === 'lines' ? [] : '';
  }
  if (!isset($seen['link'])) {
    $sec['content']['link_url'] = '';
    $sec['content']['link_label'] = '';
  }
  if ($subDef) {
    foreach ($subDef['blocks'] as $slug => $block) {
      if (isset($seen['sub: ' . $slug])) continue;
      if ($subDef['scope'] === 'content') {
        if (!isset($sec['content']['subsections']) || !is_array($sec['content']['subsections'])) {
          $sec['content']['subsections'] = [];
        }
        $sec['content']['subsections'][$slug] = [];
      } else {
        if (!isset($sec['subsections']) || !is_array($sec['subsections'])) $sec['subsections'] = [];
        $sec['subsections'][$slug] = [];
      }
    }
  }
  foreach (kb_section_fields($key) as $sf) {
    if (isset($seen[$sf['field']])) continue;
    $sec[$sf['field']] = $sf['type'] === 'lines' ? [] : '';
  }
  if (!isset($seen['links']) && kb_has_links($key)) $sec['links'] = [];
  if (!isset($seen['faq'])) $sec['faq'] = [];
}

/**
 * Ambil-alih & simpan SATU section dari teks besar + identitas (murni,
 * tanpa $_POST / Redis). Dipakai admin (copy-on-save rules):
 *  - pemilik leaders2 yang TIDAK diubah -> ['changed'=>false, 'section'=>null]
 *    (data lama tetap sumber, sisa data leaders3 tidak diubah);
 *  - ada perubahan -> section baru (basis: salinan leaders3 bila ada,
 *    selain itu section tampilan/leaders2) + flag _taken=true.
 */
function kb_big_section_save(
  array $l3Sections,
  string $key,
  array $viewSec,
  string $postText,
  string $postName,
  string $postIcon,
  bool $postVisible
): array {
  $exists = isset($l3Sections[$key]) && is_array($l3Sections[$key]);
  $basis = $exists ? $l3Sections[$key] : $viewSec;

  $txtOld = kb_big_text($key, $basis);
  $txtNew = rtrim(str_replace(["\r\n", "\r"], "\n", $postText));

  $identChanged = $postName !== (string)kb_lget($basis, 'name', '')
    || $postIcon !== (string)kb_lget($basis, 'icon', '')
    || $postVisible !== (bool)kb_lget($basis, 'visible', true);
  $bigChanged = $txtNew !== $txtOld;

  if (!$bigChanged && !$identChanged) {
    return ['changed' => false, 'section' => null];
  }

  $sec = $basis;
  kb_big_text_apply($key, $sec, $txtNew);
  $sec['name'] = $postName;
  $sec['icon'] = $postIcon;
  $sec['visible'] = $postVisible;
  $sec['_taken'] = true;
  return ['changed' => true, 'section' => $sec];
}