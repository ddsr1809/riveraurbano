<?php /** Pantalla de acceso. Variables: $error */ ?>
<div class="login">
  <div class="login-caja">
    <svg class="login-logo" viewBox="0 0 64 64" aria-hidden="true"><rect x="10" y="16" width="36" height="36" fill="none" stroke="#F5B400" stroke-width="5" stroke-dasharray="9 5"/><circle cx="46" cy="16" r="7" fill="#F5B400"/></svg>
    <h1>Panel de Rivera Urbano</h1>
    <?php if ($error): ?><div class="alerta alerta-error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post" action="<?= h(url_admin('inicio')) ?>" class="formulario-login">
      <?= csrf_campo() ?>
      <label for="usuario">Usuario</label>
      <input id="usuario" name="usuario" autocomplete="username" autocapitalize="none" required autofocus value="<?= h((string) ($_POST['usuario'] ?? '')) ?>">
      <label for="clave">Contraseña</label>
      <input id="clave" name="clave" type="password" autocomplete="current-password" required>
      <button class="btn btn-primario">Entrar</button>
    </form>
  </div>
</div>
