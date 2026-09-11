<?php
// Shim entry untuk server lokal (Laragon vhost / php -S).
// Di Vercel request masuk langsung ke api/index.php via rewrite.
require __DIR__ . '/api/index.php';
