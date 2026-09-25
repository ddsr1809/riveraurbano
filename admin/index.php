<?php
declare(strict_types=1);

/**
 * riveraurbano.com — Panel de administración  (/admin/)
 */

require __DIR__ . '/lib.php';

header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; media-src 'self'; style-src 'self'; script-src 'self'; frame-ancestors 'none'; form-action 'self'");

try {
    iniciar_sesion();
    $PAGINAS = [
        'inicio'   => 'Inicio',
        'visitas'  => 'Visitas',
        'textos'   => 'Textos',
        'config'   => 'Contacto y ajustes',
        'archivos' => 'Video',
        'cuenta'   => 'Mi cuenta',
    ];
    $p = (string) ($_GET['p'] ?? 'inicio');
    $metodo = $_SERVER['REQUEST_METHOD'];

    // Cerrar sesión
    if ($p === 'salir' && $metodo === 'POST' && csrf_valido()) {
        cerrar_sesion();
        flash('ok', 'Cerraste sesión.');
        redirigir('inicio');
    }

    $ADMIN = admin_actual();

    // Sin sesión: pantalla de acceso
    if (!$ADMIN) {
        $error = null;
        if ($metodo === 'POST') {
            $error = csrf_valido()
                ? iniciar_con((string) ($_POST['usuario'] ?? ''), (string) ($_POST['clave'] ?? ''))
                : 'La página expiró. Vuelve a intentarlo.';
            if ($error === null) redirigir('inicio');
        }
        $titulo = 'Entrar';
        ob_start();
        require __DIR__ . '/vistas/login.php';
        $contenido = ob_get_clean();
        require __DIR__ . '/vistas/_marco.php';
        exit;
    }

    if (!isset($PAGINAS[$p])) $p = 'inicio';

    if ($metodo === 'POST' && !csrf_valido()) {
        flash('error', 'La página expiró por seguridad. Vuelve a intentarlo.');
        redirigir($p);
    }

    $titulo = $PAGINAS[$p];
    ob_start();
    require __DIR__ . "/vistas/$p.php";
    $contenido = ob_get_clean();
    require __DIR__ . '/vistas/_marco.php';

} catch (Throwable $e) {
    error_log('[riveraurbano/admin] ' . $e);
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><body style="font-family:system-ui;padding:2rem">'
       . '<h1>Algo salió mal</h1><p>No se pudo conectar con la base de datos o hubo un error interno. '
       . 'Revisa los registros del servidor.</p>';
    if (!empty(config()['debug'])) echo '<pre>' . h($e->getMessage()) . '</pre>';
}
