<?php
declare(strict_types=1);
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');
echo '/* Scanner decoder bootstrap: native BarcodeDetector is preferred; jsQR fallback is loaded from the cached scanner asset when available. */';
