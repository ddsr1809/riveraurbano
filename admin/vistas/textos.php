<?php
/** Textos: editar en todos los idiomas, agrupados por sección. */
$db = db();
$idiomas = idiomas_activos();
$codigos = array_column($idiomas, 'codigo');
$defecto = 'es';
foreach ($idiomas as $i) if ($i['por_defecto']) $defecto = $i['codigo'];

// Todos los textos: $T[clave][idioma] = valor
$T = [];
foreach ($db->query('SELECT clave, idioma, valor FROM textos') as $r) $T[$r['clave']][$r['idioma']] = $r['valor'];
uksort($T, 'strnatcmp');

/* ---------------------------------------------------------------- guardar */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enviados = $_POST['t'] ?? [];
    $cambios = [];
    $errores = [];
    if (is_array($enviados)) {
        $upsert = $db->prepare('INSERT INTO textos (clave, idioma, valor) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
        $borrar = $db->prepare('DELETE FROM textos WHERE clave = ? AND idioma = ?');
        $db->beginTransaction();
        foreach ($enviados as $clave => $porIdioma) {
            if (!is_string($clave) || !isset($T[$clave]) || !is_array($porIdioma)) continue; // solo claves existentes
            foreach ($porIdioma as $idioma => $valor) {
                if (!in_array($idioma, $codigos, true) || !is_string($valor)) continue;
                $valor = trim(str_replace("\r\n", "\n", $valor));
                $antes = $T[$clave][$idioma] ?? null;
                if ($valor === ($antes ?? '')) continue;
                if (mb_strlen($valor) > 5000) { $errores[] = "$clave ($idioma): demasiado largo."; continue; }
                // Las variables {asi} deben conservarse para que el sitio funcione
                preg_match_all('/\{[a-z_]+\}/', $T[$clave][$defecto] ?? '', $vars);
                foreach ($vars[0] as $v) if ($valor !== '' && !str_contains($valor, $v)) {
                    $errores[] = "$clave ($idioma): debe incluir $v."; continue 2;
                }
                if ($valor === '') {
                    if ($idioma === $defecto) { $errores[] = "$clave: el texto en español no puede quedar vacío."; continue; }
                    $borrar->execute([$clave, $idioma]);   // sin traducción → se muestra el español
                } else {
                    $upsert->execute([$clave, $idioma, $valor]);
                }
                $cambios[] = "$clave [$idioma]: " . mb_strimwidth($antes ?? '(vacío)', 0, 60, '…') . ' → ' . mb_strimwidth($valor ?: '(vacío)', 0, 60, '…');
            }
        }
        $db->commit();
    }
    if ($cambios) {
        bitacora('Textos', implode("\n", $cambios));
        flash('ok', count($cambios) === 1 ? 'Se guardó 1 cambio.' : 'Se guardaron ' . count($cambios) . ' cambios.');
    } elseif (!$errores) {
        flash('aviso', 'No había cambios que guardar.');
    }
    foreach ($errores as $e) flash('error', $e);
    redirigir('textos', array_filter(['s' => $_POST['s'] ?? '', 'q' => $_POST['q'] ?? '', 'faltantes' => $_POST['faltantes'] ?? '']));
}

/* ---------------------------------------------------------------- filtros */
$secs = secciones();
$q = trim((string) ($_GET['q'] ?? ''));
$soloFaltantes = !empty($_GET['faltantes']);
$s = (string) ($_GET['s'] ?? '');
if (!isset($secs[$s]) && $s !== 'otros') $s = ($q === '' && !$soloFaltantes) ? 'portada' : '';

$falta = fn(string $c) => count(array_filter($codigos, fn($i) => !isset($T[$c][$i]))) > 0;

$conteo = [];
foreach (array_keys($T) as $c) {
    $sec = seccion_de($c);
    $conteo[$sec]['total'] = ($conteo[$sec]['total'] ?? 0) + 1;
    if ($falta($c)) $conteo[$sec]['faltan'] = ($conteo[$sec]['faltan'] ?? 0) + 1;
}

