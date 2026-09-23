<?php
/** Video: subir, reemplazar o quitar los videos del dron. */
$dir = carpeta_video();
$SLOTS = [
    'dron.mp4'       => ['Video completo del dron', 'Aparece en la sección “Recorre los terrenos desde el aire”, con controles y sonido.'],
    'dron-corto.mp4' => ['Clip para la portada', 'Se reproduce en silencio y en bucle de fondo al abrir el sitio. Ideal: 10 a 20 segundos, sin audio, 720p.'],
];
$limite = min(ini_bytes((string) ini_get('upload_max_filesize')), ini_bytes((string) ini_get('post_max_size')));
$escribible = is_dir($dir) && is_writable($dir);

$responder = function (bool $ok, string $msg) {
    if (!empty($_POST['ajax'])) {
        if ($ok) flash('ok', $msg);   // se muestra al recargar
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'mensaje' => $msg]);
        exit;
    }
    flash($ok ? 'ok' : 'error', $msg);
    redirigir('archivos');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si el archivo supera post_max_size, PHP vacía $_POST (y el CSRF ya habría fallado antes).
    $slot = (string) ($_POST['slot'] ?? '');
    if (!isset($SLOTS[$slot])) $responder(false, 'Destino no válido.');
    $destino = "$dir/$slot";

    if (($_POST['accion'] ?? '') === 'borrar') {
        if (is_file($destino) && @unlink($destino)) {
            bitacora('Video', "Se quitó $slot");
            $responder(true, "Se quitó {$SLOTS[$slot][0]}.");
        }
        $responder(false, 'No se pudo quitar el archivo.');
    }

    if (!$escribible) $responder(false, 'La carpeta de videos no tiene permisos de escritura en el servidor.');
    $f = $_FILES['video'] ?? null;
    if (!$f || !is_array($f) || is_array($f['error'])) $responder(false, 'No llegó ningún archivo.');
    $err = [
        UPLOAD_ERR_INI_SIZE   => 'El video pesa más de ' . tamano_legible($limite) . '. Comprímelo antes de subirlo.',
        UPLOAD_ERR_FORM_SIZE  => 'El video es demasiado grande.',
        UPLOAD_ERR_PARTIAL    => 'La subida se interrumpió. Vuelve a intentarlo.',
        UPLOAD_ERR_NO_FILE    => 'Elige un archivo primero.',
        UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal.',
        UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo guardar el archivo.',
    ];
    if ($f['error'] !== UPLOAD_ERR_OK) $responder(false, $err[$f['error']] ?? 'Error al subir el archivo.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if ($mime !== 'video/mp4') $responder(false, "Solo se aceptan videos MP4 (llegó: $mime). Si es .mov, conviértelo a MP4.");

    $tmp = "$destino.subiendo";
    if (!move_uploaded_file($f['tmp_name'], $tmp) || !rename($tmp, $destino)) {
        @unlink($tmp);
        $responder(false, 'No se pudo guardar el video en el servidor.');
    }
    @chmod($destino, 0664);
    bitacora('Video', "Se subió $slot (" . tamano_legible((float) filesize($destino)) . ", archivo original: " . basename((string) $f['name']) . ')');
    $responder(true, "{$SLOTS[$slot][0]} actualizado.");
}

$libre = @disk_free_space($dir) ?: 0;
?>
<h1>Video del dron</h1>
<p class="intro">Sube los videos en MP4. Si pesan mucho, comprímelos primero (ver abajo). El video nuevo aparece en el sitio en cuanto termina de subir.</p>

<?php if (!$escribible): ?>
  <div class="alerta alerta-error">La carpeta de videos (<code><?= h($dir) ?></code>) no permite guardar archivos. El siguiente despliegue corrige los permisos automáticamente.</div>
<?php endif; ?>

<div class="slots">
<?php foreach ($SLOTS as $slot => [$nombre, $ayuda]): $ruta = "$dir/$slot"; $existe = is_file($ruta); ?>
  <section class="tarjeta slot">
    <h2><?= h($nombre) ?></h2>
    <p class="suave"><?= h($ayuda) ?></p>
    <?php if ($existe): ?>
      <video src="/assets/video/<?= h($slot) ?>?v=<?= filemtime($ruta) ?>" controls muted preload="metadata" playsinline></video>
      <p class="meta"><?= h(tamano_legible((float) filesize($ruta))) ?> · subido el <?= h(date('d/m/Y H:i', filemtime($ruta))) ?></p>
    <?php else: ?>
      <div class="sin-video">Aún no hay video</div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="<?= h(url_admin('archivos')) ?>" class="subir" data-subir data-limite="<?= $limite ?>">
      <?= csrf_campo() ?>
      <input type="hidden" name="slot" value="<?= h($slot) ?>">
      <label class="btn btn-secundario elegir">
        <?= $existe ? 'Reemplazar video' : 'Elegir video' ?>
        <input type="file" name="video" accept="video/mp4" required>
      </label>
      <button class="btn btn-primario">Subir</button>
      <div class="progreso" hidden><div class="progreso-barra"></div><span class="progreso-texto"></span></div>
    </form>

    <?php if ($existe): ?>
      <form method="post" action="<?= h(url_admin('archivos')) ?>" data-confirmar="¿Quitar <?= h($nombre) ?> del sitio?">
        <?= csrf_campo() ?><input type="hidden" name="slot" value="<?= h($slot) ?>"><input type="hidden" name="accion" value="borrar">
        <button class="btn-texto peligro">Quitar video</button>
      </form>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>

<section class="tarjeta">
  <h2>Antes de subir</h2>
  <p>Tamaño máximo por video: <strong><?= h(tamano_legible($limite)) ?></strong> · Espacio libre en el servidor: <strong><?= h(tamano_legible((float) $libre)) ?></strong></p>
  <p>Para comprimir sin instalar nada, usa <a href="https://handbrake.fr/" target="_blank" rel="noopener">HandBrake</a> (gratis) con el ajuste <em>Fast 1080p30</em> para el video completo y <em>Fast 720p30</em> sin audio para el clip de portada. Un video de 2 a 3 minutos suele quedar entre 40 y 120 MB.</p>
</section>
