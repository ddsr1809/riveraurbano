<?php
/** Mi cuenta: cambiar contraseña y nombre. */
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'nombre') {
        $nombre = mb_substr(trim((string) ($_POST['nombre'] ?? '')), 0, 80);
        $db->prepare('UPDATE administradores SET nombre = ? WHERE id = ?')->execute([$nombre, $ADMIN['id']]);
        flash('ok', 'Nombre actualizado.');
    } elseif ($accion === 'clave') {
        $actual = (string) ($_POST['actual'] ?? '');
        $nueva  = (string) ($_POST['nueva'] ?? '');
        $otra   = (string) ($_POST['confirmar'] ?? '');
        $st = $db->prepare('SELECT hash FROM administradores WHERE id = ?');
        $st->execute([$ADMIN['id']]);
        if (!password_verify($actual, (string) $st->fetchColumn())) {
            flash('error', 'La contraseña actual no es correcta.');
        } elseif (mb_strlen($nueva) < 12) {
            flash('error', 'La contraseña nueva debe tener al menos 12 caracteres.');
        } elseif ($nueva !== $otra) {
            flash('error', 'Las contraseñas nuevas no coinciden.');
        } else {
            $db->prepare('UPDATE administradores SET hash = ? WHERE id = ?')->execute([password_hash($nueva, PASSWORD_DEFAULT), $ADMIN['id']]);
            session_regenerate_id(true);
            bitacora('Cuenta', 'Cambió su contraseña');
            flash('ok', 'Contraseña cambiada.');
        }
    }
    redirigir('cuenta');
}
?>
<h1>Mi cuenta</h1>
<p class="intro">Usuario <strong><?= h($ADMIN['usuario']) ?></strong><?= $ADMIN['ultimo_acceso'] ? ' · último acceso ' . h(date('d/m/Y H:i', strtotime($ADMIN['ultimo_acceso']))) : '' ?></p>

<div class="dos-columnas">
  <form method="post" action="<?= h(url_admin('cuenta')) ?>" class="tarjeta">
    <?= csrf_campo() ?><input type="hidden" name="accion" value="clave">
    <h2>Cambiar contraseña</h2>
    <div class="campo"><label for="actual">Contraseña actual</label><input id="actual" name="actual" type="password" autocomplete="current-password" required></div>
    <div class="campo"><label for="nueva">Contraseña nueva</label><input id="nueva" name="nueva" type="password" autocomplete="new-password" minlength="12" required><small>Mínimo 12 caracteres. Una frase de varias palabras funciona bien.</small></div>
    <div class="campo"><label for="confirmar">Repite la contraseña nueva</label><input id="confirmar" name="confirmar" type="password" autocomplete="new-password" minlength="12" required></div>
    <button class="btn btn-primario">Cambiar contraseña</button>
  </form>

  <form method="post" action="<?= h(url_admin('cuenta')) ?>" class="tarjeta">
    <?= csrf_campo() ?><input type="hidden" name="accion" value="nombre">
    <h2>Tu nombre</h2>
    <div class="campo"><label for="nombre">Nombre para mostrar</label><input id="nombre" name="nombre" value="<?= h($ADMIN['nombre']) ?>" maxlength="80"></div>
    <button class="btn btn-secundario">Guardar</button>
  </form>
</div>
