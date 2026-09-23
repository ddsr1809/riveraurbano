<?php /** Plantilla general del panel. Variables: $titulo, $contenido, $ADMIN, $PAGINAS, $p */ ?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= h($titulo) ?> · Panel Rivera Urbano</title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/admin/admin.css?v=1">
<script src="/admin/admin.js?v=1" defer></script>
</head>
<body class="<?= $ADMIN ? 'con-sesion' : 'sin-sesion' ?>">
<?php if ($ADMIN): ?>
<header class="barra">
  <a class="marca" href="<?= h(url_admin('inicio')) ?>">
    <svg viewBox="0 0 64 64" aria-hidden="true"><rect x="10" y="16" width="36" height="36" fill="none" stroke="#F5B400" stroke-width="5" stroke-dasharray="9 5"/><circle cx="46" cy="16" r="7" fill="#F5B400"/></svg>
    <span>Rivera Urbano <small>Panel</small></span>
  </a>
  <nav class="menu" aria-label="Secciones del panel">
    <?php foreach ($PAGINAS as $clave => $nombre): ?>
      <a href="<?= h(url_admin($clave)) ?>"<?= $clave === $p ? ' aria-current="page"' : '' ?>><?= h($nombre) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="barra-der">
    <a class="ver-sitio" href="/es/" target="_blank" rel="noopener">Ver sitio ↗</a>
    <form method="post" action="<?= h(url_admin('salir')) ?>"><?= csrf_campo() ?><button class="btn-texto">Salir</button></form>
  </div>
</header>
<?php endif; ?>

<main class="contenido">
  <?php foreach (flashes() as [$tipo, $msg]): ?>
    <div class="alerta alerta-<?= h($tipo) ?>" role="<?= $tipo === 'error' ? 'alert' : 'status' ?>"><?= h($msg) ?></div>
  <?php endforeach; ?>
  <?= $contenido ?>
</main>
</body>
</html>
