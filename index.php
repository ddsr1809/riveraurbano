<?php
declare(strict_types=1);

// Servidor de pruebas de PHP (php -S): deja pasar los archivos estáticos.
if (PHP_SAPI === 'cli-server') {
    $archivo = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($archivo)) return false;
    if (is_file(rtrim($archivo, '/') . '/index.php') && $archivo !== __DIR__ . '/') {
        require rtrim($archivo, '/') . '/index.php';
        return;
    }
}

require __DIR__ . '/includes/bootstrap.php';

try {
    [$lang, $explicito] = detectar_idioma();
    $IDIOMAS = idiomas();
    $defecto = 'es';
    foreach ($IDIOMAS as $i) if ($i['por_defecto']) $defecto = $i['codigo'];
    $GLOBALS['TEXTOS'] = cargar_textos($lang);
    $GLOBALS['TEXTOS_RESPALDO'] = $lang === $defecto ? [] : cargar_textos($defecto);
    $GLOBALS['CONFIG'] = cargar_config();
} catch (Throwable $e) {
    http_response_code(503);
    header('Retry-After: 300');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Rivera Urbano</title><body style="font-family:system-ui;padding:2rem;max-width:40rem;margin:auto">'
       . '<h1>Rivera Urbano</h1><p>El sitio está en mantenimiento. Vuelve en unos minutos.</p>'
       . '<p>The site is under maintenance. Please check back in a few minutes.</p></body>';
    if (!empty(config()['debug'])) echo '<pre>' . h($e->getMessage()) . '</pre>';
    exit;
}

