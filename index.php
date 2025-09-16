<?php
// --- LOGO: cache local + fallback ---
$remoteLogo = 'https://identity.staging.onceforall.com/auth/resources/rrvf8/login/portal-sywa/img/onceforall-logo.svg'; // (ojo: sin doble //)
$assetsDir  = __DIR__ . '/assets';
$localLogo  = $assetsDir . '/onceforall-logo.svg';
$publicLogo = 'assets/onceforall-logo.svg';

// Intenta usar versión local; si no existe, descargar y guardar
if (!file_exists($localLogo)) {
    if (!is_dir($assetsDir)) { @mkdir($assetsDir, 0755, true); }
    $ch = curl_init($remoteLogo);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'OFALogin/1.0'
    ]);
    $logoData = curl_exec($ch);
    $err  = curl_errno($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$err && $code >= 200 && $code < 400 && $logoData) {
        @file_put_contents($localLogo, $logoData);
    }
}

// El src que usará el <img>
$logoSrc = file_exists($localLogo) ? $publicLogo : $remoteLogo;

// SVG de emergencia (data URI) si falla la carga
$placeholderSvg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 320 60'><rect width='320' height='60' rx='10' fill='#e4007f'/><text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' fill='white' font-family='Segoe UI, Arial, sans-serif' font-size='22'>Once For All</text></svg>";
?>
