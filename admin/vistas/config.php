<?php
/** Contacto y ajustes: WhatsApp, teléfono, correo, video, mapa. */
$db = db();
$cfg = $db->query('SELECT clave, valor, descripcion FROM configuracion ORDER BY clave')->fetchAll(PDO::FETCH_UNIQUE);

$CAMPOS = [
    'whatsapp'      => ['Número de WhatsApp', 'tel', 'Con lada de país, solo números. México celular: 52 + 1 + 10 dígitos o 52 + 10 dígitos. Ej: 5216861234567'],
    'telefono'      => ['Teléfono (como se ve en el sitio)', 'text', 'Ej: 686 123 4567'],
    'correo'        => ['Correo de contacto', 'email', 'A este correo llegan los mensajes del formulario.'],
    'youtube_id'    => ['Video en YouTube (opcional)', 'text', 'Pega el enlace del video o su ID. Si lo dejas vacío se usa el video subido en la sección Video.'],
    'video_portada' => ['Clip de la portada', 'text', 'Normalmente /assets/video/dron-corto.mp4. Déjalo vacío para mostrar solo la imagen.'],
    'mapa'          => ['Ubicación en el mapa', 'text', 'Dirección o coordenadas. Con coordenadas el mapa marca el punto exacto. Ej: 32.6245,-115.4523'],
    'url_base'      => ['Dirección del sitio', 'url', 'Sin diagonal final. Ej: https://riveraurbano.com'],
];

function validar_config(string $clave, string $v): array  // [valor limpio, error|null]
{
    $v = trim($v);
    switch ($clave) {
        case 'whatsapp':
            $v = preg_replace('/\D/', '', $v);
            return [$v, preg_match('/^\d{10,15}$/', $v) ? null : 'El WhatsApp debe tener entre 10 y 15 dígitos, incluyendo la lada 52.'];
        case 'correo':
            return [$v, filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'El correo no es válido.'];
        case 'youtube_id':
            if ($v === '') return ['', null];
            if (preg_match('~(?:v=|youtu\.be/|embed/|shorts/)([A-Za-z0-9_-]{11})~', $v, $m)) $v = $m[1];
            return [$v, preg_match('/^[A-Za-z0-9_-]{11}$/', $v) ? null : 'No reconozco ese enlace de YouTube.'];
        case 'video_portada':
            return [$v, $v === '' || preg_match('~^/[A-Za-z0-9/_.-]+\.(mp4|webm)$~', $v) ? null : 'La ruta del clip debe empezar con / y terminar en .mp4 o .webm.'];
        case 'url_base':
            $v = rtrim($v, '/');
            return [$v, filter_var($v, FILTER_VALIDATE_URL) && str_starts_with($v, 'http') ? null : 'La dirección del sitio no es válida.'];
        case 'telefono': case 'mapa':
            return [$v, $v !== '' && mb_strlen($v) <= 200 ? null : 'Este campo no puede quedar vacío.'];
    }
    return [$v, mb_strlen($v) <= 500 ? null : 'Demasiado largo.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cambios = []; $errores = [];
    $st = $db->prepare('UPDATE configuracion SET valor = ? WHERE clave = ?');
    foreach ((array) ($_POST['c'] ?? []) as $clave => $valor) {
        if (!isset($cfg[$clave]) || !is_string($valor)) continue;
        [$limpio, $error] = validar_config($clave, $valor);
        if ($error) { $errores[] = $error; continue; }
        if ($limpio === $cfg[$clave]['valor']) continue;
        $st->execute([$limpio, $clave]);
        $cambios[] = "$clave: {$cfg[$clave]['valor']} → $limpio";
    }
    if ($cambios) { bitacora('Ajustes', implode("\n", $cambios)); flash('ok', 'Ajustes guardados.'); }
    elseif (!$errores) flash('aviso', 'No había cambios que guardar.');
    foreach ($errores as $e) flash('error', $e);
    redirigir('config');
}
?>
<h1>Contacto y ajustes</h1>
<p class="intro">Estos datos se usan en todos los botones de WhatsApp, llamada y correo del sitio, en los dos idiomas.</p>

<form method="post" action="<?= h(url_admin('config')) ?>" class="tarjeta form-config" data-avisar-cambios>
  <?= csrf_campo() ?>
  <?php foreach ($cfg as $clave => $fila): [$etiqueta, $tipo, $ayuda] = $CAMPOS[$clave] ?? [$clave, 'text', $fila['descripcion']]; ?>
    <div class="campo">
      <label for="c-<?= h($clave) ?>"><?= h($etiqueta) ?></label>
      <input id="c-<?= h($clave) ?>" name="c[<?= h($clave) ?>]" type="<?= h($tipo) ?>" value="<?= h($fila['valor']) ?>" data-original="<?= h($fila['valor']) ?>"
             <?= $tipo === 'tel' ? 'inputmode="numeric"' : '' ?> autocomplete="off">
      <small><?= h($ayuda) ?></small>
    </div>
  <?php endforeach; ?>
  <div class="barra-guardar">
    <span class="contador-cambios" data-contador>Sin cambios</span>
    <button class="btn btn-primario">Guardar ajustes</button>
  </div>
</form>

<?php $wa = $cfg['whatsapp']['valor'] ?? ''; if ($wa): ?>
<p class="suave">Prueba tu número: <a href="https://wa.me/<?= h($wa) ?>?text=<?= rawurlencode('Prueba desde el panel de riveraurbano.com') ?>" target="_blank" rel="noopener">abrir chat de WhatsApp ↗</a></p>
<?php endif; ?>
