<?php
/** Inicio: resumen y cosas pendientes. */
$db = db();
$idiomas = idiomas_activos();
$porIdioma = $db->query('SELECT idioma, COUNT(*) n FROM textos GROUP BY idioma')->fetchAll(PDO::FETCH_KEY_PAIR);
$faltantes = (int) $db->query('SELECT COUNT(*) FROM textos_sin_traducir')->fetchColumn();
$cfg = $db->query('SELECT clave, valor FROM configuracion')->fetchAll(PDO::FETCH_KEY_PAIR);
$pendLotes = (int) $db->query("SELECT COUNT(*) FROM textos WHERE clave LIKE 'lote._.%' AND idioma = 'es'
                               AND (valor LIKE '%Por confirmar%' OR valor = 'Consultar')")->fetchColumn();
$cambios = $db->query('SELECT usuario, accion, detalle, fecha FROM bitacora ORDER BY id DESC LIMIT 12')->fetchAll();
$videoOk = is_file(carpeta_video() . '/dron.mp4');
$hoy = $db->query("SELECT SUM(tipo IN ('humano','probable')) p, SUM(tipo IN ('bot','herramienta','sospechoso')) b FROM visitas WHERE fecha >= CURDATE()")->fetch();
$contactosSemana = (int) $db->query("SELECT COUNT(*) FROM visitas_eventos e JOIN visitas v ON v.id = e.visita_id
    WHERE e.fecha >= NOW() - INTERVAL 7 DAY AND v.tipo IN ('humano','probable') AND e.evento IN ('whatsapp','llamar','correo','formulario')")->fetchColumn();

$pendientes = [];
if (($cfg['whatsapp'] ?? '') === '526860000000' || ($cfg['telefono'] ?? '') === '686 000 0000')
    $pendientes[] = ['Tu número de WhatsApp y teléfono siguen siendo los de ejemplo. Los botones no le llegan a nadie.', 'config'];
if (($cfg['correo'] ?? '') === 'contacto@riveraurbano.com')
    $pendientes[] = ['Confirma que el correo contacto@riveraurbano.com exista y lo revises.', 'config'];
if ($pendLotes > 0)
    $pendientes[] = ["Hay $pendLotes datos de los lotes sin llenar (superficie, frente, uso de suelo…).", 'textos', ['s' => 'terrenos']];
if (!$videoOk)
    $pendientes[] = ['Todavía no hay video del dron en el sitio.', 'archivos'];
if ($faltantes > 0)
    $pendientes[] = ["Faltan $faltantes textos por traducir.", 'textos', ['faltantes' => 1]];
?>
<h1>Hola<?= $ADMIN['nombre'] ? ', ' . h($ADMIN['nombre']) : '' ?></h1>
<p class="intro">Desde aquí cambias los textos del sitio en español e inglés, tus datos de contacto y el video del dron. Los cambios se ven en cuanto recargas la página pública.</p>

<?php if ($pendientes): ?>
<section class="tarjeta">
  <h2>Pendientes</h2>
  <ul class="pendientes">
    <?php foreach ($pendientes as $x): ?>
      <li><span><?= h($x[0]) ?></span> <a class="btn btn-chico" href="<?= h(url_admin($x[1], $x[2] ?? [])) ?>">Resolver</a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php else: ?>
<div class="alerta alerta-ok">Todo en orden: contacto configurado, lotes con datos, video arriba y textos traducidos.</div>
<?php endif; ?>

<div class="cifras">
  <a class="cifra cifra-enlace" href="<?= h(url_admin('visitas', ['rango' => 1])) ?>"><strong><?= (int) $hoy['p'] ?></strong><span>personas hoy (y <?= (int) $hoy['b'] ?> bots)</span></a>
  <a class="cifra cifra-enlace" href="<?= h(url_admin('visitas')) ?>"><strong><?= $contactosSemana ?></strong><span>clics para contactar en 7 días</span></a>
  <?php foreach ($idiomas as $i): ?>
    <div class="cifra"><strong><?= (int) ($porIdioma[$i['codigo']] ?? 0) ?></strong><span>textos en <?= h($i['nombre']) ?></span></div>
  <?php endforeach; ?>
  <div class="cifra"><strong><?= $videoOk ? '✓' : '—' ?></strong><span>video del dron</span></div>
  <div class="cifra"><strong><?= $faltantes ?></strong><span>por traducir</span></div>
</div>

<section class="tarjeta">
  <h2>Últimos cambios</h2>
  <?php if (!$cambios): ?>
    <p class="suave">Todavía no hay cambios registrados.</p>
  <?php else: ?>
    <div class="tabla-scroll"><table class="tabla">
      <thead><tr><th>Fecha</th><th>Usuario</th><th>Qué cambió</th></tr></thead>
      <tbody>
      <?php foreach ($cambios as $c): ?>
        <tr><td class="nowrap"><?= h(date('d/m/Y H:i', strtotime($c['fecha']))) ?></td><td><?= h($c['usuario']) ?></td>
            <td><strong><?= h($c['accion']) ?></strong> · <?= h(mb_strimwidth($c['detalle'], 0, 160, '…')) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
