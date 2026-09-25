<?php
/** Visitas: quién entra, de dónde, y si es persona o bot. */
require_once RAIZ . '/includes/visitas.php';
$db = db();
$cfg = $db->query('SELECT clave, valor FROM configuracion')->fetchAll(PDO::FETCH_KEY_PAIR);
$miIp = ip_cliente();
$excluidas = array_values(array_filter(array_map('trim', explode(',', (string) ($cfg['visitas_ips_excluidas'] ?? '')))));

/* ---------------------------------------------------------------- acciones */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $guardarExcluidas = function (array $lista) use ($db) {
        $db->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'visitas_ips_excluidas'")
           ->execute([implode(',', array_unique($lista))]);
    };
    if ($accion === 'excluir') {
        $ip = trim((string) ($_POST['ip'] ?? ''));
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $guardarExcluidas(array_merge($excluidas, [$ip]));
            $n = $db->prepare("UPDATE visitas SET tipo = 'interno', motivo = 'IP marcada como propia' WHERE ip = ?");
            $n->execute([$ip]);
            bitacora('Visitas', "IP $ip marcada como propia");
            flash('ok', "Listo: las visitas desde $ip ahora cuentan como internas ({$n->rowCount()} anteriores actualizadas).");
        } else flash('error', 'Esa IP no es válida.');
    } elseif ($accion === 'incluir') {
        $ip = (string) ($_POST['ip'] ?? '');
        $guardarExcluidas(array_diff($excluidas, [$ip]));
        bitacora('Visitas', "IP $ip ya no se considera propia");
        flash('ok', "La IP $ip vuelve a contarse normalmente en visitas nuevas.");
    } elseif ($accion === 'dias') {
        $dias = max(30, min(730, (int) ($_POST['dias'] ?? 180)));
        $db->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'visitas_dias'")->execute([(string) $dias]);
        bitacora('Visitas', "Se guardan $dias días de visitas");
        flash('ok', "Las visitas se guardarán $dias días.");
    }
    redirigir('visitas', array_intersect_key($_GET, array_flip(['rango', 'tipo', 'pais', 'q'])));
}

/* ---------------------------------------------------------------- utilidades de vista */
function bandera(string $cc): string
{
    if (!preg_match('/^[A-Z]{2}$/', $cc)) return '🌐';
    return mb_chr(0x1F1E6 + ord($cc[0]) - 65) . mb_chr(0x1F1E6 + ord($cc[1]) - 65);
}
function etiqueta_tipo(string $t): string
{
    return '<span class="tipo tipo-' . h($t) . '">' . h(TIPOS_VISITA[$t] ?? $t) . '</span>';
}
function lugar(array $v): string
{
    $partes = array_filter([$v['ciudad'], $v['region'], $v['pais']]);
    return $partes ? implode(', ', $partes) : ($v['organizacion'] === 'Red local' ? 'Red local' : 'Ubicación desconocida');
}
function duracion(int $s): string
{
    return $s <= 0 ? '—' : ($s < 60 ? "{$s} s" : intdiv($s, 60) . ' min ' . ($s % 60) . ' s');
}

$hayGeo = lector_geoip('ciudad') !== null;