if ($explicito) {
    setcookie('idioma', $lang, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax',
                                'secure' => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https']);
}
header('Content-Language: ' . $lang);
header('Vary: Accept-Language, Cookie');

$locale = 'es_MX';
foreach ($IDIOMAS as $i) if ($i['codigo'] === $lang) $locale = $i['locale'];
$base   = rtrim(cfg('url_base', 'https://riveraurbano.com'), '/');
$poster = "/assets/img/poster-$lang.jpg";
$lotes  = ['a', 'b'];
$campos = ['superficie', 'frente', 'fondo', 'uso_suelo', 'servicios', 'renta'];

// Textos que necesita el JavaScript
$js_textos = [];
foreach (['nav.abrir_menu', 'nav.cerrar_menu', 'msg.encabezado', 'msg.nombre', 'msg.telefono',
          'msg.empresa', 'msg.lote', 'msg.uso', 'msg.plazo', 'msg.mensaje', 'correo.asunto_form'] as $k) {
    $js_textos[$k] = t($k);
}
$js = [
    'whatsapp'     => cfg('whatsapp'),
    'correo'       => cfg('correo'),
    'youtubeId'    => cfg('youtube_id'),
    'videoPortada' => cfg('video_portada'),
    'videoTitulo'  => t('video.iframe'),
    'textos'       => $js_textos,
];
?>
<!DOCTYPE html>
<html lang="<?= h($lang === 'es' ? 'es-MX' : $lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e('meta.titulo') ?></title>
<meta name="description" content="<?= e('meta.descripcion') ?>">
<meta name="theme-color" content="#1F2B33">
<link rel="canonical" href="<?= h("$base/$lang/") ?>">
<?php foreach ($IDIOMAS as $i): ?>
<link rel="alternate" hreflang="<?= h($i['codigo']) ?>" href="<?= h("$base/{$i['codigo']}/") ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= h("$base/") ?>">

<meta property="og:type" content="website">
<meta property="og:locale" content="<?= h($locale) ?>">
<meta property="og:title" content="<?= e('og.titulo') ?>">
<meta property="og:description" content="<?= e('og.descripcion') ?>">
<meta property="og:url" content="<?= h("$base/$lang/") ?>">
<meta property="og:image" content="<?= h("$base/assets/img/og-image-$lang.jpg") ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">

<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/assets/img/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/estilos.css">

<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'RealEstateListing',
    'name' => t('og.titulo'),
    'url' => "$base/$lang/",
    'inLanguage' => $lang,
    'image' => "$base/assets/img/og-image-$lang.jpg",
    'description' => t('meta.descripcion'),
    'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'Calle Novena y Calzada Cetys',
                  'addressLocality' => 'Mexicali', 'addressRegion' => 'B.C.', 'addressCountry' => 'MX'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
</head>
<body>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-whats" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.8-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.2-.2-.4-.3Z"/></symbol>
  <symbol id="i-tel" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2Z"/></symbol>
  <symbol id="i-correo" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></g></symbol>
  <symbol id="i-pin" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12Z"/><circle cx="12" cy="9" r="2.5"/></g></symbol>
  <symbol id="i-play" viewBox="0 0 24 24"><path fill="currentColor" d="M8 5v14l11-7z"/></symbol>
  <symbol id="i-logo" viewBox="0 0 64 64"><rect x="10" y="16" width="36" height="36" fill="none" stroke="#F5B400" stroke-width="5" stroke-dasharray="9 5"/><circle cx="46" cy="16" r="7" fill="#F5B400"/></symbol>
</svg>

<header class="encabezado">
  <div class="contenedor">
    <a href="#inicio" class="marca" aria-label="<?= e('nav.ir_inicio') ?>"><svg aria-hidden="true"><use href="#i-logo"/></svg>Rivera Urbano</a>
    <nav class="idiomas" aria-label="<?= e('nav.idioma') ?>">
      <?php foreach ($IDIOMAS as $i): ?>
        <a href="/<?= h($i['codigo']) ?>/" hreflang="<?= h($i['codigo']) ?>" lang="<?= h($i['codigo']) ?>" title="<?= h($i['nombre']) ?>"<?= $i['codigo'] === $lang ? ' aria-current="true"' : '' ?>><?= h(strtoupper($i['codigo'])) ?></a>
      <?php endforeach; ?>
    </nav>
    <button class="menu-btn" aria-label="<?= e('nav.abrir_menu') ?>" aria-expanded="false" aria-controls="nav">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </button>
    <nav class="nav" id="nav" aria-label="<?= e('nav.principal') ?>">
      <a href="#terrenos"><?= e('nav.terrenos') ?></a>
      <a href="#video"><?= e('nav.video') ?></a>
      <a href="#propuesta"><?= e('nav.propuesta') ?></a>
      <a href="#preguntas"><?= e('nav.preguntas') ?></a>
      <a href="#contacto"><?= e('nav.contacto') ?></a>
      <a class="btn btn-whats" href="<?= h(link_whats(t('wa.general'))) ?>" target="_blank" rel="noopener"><svg aria-hidden="true"><use href="#i-whats"/></svg><?= e('nav.whatsapp') ?></a>
    </nav>
  </div>
</header>

<main>

<section class="portada" id="inicio">
  <img class="portada-img" src="<?= h($poster) ?>" alt="" aria-hidden="true">
  <video id="videoPortada" muted loop playsinline preload="none" poster="<?= h($poster) ?>" aria-hidden="true"></video>
  <div class="contenedor">
    <h1><?= e('hero.titulo') ?></h1>
    <p class="sub"><?= e('hero.sub') ?></p>
    <div class="acciones">
      <a class="btn btn-whats" href="<?= h(link_whats(t('wa.info'))) ?>" target="_blank" rel="noopener"><svg aria-hidden="true"><use href="#i-whats"/></svg><?= e('hero.btn_info') ?></a>
      <a class="btn btn-borde" href="#video"><svg aria-hidden="true"><use href="#i-play"/></svg><?= e('hero.btn_video') ?></a>
    </div>
    <div class="datos-portada">
      <span><i></i><?= e('hero.dato1') ?></span>
      <span><i></i><?= e('hero.dato2') ?></span>
      <span><i></i><?= e('hero.dato3') ?></span>
    </div>
  </div>
</section>

<section class="terrenos" id="terrenos">
  <div class="contenedor">
    <h2><?= e('terrenos.titulo') ?></h2>
    <p class="intro"><?= e('terrenos.intro') ?></p>

    <div class="terrenos-grid">
      <div>
        <div class="croquis">
          <!-- Generado con: python3 tools/imagenes.py --croquis -->
<svg viewBox="120 185 540 520" role="img" aria-labelledby="croquisTitulo">
<title id="croquisTitulo"><?= e('croquis.titulo') ?></title>
<defs><clipPath id="sinCruce"><rect x="-400" y="272" width="1600" height="900"/><rect x="-400" y="-200" width="1600" height="404"/></clipPath></defs>
<rect x="-400" y="-200" width="1600" height="1100" fill="#C7B98F"/>
<rect x="640" y="40" width="80" height="100" fill="#B5A873"/>
<rect x="-400" y="690" width="1600" height="40" fill="#A9A06E"/>
<rect x="300" y="140" width="70" height="52" fill="#C98C6E" stroke="#BDB8AD" stroke-width="1.5"/>
<rect x="130" y="285" width="136" height="86" fill="#B9B4A6"/>
<line x1="140" y1="300" x2="140" y2="318" stroke="#fff" stroke-width="1.2"/>
<line x1="152" y1="300" x2="152" y2="318" stroke="#fff" stroke-width="1.2"/>
<line x1="164" y1="300" x2="164" y2="318" stroke="#fff" stroke-width="1.2"/>
<line x1="176" y1="300" x2="176" y2="318" stroke="#fff" stroke-width="1.2"/>
<line x1="188" y1="300" x2="188" y2="318" stroke="#fff" stroke-width="1.2"/>
<line x1="200" y1="300" x2="200" y2="318" stroke="#fff" stroke-width="1.2"/>
<line x1="212" y1="300" x2="212" y2="318" stroke="#fff" stroke-width="1.2"/>
<line x1="224" y1="300" x2="224" y2="318" stroke="#fff" stroke-width="1.2"/>
<line x1="236" y1="300" x2="236" y2="318" stroke="#fff" stroke-width="1.2"/>
<line x1="248" y1="300" x2="248" y2="318" stroke="#fff" stroke-width="1.2"/>
<rect x="133" y="375" width="135" height="240" fill="#E4E2DC" stroke="#BDB8AD" stroke-width="2"/>
<rect x="270" y="280" width="95" height="172" fill="#B9B4A6"/>
<rect x="298" y="296" width="46" height="96" fill="#C0694F" stroke="#BDB8AD" stroke-width="1.5"/>
<rect x="440" y="272" width="125" height="118" fill="#B9B4A6"/>
<rect x="466" y="286" width="58" height="42" fill="#F4F4F0" stroke="#BDB8AD" stroke-width="1.5"/>
<rect x="508" y="336" width="54" height="50" fill="#E4E2DC" stroke="#BDB8AD" stroke-width="1.5"/>
<rect x="508" y="336" width="54" height="5" fill="#2E7D4F"/><rect x="508" y="341" width="54" height="3" fill="#E8792B"/>
<rect x="680" y="300" width="80" height="290" rx="10" fill="#F1F1EE" stroke="#BDB8AD" stroke-width="1.5"/>
<circle cx="60" cy="60" r="9" fill="#6F8466" opacity=".85"/>
<circle cx="90" cy="120" r="10" fill="#6F8466" opacity=".85"/>
<circle cx="150" cy="70" r="12" fill="#6F8466" opacity=".85"/>
<circle cx="210" cy="110" r="9" fill="#6F8466" opacity=".85"/>
<circle cx="250" cy="40" r="10" fill="#6F8466" opacity=".85"/>
<circle cx="530" cy="60" r="10" fill="#6F8466" opacity=".85"/>
<circle cx="580" cy="120" r="8" fill="#6F8466" opacity=".85"/>
<circle cx="600" cy="30" r="10" fill="#6F8466" opacity=".85"/>
<circle cx="700" cy="180" r="10" fill="#6F8466" opacity=".85"/>
<circle cx="60" cy="300" r="11" fill="#6F8466" opacity=".85"/>
<circle cx="80" cy="420" r="7" fill="#6F8466" opacity=".85"/>
<circle cx="70" cy="560" r="9" fill="#6F8466" opacity=".85"/>
<circle cx="40" cy="640" r="7" fill="#6F8466" opacity=".85"/>
<circle cx="620" cy="430" r="11" fill="#6F8466" opacity=".85"/>
<circle cx="580" cy="520" r="10" fill="#6F8466" opacity=".85"/>
<circle cx="660" cy="620" r="7" fill="#6F8466" opacity=".85"/>
<circle cx="520" cy="460" r="12" fill="#6F8466" opacity=".85"/>
<circle cx="600" cy="650" r="12" fill="#6F8466" opacity=".85"/>
<circle cx="250" cy="160" r="10" fill="#6F8466" opacity=".85"/>
<circle cx="170" cy="190" r="10" fill="#6F8466" opacity=".85"/>
<circle cx="560" cy="190" r="8" fill="#6F8466" opacity=".85"/>
<rect x="-400" y="207" width="1600" height="62" fill="#D8D4C8"/>
<path d="M447,-60 C445.8,-33.3 443.7,50.0 440,100 C436.3,150.0 432.5,190.8 425,240 C417.5,289.2 401.7,358.3 395,395 C388.3,431.7 397.0,414.2 385,460 C373.0,505.8 337.2,620.0 323,670 C308.8,720.0 303.8,745.0 300,760" fill="none" stroke="#D8D4C8" stroke-width="76"/>
<path d="M447,-60 C445.8,-33.3 443.7,50.0 440,100 C436.3,150.0 432.5,190.8 425,240 C417.5,289.2 401.7,358.3 395,395 C388.3,431.7 397.0,414.2 385,460 C373.0,505.8 337.2,620.0 323,670 C308.8,720.0 303.8,745.0 300,760" fill="none" stroke="#3A454C" stroke-width="66"/>
<rect x="-400" y="212" width="1600" height="52" fill="#3A454C"/>
<line x1="-400" y1="238" x2="385" y2="238" stroke="#F5B400" stroke-width="3"/>
<line x1="470" y1="238" x2="1200" y2="238" stroke="#F5B400" stroke-width="3"/>
<line x1="-400" y1="225" x2="380" y2="225" stroke="#fff" stroke-width="1.5" stroke-dasharray="12 10" opacity=".7"/>
<line x1="475" y1="225" x2="1200" y2="225" stroke="#fff" stroke-width="1.5" stroke-dasharray="12 10" opacity=".7"/>
<line x1="-400" y1="251" x2="380" y2="251" stroke="#fff" stroke-width="1.5" stroke-dasharray="12 10" opacity=".7"/>
<line x1="475" y1="251" x2="1200" y2="251" stroke="#fff" stroke-width="1.5" stroke-dasharray="12 10" opacity=".7"/>
<path d="M447,-60 C445.8,-33.3 443.7,50.0 440,100 C436.3,150.0 432.5,190.8 425,240 C417.5,289.2 401.7,358.3 395,395 C388.3,431.7 397.0,414.2 385,460 C373.0,505.8 337.2,620.0 323,670 C308.8,720.0 303.8,745.0 300,760" fill="none" stroke="#F5B400" stroke-width="3" clip-path="url(#sinCruce)"/>
<rect x="40" y="243" width="18" height="9" rx="2" fill="#FFFFFF"/>
<rect x="160" y="216" width="18" height="9" rx="2" fill="#A63A3A"/>
<rect x="560" y="243" width="18" height="9" rx="2" fill="#2E5E8C"/>
<rect x="640" y="216" width="18" height="9" rx="2" fill="#222"/>
<rect x="760" y="243" width="18" height="9" rx="2" fill="#C9C9C9"/>
<rect x="-60" y="216" width="18" height="9" rx="2" fill="#FFFFFF"/>
<rect x="290" y="243" width="18" height="9" rx="2" fill="#A63A3A"/>
<rect x="366" y="471" width="9" height="18" rx="2" fill="#2E5E8C" transform="rotate(-16 370 480)"/>
<rect x="398" y="491" width="9" height="18" rx="2" fill="#222" transform="rotate(-16 402 500)"/>
<rect x="406" y="336" width="9" height="18" rx="2" fill="#C9C9C9" transform="rotate(-12 410 345)"/>
<rect x="334" y="651" width="9" height="18" rx="2" fill="#FFFFFF" transform="rotate(-16 338 660)"/>
<polygon points="272,460 352,460 332,530 312,600 292,668 270,668" fill="#CDBB90" stroke="#F5B400" stroke-width="3.5" stroke-dasharray="10 6" stroke-linejoin="round" class="lote activo" data-lote="a" tabindex="0" role="button" aria-label="<?= e('croquis.ver_lote', ['lote' => t('lote.a.nombre')]) ?>"/>
<polygon points="428,395 458,395 455,678 354,678 376,600 396,530 417,460" fill="#CDBB90" stroke="#F5B400" stroke-width="3.5" stroke-dasharray="10 6" stroke-linejoin="round" class="lote" data-lote="b" tabindex="0" role="button" aria-label="<?= e('croquis.ver_lote', ['lote' => t('lote.b.nombre')]) ?>"/>
<rect x="420" y="455" width="28" height="44" fill="#A8A397" opacity=".8" pointer-events="none"/>
<text x="200" y="500" text-anchor="middle" font-family="Archivo,Arial,Helvetica,sans-serif" font-weight="700" font-size="15" fill="#5E5B54" pointer-events="none">CEDIS Coppel</text>
<text x="318" y="440" text-anchor="middle" font-family="Archivo,Arial,Helvetica,sans-serif" font-weight="700" font-size="11" fill="#5E5B54" pointer-events="none">Car Wash</text>
<text x="495" y="312" text-anchor="middle" font-family="Archivo,Arial,Helvetica,sans-serif" font-weight="700" font-size="13" fill="#5E5B54" pointer-events="none">bp</text>
<text x="535" y="369" text-anchor="middle" font-family="Archivo,Arial,Helvetica,sans-serif" font-weight="700" font-size="9" fill="#5E5B54" pointer-events="none">7-Eleven</text>
<text x="335" y="206" text-anchor="middle" font-family="Archivo,Arial,Helvetica,sans-serif" font-weight="700" font-size="10" fill="#5E5B54" pointer-events="none">Farmacias Roma</text>
<g transform="translate(215,238) rotate(0)" pointer-events="none"><rect x="-57" y="-12" width="114" height="25" rx="5" fill="#fff"/><text y="4.7" text-anchor="middle" font-family="Archivo,Arial,Helvetica,sans-serif" font-weight="700" font-size="13" fill="#1F2B33">Calzada Cetys</text></g>
<g transform="translate(585,238) rotate(0)" pointer-events="none"><rect x="-68" y="-12" width="137" height="25" rx="5" fill="#fff"/><text y="4.7" text-anchor="middle" font-family="Archivo,Arial,Helvetica,sans-serif" font-weight="700" font-size="13" fill="#1F2B33">Carr. Aeropuerto</text></g>
<g transform="translate(342,585) rotate(-73)" pointer-events="none"><rect x="-46" y="-10" width="93" height="21" rx="5" fill="#fff"/><text y="4.0" text-anchor="middle" font-family="Archivo,Arial,Helvetica,sans-serif" font-weight="700" font-size="11" fill="#1F2B33">Calle Novena</text></g>
<g transform="translate(454,110) rotate(-86)" pointer-events="none"><rect x="-94" y="-10" width="188" height="21" rx="5" fill="#fff"/><text y="4.0" text-anchor="middle" font-family="Archivo,Arial,Helvetica,sans-serif" font-weight="700" font-size="11" fill="#1F2B33">Calz. Abelardo L. Rodríguez</text></g>
<g pointer-events="none"><rect x="273" y="542" width="72" height="28" rx="14" fill="#1F2B33"/><text x="309" y="561" text-anchor="middle" font-family="Archivo,Arial,Helvetica,sans-serif" font-weight="800" font-size="15" fill="#F5B400"><?= e('lote.a.nombre') ?></text></g>
<g pointer-events="none"><rect x="394" y="560" width="72" height="28" rx="14" fill="#1F2B33"/><text x="430" y="579" text-anchor="middle" font-family="Archivo,Arial,Helvetica,sans-serif" font-weight="800" font-size="15" fill="#F5B400"><?= e('lote.b.nombre') ?></text></g>
<g transform="translate(600,310)" pointer-events="none"><circle r="15" fill="#fff" opacity=".9"/><path d="M0,-10 L6,6 L0,2 L-6,6Z" fill="#1F2B33"/><text y="-19" text-anchor="middle" font-family="Archivo,Arial,Helvetica,sans-serif" font-weight="800" font-size="12" fill="#1F2B33">N</text></g>
</svg>
        </div>
        <p class="nota-croquis"><?= e('croquis.nota') ?></p>
      </div>

      <div>
        <div class="pestanas" role="tablist" aria-label="<?= e('croquis.elegir') ?>">
          <?php foreach ($lotes as $n => $l): ?>
            <button class="pestana" role="tab" id="tab-<?= $l ?>" aria-controls="ficha-<?= $l ?>" data-lote="<?= $l ?>" aria-selected="<?= $n === 0 ? 'true' : 'false' ?>"><?= e("lote.$l.nombre") ?></button>
          <?php endforeach; ?>
        </div>
        <?php foreach ($lotes as $n => $l): ?>
          <div class="ficha" id="ficha-<?= $l ?>" role="tabpanel" aria-labelledby="tab-<?= $l ?>"<?= $n ? ' hidden' : '' ?>>
            <h3><?= e("lote.$l.nombre") ?></h3>
            <p class="donde"><?= e("lote.$l.donde") ?></p>
            <dl>
              <?php foreach ($campos as $c): $valor = t("lote.$l.$c"); ?>
                <dt><?= e("lote.campo.$c") ?></dt>
                <dd<?= stripos($valor, t('lote.pendiente')) !== false ? ' class="pendiente"' : '' ?>><?= h($valor) ?></dd>
              <?php endforeach; ?>
            </dl>
            <a class="btn btn-whats" href="<?= h(link_whats(t('wa.lote', ['lote' => t("lote.$l.nombre"), 'donde' => t("lote.$l.donde")]))) ?>" target="_blank" rel="noopener"><svg aria-hidden="true"><use href="#i-whats"/></svg><?= e('lote.btn_preguntar', ['lote' => t("lote.$l.nombre")]) ?></a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="video" id="video">
  <div class="contenedor">
    <h2><?= e('video.titulo') ?></h2>
    <p class="intro"><?= e('video.intro') ?></p>
    <div class="marco-video" id="marcoVideo">
      <!-- Video del dron: assets/video/dron.mp4, o un ID de YouTube en la tabla configuracion (youtube_id) -->
      <video id="videoDron" controls playsinline preload="metadata" poster="<?= h($poster) ?>">
        <source src="/assets/video/dron.webm" type="video/webm">
        <source src="/assets/video/dron.mp4" type="video/mp4">
        <?= e('video.no_soportado') ?>
      </video>
      <div class="video-sin-archivo" id="videoSinArchivo"><p><?= e('video.sin_archivo') ?></p></div>
    </div>
    <div class="video-pie">
      <p><?= e('video.pie') ?></p>
      <a class="btn btn-amarillo" href="<?= h(link_whats(t('wa.video'))) ?>" target="_blank" rel="noopener"><?= e('video.btn_visita') ?></a>
    </div>
  </div>
</section>

<section id="propuesta">
  <div class="contenedor">
    <h2><?= e('propuesta.titulo') ?></h2>
    <p class="intro"><?= e('propuesta.intro') ?></p>
    <?php
    $iconos = [
      1 => '<path d="M4 14h28v18H4zM2 14l4-9h24l4 9"/><path d="M10 32V22h8v10M22 21h6v5h-6z"/>',
      2 => '<path d="M8 12h20l-2 20H10z"/><path d="M13 12a5 5 0 0 1 10 0M4 6h28"/>',
      3 => '<path d="M4 22l3-8h22l3 8v6H4z"/><circle cx="10" cy="28" r="3"/><circle cx="26" cy="28" r="3"/><path d="M4 22h28"/>',
      4 => '<rect x="5" y="4" width="26" height="28" rx="2"/><path d="M13 26V10h6a5 5 0 0 1 0 10h-6"/>',
      5 => '<path d="M4 30h28M8 30V16l10-8 10 8v14"/><path d="M15 30v-8h6v8"/>',
      6 => '<rect x="3" y="12" width="20" height="14" rx="2"/><path d="M23 16h6l4 5v5H23"/><circle cx="9" cy="28" r="2.5"/><circle cx="27" cy="28" r="2.5"/>',
    ];
    ?>
    <div class="usos-grid">
      <?php foreach ($iconos as $n => $svg): ?>
        <div class="uso">
          <svg viewBox="0 0 36 36" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2.2"><?= $svg ?></g></svg>
          <h3><?= e("uso.$n.titulo") ?></h3>
          <p><?= e("uso.$n.texto") ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <h2><?= e('esquemas.titulo') ?></h2>
    <p class="intro"><?= e('esquemas.intro') ?></p>
    <div class="esquemas">
      <?php for ($n = 1; $n <= 3; $n++): ?>
        <article class="esquema<?= $n === 2 ? ' destacado' : '' ?>">
          <h3><?= e("esquema.$n.titulo") ?></h3>
          <p class="para"><?= e("esquema.$n.para") ?></p>
          <ul>
            <?php for ($i = 1; $i <= 3; $i++): ?><li><?= e("esquema.$n.li$i") ?></li><?php endfor; ?>
          </ul>
          <a class="btn" href="<?= h(link_whats(t("wa.esquema.$n"))) ?>" target="_blank" rel="noopener"><?= e('esquema.btn') ?></a>
        </article>
      <?php endfor; ?>
    </div>
  </div>
</section>

<section class="pasos" id="pasos">
  <div class="contenedor">
    <h2><?= e('pasos.titulo') ?></h2>
    <ol>
      <?php for ($n = 1; $n <= 4; $n++): ?>
        <li><h3><?= e("paso.$n.titulo") ?></h3><p><?= e("paso.$n.texto") ?></p></li>
      <?php endfor; ?>
    </ol>
  </div>
</section>

<section class="faq" id="preguntas">
  <div class="contenedor">
    <h2><?= e('faq.titulo') ?></h2>
    <?php for ($n = 1; $n <= 6; $n++): ?>
      <details><summary><?= e("faq.$n.p") ?></summary><p><?= e("faq.$n.r") ?></p></details>
    <?php endfor; ?>
  </div>
</section>

<section class="contacto" id="contacto">
  <div class="contenedor">
    <h2><?= e('contacto.titulo') ?></h2>
    <p class="intro"><?= e('contacto.intro') ?></p>

    <div class="contacto-grid">
      <div>
        <div class="canales">
          <a class="canal" href="<?= h(link_whats(t('wa.general'))) ?>" target="_blank" rel="noopener">
            <svg aria-hidden="true"><use href="#i-whats"/></svg>
            <span><small><?= e('canal.whatsapp') ?></small><strong><?= h(cfg('telefono')) ?></strong></span>
          </a>
          <a class="canal" href="tel:+<?= h(cfg('whatsapp')) ?>">
            <svg aria-hidden="true"><use href="#i-tel"/></svg>
            <span><small><?= e('canal.llamar') ?></small><strong><?= h(cfg('telefono')) ?></strong></span>
          </a>
          <a class="canal" href="mailto:<?= h(cfg('correo')) ?>?subject=<?= h(rawurlencode(t('correo.asunto'))) ?>">
            <svg aria-hidden="true"><use href="#i-correo"/></svg>
            <span><small><?= e('canal.correo') ?></small><strong><?= h(cfg('correo')) ?></strong></span>
          </a>
          <a class="canal" href="https://www.google.com/maps/search/?api=1&amp;query=<?= h(rawurlencode(cfg('mapa'))) ?>" target="_blank" rel="noopener">
            <svg aria-hidden="true"><use href="#i-pin"/></svg>
            <span><small><?= e('canal.ubicacion') ?></small><strong><?= e('canal.direccion') ?></strong></span>
          </a>
        </div>
        <div class="mapa"><iframe title="<?= e('mapa.titulo') ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
          src="https://maps.google.com/maps?q=<?= h(rawurlencode(cfg('mapa'))) ?>&amp;z=17&amp;hl=<?= h($lang) ?>&amp;output=embed"></iframe></div>
      </div>

      <form class="formulario" id="formulario" novalidate>
        <h3><?= e('form.titulo') ?></h3>
        <p><?= e('form.intro') ?></p>
        <div class="campos">
          <div class="campo"><label for="f-nombre"><?= e('form.nombre') ?></label><input id="f-nombre" name="nombre" autocomplete="name" required><span class="error"><?= e('form.nombre_error') ?></span></div>
          <div class="campo"><label for="f-tel"><?= e('form.telefono') ?></label><input id="f-tel" name="telefono" type="tel" autocomplete="tel" inputmode="tel" required><span class="error"><?= e('form.telefono_error') ?></span></div>
          <div class="campo"><label for="f-empresa"><?= e('form.empresa') ?></label><input id="f-empresa" name="empresa" autocomplete="organization"></div>
          <div class="campo"><label for="f-lote"><?= e('form.lote') ?></label>
            <select id="f-lote" name="lote"><?php foreach (['a', 'b', 'ambos', 'nose'] as $o): ?><option><?= e("form.lote.$o") ?></option><?php endforeach; ?></select></div>
          <div class="campo"><label for="f-uso"><?= e('form.uso') ?></label>
            <select id="f-uso" name="uso"><?php for ($o = 1; $o <= 7; $o++): ?><option><?= e("form.uso.$o") ?></option><?php endfor; ?></select></div>
          <div class="campo"><label for="f-plazo"><?= e('form.plazo') ?></label>
            <select id="f-plazo" name="plazo"><?php for ($o = 1; $o <= 4; $o++): ?><option><?= e("form.plazo.$o") ?></option><?php endfor; ?></select></div>
          <div class="campo ancho"><label for="f-msg"><?= e('form.mensaje') ?></label><textarea id="f-msg" name="mensaje" placeholder="<?= e('form.mensaje_ph') ?>"></textarea></div>
        </div>
        <div class="enviar">
          <button type="submit" class="btn btn-whats" data-via="whats"><svg aria-hidden="true"><use href="#i-whats"/></svg><?= e('form.btn_whats') ?></button>
          <button type="submit" class="btn btn-borde" data-via="correo"><svg aria-hidden="true"><use href="#i-correo"/></svg><?= e('form.btn_correo') ?></button>
        </div>
        <p class="aviso"><?= e('form.aviso') ?></p>
      </form>
    </div>
  </div>
</section>

</main>

<footer class="pie">
  <div class="contenedor">
    <a href="#inicio" class="marca"><svg aria-hidden="true" width="28" height="28"><use href="#i-logo"/></svg>Rivera Urbano</a>
    <p><?= e('pie.texto') ?> · © <?= date('Y') ?> riveraurbano.com</p>
  </div>
</footer>

<a class="whats-flotante" href="<?= h(link_whats(t('wa.general'))) ?>" target="_blank" rel="noopener" aria-label="<?= e('aria.whats_flotante') ?>"><svg aria-hidden="true"><use href="#i-whats"/></svg></a>

<script>window.SITIO = <?= json_encode($js, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="/assets/js/sitio.js" defer></script>
</body>
</html>
