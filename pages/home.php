<?php
/* ============================================================
 * home.php — Knowledge Base Leaders 3 (tampilan publik).
 * Sumber data: get_leaders_content() — SAMA dengan leaders2.
 * Render konten: lib/kb.php (skema + renderer generik).
 * ============================================================ */

require_once dirname(__DIR__) . '/lib/kb.php';

if (!function_exists('lget')) {
  function lget($arr, $key, $default = '') {
    return isset($arr[$key]) ? $arr[$key] : $default;
  }
}

$content = get_leaders_content();
$site = lget($content, 'site', []);
$sections = lget($content, 'sections', []);
$admin = is_admin();

// Satu baris intro untuk pratinjau kartu section (ambil baris pertama content.intro).
function kb_card_preview(array $sec): string {
  $intro = lget(lget($sec, 'content', []), 'intro', '');
  $parts = preg_split('/\r\n|\r|\n/', trim((string)$intro));
  foreach ($parts as $p) {
    if (trim($p) !== '') {
      $s = trim($p);
      return mb_strlen($s) > 110 ? mb_substr($s, 0, 110) . '…' : $s;
    }
  }
  return 'Buka panduan ini.';
}

$key = isset($_GET['s']) ? (string)$_GET['s'] : '';
$sec = ($key !== '' && isset($sections[$key])) ? $sections[$key] : null;
if ($sec !== null && !lget($sec, 'visible', true) && !$admin) {
  $sec = null;
}
$isSection = $sec !== null;

$siteTitle = (string)lget($site, 'title', 'Knowledge Base Leaders');
$siteSub = (string)lget($site, 'subtitle', 'GMS Lampung');
$pageTitle = $isSection
  ? lget($sec, 'name', $key) . ' - ' . $siteTitle
  : $siteTitle;

?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <meta name="theme-color" content="#0e7c66" />
  <meta name="description" content="<?php echo htmlspecialchars($siteSub); ?> - <?php echo htmlspecialchars($siteTitle); ?>" />
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/css/app.css" />
  <link rel="manifest" href="/manifest.json" />
  <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png" />
  <link rel="apple-touch-icon" href="/icons/icon-192.png" />
</head>
<body>

  <header class="topbar">
    <div class="wrap topbar-inner">
      <a class="brand" href="<?php echo app_url(''); ?>">
        <span class="brand-badge"><i class="fa-solid fa-book-open"></i></span>
        <span class="brand-text">
          <span class="brand-name"><?php echo htmlspecialchars($siteTitle); ?></span>
          <span class="brand-sub"><?php echo htmlspecialchars($siteSub); ?></span>
        </span>
      </a>
      <div class="topbar-actions">
        <?php if ($admin): ?>
          <a class="chip chip-admin" href="<?php echo app_url('admin'); ?>"><i class="fa-solid fa-user-shield"></i> Panel</a>
          <a class="chip chip-ghost" href="<?php echo app_url('logout'); ?>" title="Keluar"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
        <?php else: ?>
          <a class="chip chip-ghost" href="<?php echo app_url('login'); ?>"><i class="fa-solid fa-user-shield"></i> Admin</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="wrap">

    <?php if ($isSection): ?>
      <?php
      $intro = (string)lget(lget($sec, 'content', []), 'intro', '');
      ?>
      <section class="subhero">
        <a class="subhero-back" href="<?php echo app_url(''); ?>"><i class="fa-solid fa-arrow-left"></i> Beranda</a>
        <div class="subhero-row">
          <span class="subhero-icon"><i class="<?php echo htmlspecialchars((string)lget($sec, 'icon', 'fa-solid fa-file')); ?>"></i></span>
          <div class="subhero-title-block">
            <h1 class="subhero-name"><?php echo htmlspecialchars((string)lget($sec, 'name', $key)); ?></h1>
            <div class="subhero-indicator"></div>
          </div>
        </div>
      </section>

      <div class="kb-content">
        <?php kb_render_section($key, $sec); ?>
      </div>

    <?php else: ?>

      <section class="hero">
        <?php if (lget($site, 'welcome_heading') !== ''): ?>
          <p class="hero-eyebrow"><i class="fa-solid fa-hand"></i> <?php echo htmlspecialchars((string)lget($site, 'subtitle', 'GMS Lampung')); ?></p>
          <h1 class="hero-title"><?php echo htmlspecialchars((string)lget($site, 'welcome_heading')); ?></h1>
        <?php endif; ?>
        <?php if (lget($site, 'welcome_text') !== ''): ?>
          <p class="hero-text"><?php echo htmlspecialchars((string)lget($site, 'welcome_text')); ?></p>
        <?php endif; ?>
        <?php if (lget($site, 'welcome_intro') !== ''): ?>
          <p class="hero-intro"><?php echo htmlspecialchars((string)lget($site, 'welcome_intro')); ?></p>
        <?php endif; ?>
        <?php $notes = lget($site, 'welcome_notes', []); if (is_array($notes) && $notes): ?>
          <ul class="hero-notes">
            <?php foreach ($notes as $n): ?>
              <li><?php echo htmlspecialchars((string)$n); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if (lget($site, 'welcome_closing') !== ''): ?>
          <p class="hero-closing"><?php echo htmlspecialchars((string)lget($site, 'welcome_closing')); ?></p>
        <?php endif; ?>
      </section>

      <div class="search">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <input id="kbSearch" class="searchbox" type="search" inputmode="search" autocomplete="off"
               placeholder="Cari panduan…" aria-label="Cari panduan" />
      </div>
      <p class="search-empty" id="kbSearchEmpty">Tidak ada panduan yang cocok.</p>

      <div class="section-grid" id="kbSectionGrid">
        <?php foreach ($sections as $sk => $sv): ?>
          <?php if (!lget($sv, 'visible', true) && !$admin) continue; ?>
          <a class="section-card" href="?s=<?php echo urlencode($sk); ?>" data-name="<?php echo htmlspecialchars(strtolower((string)lget($sv, 'name', $sk))); ?>">
            <span class="section-card-icon"><i class="<?php echo htmlspecialchars((string)lget($sv, 'icon', 'fa-solid fa-file')); ?>"></i></span>
            <span class="section-card-body">
              <span class="section-card-name"><?php echo htmlspecialchars((string)lget($sv, 'name', $sk)); ?></span>
              <span class="section-card-intro"><?php echo htmlspecialchars(kb_card_preview($sv)); ?></span>
            </span>
            <span class="section-card-arrow"><i class="fa-solid fa-chevron-right"></i></span>
          </a>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

  </main>

  <?php gms_footer(); ?>

  <?php gms_bottom_nav($isSection ? 'home' : 'home'); ?>

  <script>
    (function () {
      var grid = document.getElementById('kbSectionGrid');
      var box = document.getElementById('kbSearch');
      var empty = document.getElementById('kbSearchEmpty');
      if (grid && box) {
        box.addEventListener('input', function () {
          var q = box.value.toLowerCase().trim();
          var cards = grid.querySelectorAll('.section-card');
          var shown = 0;
          if (q === '') {
            empty.classList.remove('is-visible');
          }
          cards.forEach(function (c) {
            var hide = c.dataset.name.indexOf(q) === -1;
            c.dataset.hide = hide ? '1' : '0';
            if (!hide) shown++;
          });
          if (q !== '') {
            empty.classList.toggle('is-visible', shown === 0);
          }
        });
      }
      window.toggleFaq = function (btn) {
        var item = btn.closest('.faq-item');
        if (item) item.classList.toggle('is-open');
      };
    })();
  </script>

</body>
</html>