/* ================================================================ detalle de una visita */
if (isset($_GET['id'])) {
    $st = $db->prepare('SELECT * FROM visitas WHERE id = ?');
    $st->execute([(int) $_GET['id']]);
    $v = $st->fetch();
    if (!$v) { flash('error', 'Esa visita ya no existe.'); redirigir('visitas'); }

    if ($v['host_inverso'] === null && filter_var($v['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $hn = @gethostbyaddr($v['ip']);
        $v['host_inverso'] = ($hn && $hn !== $v['ip']) ? mb_substr($hn, 0, 255) : '';
        $db->prepare('UPDATE visitas SET host_inverso = ? WHERE ip = ? AND host_inverso IS NULL')->execute([$v['host_inverso'], $v['ip']]);
    }
    $ev = $db->prepare('SELECT fecha, evento, detalle FROM visitas_eventos WHERE visita_id = ? ORDER BY id');
    $ev->execute([$v['id']]);
    $eventos = $ev->fetchAll();
    $otras = $db->prepare('SELECT id, fecha, ruta, tipo, fuente, interaccion, duracion_seg FROM visitas WHERE ip = ? AND id <> ? ORDER BY fecha DESC LIMIT 25');
    $otras->execute([$v['ip'], $v['id']]);
    $otras = $otras->fetchAll();
    $cuentaIp = $db->prepare('SELECT COUNT(*) FROM visitas WHERE ip = ?');
    $cuentaIp->execute([$v['ip']]);
    $totalIp = (int) $cuentaIp->fetchColumn();

    $fila = fn($k, $val) => $val === '' || $val === null ? '' : '<tr><th>' . h($k) . '</th><td>' . $val . '</td></tr>';
    ?>
    <p><a href="<?= h(url_admin('visitas', array_intersect_key($_GET, array_flip(['rango', 'tipo', 'pais', 'q'])))) ?>">← Volver a visitas</a></p>
    <h1><?= bandera($v['pais_codigo']) ?> <?= h(lugar($v)) ?> <?= etiqueta_tipo($v['tipo']) ?></h1>
    <p class="intro"><?= h(date('d/m/Y H:i:s', strtotime($v['fecha']))) ?> (hora de Mexicali) · <?= h($v['motivo']) ?></p>

    <div class="dos-columnas">
      <section class="tarjeta">
        <h2>La visita</h2>
        <table class="tabla ficha-tabla">
          <?= $fila('Página', h($v['ruta']) . ($v['idioma'] ? ' (' . h(strtoupper($v['idioma'])) . ')' : '')) ?>
          <?= $fila('Llegó desde', h($v['fuente']) . ($v['referente'] ? '<br><small class="suave rompe">' . h($v['referente']) . '</small>' : '')) ?>
          <?= $fila('Campaña (UTM)', h(implode(' · ', array_filter([$v['utm_source'], $v['utm_medium'], $v['utm_campaign']])))) ?>
          <?= $fila('Tiempo en la página', h(duracion((int) $v['duracion_seg']))) ?>
          <?= $fila('Ejecutó JavaScript', $v['js'] ? 'Sí' : 'No (los bots casi nunca lo hacen)') ?>
          <?= $fila('Interactuó', $v['interaccion'] ? 'Sí: movió, tocó o desplazó la página' : 'No') ?>
          <?= $fila('Dispositivo', h(implode(' · ', array_filter([$v['dispositivo'], $v['sistema'], $v['navegador'], $v['pantalla'] ? 'pantalla ' . $v['pantalla'] : ''])))) ?>
          <?= $fila('Bot', h($v['nombre_bot'])) ?>
          <?= $fila('Idiomas del navegador', h($v['idioma_navegador'])) ?>
        </table>
        <h3>Navegador (user agent)</h3>
        <p class="ua"><?= h($v['ua'] ?: '(vacío)') ?></p>
        <?php if ($eventos): ?>
          <h3>Qué hizo</h3>
          <ul class="eventos">
            <?php foreach ($eventos as $e): ?>
              <li><span class="suave"><?= h(date('H:i:s', strtotime($e['fecha']))) ?></span> <strong><?= h(EVENTOS_VISITA[$e['evento']] ?? $e['evento']) ?></strong><?= $e['detalle'] ? ' · ' . h($e['detalle']) : '' ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <section class="tarjeta">
        <h2>La IP <code><?= h($v['ip']) ?></code></h2>
        <table class="tabla ficha-tabla">
          <?= $fila('País', $v['pais'] ? bandera($v['pais_codigo']) . ' ' . h($v['pais']) : '') ?>
          <?= $fila('Estado / región', h($v['region'])) ?>
          <?= $fila('Ciudad', h($v['ciudad'])) ?>
          <?= $fila('Código postal', h($v['codigo_postal'])) ?>
          <?= $fila('Ubicación aproximada', $v['latitud'] !== null ? '<a href="https://www.google.com/maps?q=' . h($v['latitud'] . ',' . $v['longitud']) . '" target="_blank" rel="noopener">' . h($v['latitud'] . ', ' . $v['longitud']) . ' ↗</a>' . ($v['radio_km'] ? ' <small class="suave">(margen de ±' . (int) $v['radio_km'] . ' km)</small>' : '') : '') ?>
          <?= $fila('Zona horaria', h($v['zona_horaria'])) ?>
          <?= $fila('Proveedor / dueño de la IP', h($v['organizacion']) . ($v['asn'] ? ' <small class="suave">(AS' . (int) $v['asn'] . ')</small>' : '')) ?>
          <?= $fila('Tipo de conexión', $v['centro_datos'] ? '<strong>Centro de datos / servidor</strong> (no es internet de casa ni de celular)' : ($v['organizacion'] && $v['organizacion'] !== 'Red local' ? 'Internet residencial, empresa o celular' : '')) ?>
          <?= $fila('Nombre del servidor (DNS inverso)', h((string) $v['host_inverso'])) ?>
          <?= $fila('Visitas desde esta IP', (string) $totalIp) ?>
        </table>
        <p class="enlaces-externos">Más información:
          <a href="https://ipinfo.io/<?= h($v['ip']) ?>" target="_blank" rel="noopener">ipinfo.io ↗</a> ·
          <a href="https://www.abuseipdb.com/check/<?= h($v['ip']) ?>" target="_blank" rel="noopener">AbuseIPDB (reportes de abuso) ↗</a>
        </p>
        <?php if ($v['ip'] === $miIp): ?><p class="alerta alerta-aviso">Esta es tu IP actual.</p><?php endif; ?>
        <?php if (!in_array($v['ip'], $excluidas, true)): ?>
          <form method="post" action="<?= h(url_admin('visitas')) ?>" data-confirmar="¿Marcar <?= h($v['ip']) ?> como IP propia? Sus visitas dejarán de contarse.">
            <?= csrf_campo() ?><input type="hidden" name="accion" value="excluir"><input type="hidden" name="ip" value="<?= h($v['ip']) ?>">
            <button class="btn btn-secundario">Es mía: no contar esta IP</button>
          </form>
        <?php endif; ?>
        <p class="suave nota">La ubicación por IP es aproximada: suele acertar el país y el estado; la ciudad puede ser la del proveedor de internet.</p>
      </section>
    </div>

    <?php if ($otras): ?>
    <section class="tarjeta">
      <h2>Otras visitas desde esta IP</h2>
      <div class="tabla-scroll"><table class="tabla">
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Página</th><th>Desde</th><th>Tiempo</th></tr></thead>
        <tbody><?php foreach ($otras as $o): ?>
          <tr><td class="nowrap"><a href="<?= h(url_admin('visitas', ['id' => $o['id']])) ?>"><?= h(date('d/m/Y H:i', strtotime($o['fecha']))) ?></a></td>
              <td><?= etiqueta_tipo($o['tipo']) ?></td><td><?= h($o['ruta']) ?></td><td><?= h($o['fuente']) ?></td><td><?= h(duracion((int) $o['duracion_seg'])) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
    </section>
    <?php endif; ?>
    <?php if (($GLOBALS['FUENTE_GEOIP'] ?? '') === 'dbip'): ?><p class="suave nota credito"><a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a> · datos bajo licencia CC BY 4.0</p><?php endif;
    return;
}

/* ================================================================ filtros */
$RANGOS = ['1' => 'Hoy', '7' => '7 días', '30' => '30 días', '90' => '90 días', '365' => '1 año'];
$FILTROS = [
    'personas'     => ['Personas',       ['humano', 'probable']],
    'humano'       => ['Personas confirmadas', ['humano']],
    'bots'         => ['Bots',           ['bot']],
    'vista_previa' => ['Vistas previas', ['vista_previa']],
    'herramienta'  => ['Herramientas',   ['herramienta']],
    'sospechoso'   => ['Sospechosos',    ['sospechoso']],
    'interno'      => ['Internas',       ['interno']],
    'todo'         => ['Todo',           array_keys(TIPOS_VISITA)],
];
$rango = isset($RANGOS[$_GET['rango'] ?? '']) ? $_GET['rango'] : '7';
$filtro = isset($FILTROS[$_GET['tipo'] ?? '']) ? $_GET['tipo'] : 'personas';
$pais = preg_match('/^[A-Z]{2}$/', (string) ($_GET['pais'] ?? '')) ? $_GET['pais'] : '';
$q = trim((string) ($_GET['q'] ?? ''));
$pagina = max(1, (int) ($_GET['pag'] ?? 1));
$porPagina = 50;

$desde = $rango === '1' ? date('Y-m-d 00:00:00') : date('Y-m-d H:i:s', strtotime("-$rango days"));
$base = ['fecha >= ?']; $pBase = [$desde];
$tipos = $FILTROS[$filtro][1];
$where = $base; $params = $pBase;
$where[] = 'tipo IN (' . implode(',', array_fill(0, count($tipos), '?')) . ')'; $params = array_merge($params, $tipos);
if ($pais) { $where[] = 'pais_codigo = ?'; $params[] = $pais; }
if ($q !== '') {
    $where[] = '(ip = ? OR ciudad LIKE ? OR organizacion LIKE ? OR fuente LIKE ? OR nombre_bot LIKE ? OR ua LIKE ?)';
    array_push($params, $q, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
}
$W = implode(' AND ', $where);
$consulta = function (string $sql, array $p) use ($db) { $st = $db->prepare($sql); $st->execute($p); return $st; };

/* ---------------------------------------------------------------- exportar CSV */
if (isset($_GET['csv'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="visitas-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // para que Excel respete los acentos
    $cols = ['fecha', 'tipo', 'nombre_bot', 'motivo', 'ip', 'pais', 'region', 'ciudad', 'organizacion', 'centro_datos', 'ruta', 'idioma',
             'fuente', 'referente', 'utm_source', 'utm_medium', 'utm_campaign', 'dispositivo', 'sistema', 'navegador', 'js', 'interaccion', 'duracion_seg', 'ua'];
    fputcsv($out, $cols);
    $st = $consulta('SELECT ' . implode(',', $cols) . " FROM visitas WHERE $W ORDER BY fecha DESC LIMIT 50000", $params);
    while ($r = $st->fetch()) { $r['tipo'] = TIPOS_VISITA[$r['tipo']] ?? $r['tipo']; fputcsv($out, $r); }
    exit;
}

/* ---------------------------------------------------------------- cifras */
$porTipo = $consulta("SELECT tipo, COUNT(*) n FROM visitas WHERE fecha >= ? GROUP BY tipo", $pBase)->fetchAll(PDO::FETCH_KEY_PAIR);
$personas = ($porTipo['humano'] ?? 0) + ($porTipo['probable'] ?? 0);
$robots = ($porTipo['bot'] ?? 0) + ($porTipo['herramienta'] ?? 0) + ($porTipo['sospechoso'] ?? 0);
$total = array_sum($porTipo) - ($porTipo['interno'] ?? 0);
$unicos = (int) $consulta("SELECT COUNT(DISTINCT visitante) FROM visitas WHERE fecha >= ? AND tipo IN ('humano','probable')", $pBase)->fetchColumn();
$contactos = $consulta("SELECT e.evento, COUNT(*) n FROM visitas_eventos e JOIN visitas v ON v.id = e.visita_id
                        WHERE v.fecha >= ? AND v.tipo IN ('humano','probable') AND e.evento IN ('whatsapp','llamar','correo','formulario')
                        GROUP BY e.evento", $pBase)->fetchAll(PDO::FETCH_KEY_PAIR);
$totalContactos = array_sum($contactos);

// Gráfica por día
$dias = (int) $rango === 1 ? 1 : min((int) $rango, 90);
$serie = $consulta("SELECT DATE(fecha) d,
                           SUM(tipo IN ('humano','probable')) p,
                           SUM(tipo IN ('bot','herramienta','sospechoso')) b,
                           SUM(tipo = 'vista_previa') v
                    FROM visitas WHERE fecha >= ? GROUP BY DATE(fecha)", [date('Y-m-d 00:00:00', strtotime('-' . ($dias - 1) . ' days'))])->fetchAll(PDO::FETCH_UNIQUE);

$top = function (string $col, string $tiposSql, int $lim = 8) use ($consulta, $pBase) {
    return $consulta("SELECT $col AS k, COUNT(*) n FROM visitas WHERE fecha >= ? AND tipo IN ($tiposSql) AND $col <> ''
                      GROUP BY $col ORDER BY n DESC LIMIT $lim", $pBase)->fetchAll();
};
$P = "'humano','probable'";
$fuentes = $top('fuente', $P);
$paises = $consulta("SELECT pais_codigo, pais, COUNT(*) n FROM visitas WHERE fecha >= ? AND tipo IN ($P) AND pais <> ''
                     GROUP BY pais_codigo, pais ORDER BY n DESC LIMIT 8", $pBase)->fetchAll();
$ciudades = $consulta("SELECT CONCAT(ciudad, ', ', region) k, COUNT(*) n FROM visitas WHERE fecha >= ? AND tipo IN ($P) AND ciudad <> ''
                       GROUP BY ciudad, region ORDER BY n DESC LIMIT 8", $pBase)->fetchAll();
$dispositivos = $top('dispositivo', $P, 4);
$idiomasTop = $consulta("SELECT UPPER(idioma) k, COUNT(*) n FROM visitas WHERE fecha >= ? AND tipo IN ($P) GROUP BY idioma ORDER BY n DESC", $pBase)->fetchAll();
$bots = $top('nombre_bot', "'bot','herramienta','vista_previa','sospechoso'", 10);

$totalFiltrado = (int) $consulta("SELECT COUNT(*) FROM visitas WHERE $W", $params)->fetchColumn();
$lista = $consulta("SELECT v.*, (SELECT GROUP_CONCAT(DISTINCT evento) FROM visitas_eventos e WHERE e.visita_id = v.id) eventos
                    FROM visitas v WHERE $W ORDER BY v.fecha DESC, v.id DESC LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina), $params)->fetchAll();
$paginas = max(1, (int) ceil($totalFiltrado / $porPagina));

$filtrosUrl = fn(array $cambios = []) => url_admin('visitas', array_filter(['rango' => $rango, 'tipo' => $filtro, 'pais' => $pais, 'q' => $q] + $cambios + [], fn($x) => $x !== '' && $x !== null));
function barras_top(array $filas, string $etiqueta = 'k', ?callable $fmt = null): string
{
    if (!$filas) return '<p class="suave">Sin datos todavía.</p>';
    $max = max(array_column($filas, 'n')) ?: 1;
    $html = '<ul class="barras">';
    foreach ($filas as $f) {
        $txt = $fmt ? $fmt($f) : h((string) $f[$etiqueta]);
        $pct = (int) round($f['n'] / $max * 100);
        $html .= '<li><span class="barra-txt">' . $txt . '</span><span class="barra-n">' . (int) $f['n'] . '</span>'
               . '<svg class="barra-graf" viewBox="0 0 100 4" preserveAspectRatio="none" aria-hidden="true"><rect width="' . $pct . '" height="4" rx="1"/></svg></li>';
    }
    return $html . '</ul>';
}
?>
<h1>Visitas</h1>
<p class="intro">Quién entra al sitio, desde dónde y si es una persona o un programa. Una visita se confirma como <strong>persona</strong> cuando mueve el mouse, toca la pantalla o desplaza la página; los bots casi nunca lo hacen.</p>

<?php if (!$hayGeo): ?>
<div class="alerta alerta-aviso">La ubicación por IP (país, ciudad, proveedor) todavía no está lista. El servidor descarga la base gratuita de DB-IP al publicar; si tras unos minutos sigue este aviso, revisa <code>docker compose -f docker-compose.prod.yml logs geoip</code>. Todo lo demás ya funciona.</div>
<?php endif; ?>

<form method="get" action="/admin/" class="filtros-visitas">
  <input type="hidden" name="p" value="visitas">
  <div class="segmentos" role="group" aria-label="Periodo">
    <?php foreach ($RANGOS as $k => $n): ?>
      <a href="<?= h($filtrosUrl(['rango' => $k, 'pag' => null])) ?>"<?= $k === $rango ? ' aria-current="true"' : '' ?>><?= h($n) ?></a>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="rango" value="<?= h($rango) ?>">
  <label class="sr" for="f-tipo">Mostrar</label>
  <select id="f-tipo" name="tipo" data-autoenviar>
    <?php foreach ($FILTROS as $k => [$n]): ?><option value="<?= h($k) ?>"<?= $k === $filtro ? ' selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?>
  </select>
  <?php if ($pais): ?><input type="hidden" name="pais" value="<?= h($pais) ?>"><?php endif; ?>
  <label class="sr" for="f-q">Buscar</label>
  <input id="f-q" name="q" type="search" value="<?= h($q) ?>" placeholder="IP, ciudad, proveedor, bot…">
  <button class="btn btn-primario">Filtrar</button>
  <a class="btn btn-secundario" href="<?= h($filtrosUrl(['csv' => 1])) ?>">Descargar CSV</a>
</form>
<?php if ($pais): ?><p>Filtrando por país: <?= bandera($pais) ?> <?= h($pais) ?> · <a href="<?= h($filtrosUrl(['pais' => ''])) ?>">quitar</a></p><?php endif; ?>

<div class="cifras">
  <div class="cifra"><strong><?= $personas ?></strong><span>visitas de personas</span></div>
  <div class="cifra"><strong><?= $unicos ?></strong><span>personas distintas (aprox.)</span></div>
  <div class="cifra"><strong><?= $totalContactos ?></strong><span>clics para contactar<?= $contactos ? ' · ' . h(implode(', ', array_map(fn($k, $n) => (EVENTOS_VISITA[$k] ?? $k) . " $n", array_keys($contactos), $contactos))) : '' ?></span></div>
  <div class="cifra"><strong><?= $total ? round($robots / $total * 100) : 0 ?>%</strong><span>del tráfico son bots (<?= $robots ?>)</span></div>
  <div class="cifra"><strong><?= (int) ($porTipo['vista_previa'] ?? 0) ?></strong><span>veces que compartieron el enlace</span></div>
</div>

<section class="tarjeta">
  <h2>Por día</h2>
  <?php
  $maxDia = 1;
  $puntos = [];
  for ($i = $dias - 1; $i >= 0; $i--) {
      $d = date('Y-m-d', strtotime("-$i days"));
      $r = $serie[$d] ?? ['p' => 0, 'b' => 0, 'v' => 0];
      $puntos[$d] = $r;
      $maxDia = max($maxDia, $r['p'] + $r['b'] + $r['v']);
  }
  $ancho = 100 / max(1, count($puntos));
  ?>
  <svg class="grafica" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="Visitas por día: personas, bots y vistas previas">
    <?php $x = 0; foreach ($puntos as $d => $r):
        $hP = $r['p'] / $maxDia * 38; $hB = $r['b'] / $maxDia * 38; $hV = $r['v'] / $maxDia * 38; $w = $ancho * 0.75; ?>
      <g><title><?= h(date('d/m', strtotime($d))) ?>: <?= (int) $r['p'] ?> personas, <?= (int) $r['b'] ?> bots, <?= (int) $r['v'] ?> vistas previas</title>
        <rect class="g-p" x="<?= $x ?>" y="<?= 40 - $hP ?>" width="<?= $w ?>" height="<?= $hP ?>"/>
        <rect class="g-b" x="<?= $x ?>" y="<?= 40 - $hP - $hB ?>" width="<?= $w ?>" height="<?= $hB ?>"/>
        <rect class="g-v" x="<?= $x ?>" y="<?= 40 - $hP - $hB - $hV ?>" width="<?= $w ?>" height="<?= $hV ?>"/></g>
    <?php $x += $ancho; endforeach; ?>
  </svg>
  <p class="leyenda"><span class="l-p"></span> Personas <span class="l-b"></span> Bots y programas <span class="l-v"></span> Vistas previas · máximo <?= $maxDia ?> en un día</p>
</section>

<div class="rejilla-top">
  <section class="tarjeta"><h2>De dónde llegan</h2><?= barras_top($fuentes) ?></section>
  <section class="tarjeta"><h2>Países</h2><?= barras_top($paises, 'k', fn($f) => '<a href="' . h($filtrosUrl(['pais' => $f['pais_codigo']])) . '">' . bandera($f['pais_codigo']) . ' ' . h($f['pais']) . '</a>') ?></section>
  <section class="tarjeta"><h2>Ciudades</h2><?= barras_top($ciudades) ?></section>
  <section class="tarjeta"><h2>Dispositivo e idioma</h2><?= barras_top(array_merge($dispositivos, array_map(fn($f) => ['k' => 'Sitio en ' . $f['k'], 'n' => $f['n']], array_filter($idiomasTop, fn($f) => $f['k'] !== '')))) ?></section>
  <section class="tarjeta"><h2>Bots y programas</h2><?= barras_top($bots) ?></section>
</div>

<section class="tarjeta">
  <h2><?= h($FILTROS[$filtro][0]) ?> <span class="suave">(<?= $totalFiltrado ?>)</span></h2>
  <?php if (!$lista): ?>
    <p class="suave">No hay visitas con estos filtros.</p>
  <?php else: ?>
  <div class="tabla-scroll"><table class="tabla lista-visitas">
    <thead><tr><th>Fecha</th><th>Tipo</th><th>Ubicación</th><th>IP y proveedor</th><th>Dispositivo</th><th>Desde</th><th>Tiempo</th><th>Hizo</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $v): ?>
      <tr>
        <td class="nowrap"><a href="<?= h($filtrosUrl(['id' => $v['id']])) ?>"><?= h(date('d/m H:i', strtotime($v['fecha']))) ?></a></td>
        <td><?= etiqueta_tipo($v['tipo']) ?><?= $v['nombre_bot'] ? '<br><small class="suave">' . h($v['nombre_bot']) . '</small>' : '' ?></td>
        <td><?= bandera($v['pais_codigo']) ?> <?= h(lugar($v)) ?></td>
        <td><code><?= h($v['ip']) ?></code><?= $v['centro_datos'] ? ' <span class="chip chip-aviso" title="IP de servidores en la nube">servidor</span>' : '' ?><br><small class="suave"><?= h($v['organizacion']) ?></small></td>
        <td><?= h(trim($v['dispositivo'] . ' · ' . $v['sistema'], ' ·')) ?><br><small class="suave"><?= h($v['navegador']) ?></small></td>
        <td><?= h($v['fuente']) ?><br><small class="suave"><?= h($v['ruta']) ?></small></td>
        <td class="nowrap"><?= h(duracion((int) $v['duracion_seg'])) ?></td>
        <td><?= $v['eventos'] ? h(implode(', ', array_map(fn($e) => EVENTOS_VISITA[$e] ?? $e, explode(',', $v['eventos'])))) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($paginas > 1): ?>
    <nav class="paginacion" aria-label="Páginas">
      <?php if ($pagina > 1): ?><a class="btn btn-secundario" href="<?= h($filtrosUrl(['pag' => $pagina - 1])) ?>">← Anteriores</a><?php endif; ?>
      <span class="suave">Página <?= $pagina ?> de <?= $paginas ?></span>
      <?php if ($pagina < $paginas): ?><a class="btn btn-secundario" href="<?= h($filtrosUrl(['pag' => $pagina + 1])) ?>">Siguientes →</a><?php endif; ?>
    </nav>
  <?php endif; ?>
  <?php endif; ?>
</section>

<div class="dos-columnas">
  <section class="tarjeta">
    <h2>Tus propias visitas</h2>
    <p>Tu IP ahora es <code><?= h($miIp) ?></code>. Márcala para que tus visitas no se mezclen con las de posibles clientes.</p>
    <?php if (!in_array($miIp, $excluidas, true)): ?>
      <form method="post" action="<?= h(url_admin('visitas')) ?>"><?= csrf_campo() ?><input type="hidden" name="accion" value="excluir"><input type="hidden" name="ip" value="<?= h($miIp) ?>">
        <button class="btn btn-secundario">No contar mi IP</button></form>
    <?php endif; ?>
    <?php if ($excluidas): ?>
      <ul class="lista-ips"><?php foreach ($excluidas as $ip): ?>
        <li><code><?= h($ip) ?></code>
          <form method="post" action="<?= h(url_admin('visitas')) ?>"><?= csrf_campo() ?><input type="hidden" name="accion" value="incluir"><input type="hidden" name="ip" value="<?= h($ip) ?>"><button class="btn-texto">quitar</button></form></li>
      <?php endforeach; ?></ul>
      <p class="suave nota">Tu IP de casa u oficina puede cambiar cada cierto tiempo; si ves visitas tuyas contadas, vuelve a marcarla.</p>
    <?php endif; ?>
  </section>
  <section class="tarjeta">
    <h2>Privacidad</h2>
    <form method="post" action="<?= h(url_admin('visitas')) ?>" class="fila-form"><?= csrf_campo() ?><input type="hidden" name="accion" value="dias">
      <label for="dias">Guardar visitas durante</label>
      <input id="dias" name="dias" type="number" min="30" max="730" value="<?= (int) ($cfg['visitas_dias'] ?? 180) ?>"> días
      <button class="btn btn-secundario">Guardar</button>
    </form>
    <p class="suave nota">No se usan cookies ni se identifica a nadie por nombre. La IP y la ubicación aproximada son datos personales según la ley mexicana; menciónalo en tu aviso de privacidad.</p>
    <p class="suave nota">Consejo: al compartir tu enlace, agrega <code>?utm_source=facebook</code> (o whatsapp, inmuebles24…) para saber exactamente de dónde llega cada persona.</p>
  </section>
</div>

<?php if (($GLOBALS['FUENTE_GEOIP'] ?? '') === 'dbip'): ?>
<p class="suave nota credito"><a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a> · datos bajo licencia CC BY 4.0</p>
<?php endif; ?>