$mostrar = array_filter(array_keys($T), function ($c) use ($T, $q, $s, $soloFaltantes, $falta) {
    if ($s !== '' && seccion_de($c) !== $s) return false;
    if ($soloFaltantes && !$falta($c)) return false;
    if ($q !== '') {
        $heno = mb_strtolower($c . ' ' . implode(' ', $T[$c]));
        if (!str_contains($heno, mb_strtolower($q))) return false;
    }
    return true;
});
$tituloLista = $q !== '' ? "Resultados para “{$q}”" : ($soloFaltantes ? 'Textos sin traducir' : ($secs[$s][0] ?? 'Otros'));
?>
<h1>Textos del sitio</h1>
<p class="intro">Cambia lo que dice el sitio en cada idioma. Si dejas vacío un texto en inglés, se mostrará el de español.</p>

<div class="textos">
  <aside class="secciones">
    <form method="get" action="/admin/" class="buscar" role="search">
      <input type="hidden" name="p" value="textos">
      <label class="sr" for="q">Buscar texto</label>
      <input id="q" name="q" type="search" placeholder="Buscar… (ej. superficie)" value="<?= h($q) ?>">
    </form>
    <nav aria-label="Secciones">
      <?php foreach ($secs + (isset($conteo['otros']) ? ['otros' => ['Otros', []]] : []) as $id => [$nombre]): ?>
        <a href="<?= h(url_admin('textos', ['s' => $id])) ?>"<?= $id === $s && $q === '' && !$soloFaltantes ? ' aria-current="page"' : '' ?>>
          <?= h($nombre) ?>
          <span class="chip<?= !empty($conteo[$id]['faltan']) ? ' chip-aviso' : '' ?>"><?= (int) ($conteo[$id]['total'] ?? 0) ?></span>
        </a>
      <?php endforeach; ?>
      <a href="<?= h(url_admin('textos', ['faltantes' => 1])) ?>"<?= $soloFaltantes ? ' aria-current="page"' : '' ?>>Sin traducir</a>
    </nav>
  </aside>

  <section>
    <h2><?= h($tituloLista) ?> <span class="suave">(<?= count($mostrar) ?>)</span></h2>
    <?php if (!$mostrar): ?>
      <p class="suave">No hay textos que coincidan.</p>
    <?php else: ?>
    <form method="post" action="<?= h(url_admin('textos')) ?>" class="form-textos" data-avisar-cambios>
      <?= csrf_campo() ?>
      <input type="hidden" name="s" value="<?= h($s) ?>"><input type="hidden" name="q" value="<?= h($q) ?>">
      <input type="hidden" name="faltantes" value="<?= $soloFaltantes ? '1' : '' ?>">
      <?php foreach ($mostrar as $c): ?>
        <?php preg_match_all('/\{[a-z_]+\}/', $T[$c][$defecto] ?? '', $vars); ?>
        <fieldset class="texto">
          <legend><strong><?= h(etiqueta_texto($c, $T)) ?></strong> <code><?= h($c) ?></code>
            <?php foreach (array_unique($vars[0]) as $v): ?><span class="chip" title="Déjalo tal cual: el sitio lo reemplaza automáticamente">usa <?= h($v) ?></span><?php endforeach; ?>
          </legend>
          <div class="idiomas-grid">
            <?php foreach ($idiomas as $i): $val = $T[$c][$i['codigo']] ?? ''; $id = 'f-' . md5($c . $i['codigo']); ?>
              <div class="campo<?= $val === '' ? ' vacio' : '' ?>">
                <label for="<?= $id ?>"><?= h($i['nombre']) ?><?= $val === '' ? ' <span class="chip chip-aviso">falta</span>' : '' ?></label>
                <textarea id="<?= $id ?>" name="t[<?= h($c) ?>][<?= h($i['codigo']) ?>]" rows="<?= max(1, min(10, (int) ceil(mb_strlen($val) / 42))) ?>" lang="<?= h($i['codigo']) ?>"
                  data-original="<?= h($val) ?>"<?= $i['codigo'] !== $defecto && $val === '' ? ' placeholder="' . h($T[$c][$defecto] ?? '') . '"' : '' ?>><?= h($val) ?></textarea>
              </div>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endforeach; ?>
      <div class="barra-guardar">
        <span class="contador-cambios" data-contador>Sin cambios</span>
        <button class="btn btn-primario">Guardar cambios</button>
      </div>
    </form>
    <?php endif; ?>
  </section>
</div>
