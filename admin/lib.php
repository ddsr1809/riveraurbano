<?php
declare(strict_types=1);

/**
 * riveraurbano.com — Panel de administración: sesión, seguridad y utilidades.
 */

require __DIR__ . '/../includes/bootstrap.php';

const SESION_MINUTOS    = 120;   // cierre automático por inactividad
const MAX_FALLOS_IP     = 10;    // intentos fallidos por IP en la ventana
const MAX_FALLOS_USUARIO = 5;    // intentos fallidos por usuario en la ventana
const VENTANA_MINUTOS   = 15;

/* ------------------------------------------------------------------ sesión */

function iniciar_sesion(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('ru_admin');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/admin/', 'secure' => es_https(),
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();

    $ahora = time();
    if (isset($_SESSION['ultimo']) && $ahora - $_SESSION['ultimo'] > SESION_MINUTOS * 60) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('aviso', 'Tu sesión se cerró por inactividad. Vuelve a entrar.');
    }
    $_SESSION['ultimo'] = $ahora;
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function admin_actual(): ?array
{
    $id = $_SESSION['admin_id'] ?? null;
    if (!$id) return null;
    $st = db()->prepare('SELECT id, usuario, nombre, ultimo_acceso FROM administradores WHERE id = ? AND activo = 1');
    $st->execute([$id]);
    $a = $st->fetch();
    if (!$a) { cerrar_sesion(); return null; }
    return $a;
}

/** Intenta iniciar sesión. Devuelve null si entró, o el mensaje de error. */
function iniciar_con(string $usuario, string $clave): ?string
{
    $db = db();
    $ip = ip_cliente();
    $usuario = mb_strtolower(trim($usuario));

    $st = $db->prepare('SELECT
        (SELECT COUNT(*) FROM intentos_login WHERE ip = ? AND exito = 0 AND fecha > NOW() - INTERVAL ? MINUTE) AS por_ip,
        (SELECT COUNT(*) FROM intentos_login WHERE usuario = ? AND exito = 0 AND fecha > NOW() - INTERVAL ? MINUTE) AS por_usuario');
    $st->execute([$ip, VENTANA_MINUTOS, $usuario, VENTANA_MINUTOS]);
    $f = $st->fetch();
    if ($f['por_ip'] >= MAX_FALLOS_IP || $f['por_usuario'] >= MAX_FALLOS_USUARIO) {
        return 'Demasiados intentos fallidos. Espera ' . VENTANA_MINUTOS . ' minutos e inténtalo de nuevo.';
    }

    $st = $db->prepare('SELECT id, hash FROM administradores WHERE usuario = ? AND activo = 1');
    $st->execute([$usuario]);
    $a = $st->fetch();
    // Se verifica aunque el usuario no exista, para no revelar cuáles existen por el tiempo de respuesta.
    $hash = $a['hash'] ?? '$2y$10$nB6LYKM/KJzTxQhpxr.keuVSgpcf/GySvPvkBpqiLFzY1PfuUc2qa';
    $ok = password_verify($clave, $hash) && $a;

    $db->prepare('INSERT INTO intentos_login (ip, usuario, exito) VALUES (?, ?, ?)')
       ->execute([$ip, mb_substr($usuario, 0, 40), $ok ? 1 : 0]);
    // Limpieza ocasional
    if (random_int(1, 50) === 1) $db->exec('DELETE FROM intentos_login WHERE fecha < NOW() - INTERVAL 30 DAY');

    if (!$ok) return 'Usuario o contraseña incorrectos.';

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $a['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $db->prepare('UPDATE administradores SET ultimo_acceso = NOW() WHERE id = ?')->execute([$a['id']]);
    if (password_needs_rehash($a['hash'], PASSWORD_DEFAULT)) {
        $db->prepare('UPDATE administradores SET hash = ? WHERE id = ?')->execute([password_hash($clave, PASSWORD_DEFAULT), $a['id']]);
    }
    return null;
}

/* ------------------------------------------------------------------ CSRF */

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_valido(): bool
{
    $enviado = (string) ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '');
    return $enviado !== '' && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $enviado);
}

/* ------------------------------------------------------------------ utilidades */

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = [$tipo, $mensaje];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function url_admin(string $pagina, array $params = []): string
{
    return '/admin/?' . http_build_query(['p' => $pagina] + $params);
}

function redirigir(string $pagina, array $params = []): never
{
    header('Location: ' . url_admin($pagina, $params), true, 303);
    exit;
}

function bitacora(string $accion, string $detalle): void
{
    global $ADMIN;
    db()->prepare('INSERT INTO bitacora (admin_id, usuario, accion, detalle) VALUES (?, ?, ?, ?)')
        ->execute([$ADMIN['id'] ?? null, $ADMIN['usuario'] ?? '', $accion, mb_substr($detalle, 0, 5000)]);
}

function idiomas_activos(): array
{
    return db()->query('SELECT codigo, nombre, por_defecto FROM idiomas WHERE activo = 1 ORDER BY orden, codigo')->fetchAll();
}

function tamano_legible(float $bytes): string
{
    foreach (['B', 'KB', 'MB', 'GB'] as $u) {
        if ($bytes < 1024 || $u === 'GB') return number_format($bytes, $u === 'B' ? 0 : 1) . " $u";
        $bytes /= 1024;
    }
    return '';
}

function ini_bytes(string $valor): int
{
    $valor = trim($valor);
    $n = (int) $valor;
    return match (strtolower(substr($valor, -1))) {
        'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
    };
}

/** Carpeta donde viven los videos del dron (en Docker es un volumen del servidor). */
function carpeta_video(): string
{
    return rtrim(getenv('MEDIA_VIDEO_DIR') ?: RAIZ . '/assets/video', '/');
}

/** Secciones del sitio para agrupar los textos en el panel. */
function secciones(): array
{
    return [
        'portada'   => ['Portada',                    ['hero']],
        'terrenos'  => ['Terrenos y croquis',         ['terrenos', 'croquis', 'lote']],
        'video'     => ['Video',                      ['video']],
        'propuesta' => ['Propuesta y formas de renta', ['propuesta', 'uso', 'esquemas', 'esquema']],
        'pasos'     => ['Pasos para rentar',          ['pasos', 'paso']],
        'faq'       => ['Preguntas frecuentes',       ['faq']],
        'contacto'  => ['Contacto y formulario',      ['contacto', 'canal', 'mapa', 'form']],
        'mensajes'  => ['Mensajes de WhatsApp y correo', ['wa', 'msg', 'correo']],
        'menu'      => ['Menú y pie de página',       ['nav', 'pie', 'aria']],
        'seo'       => ['Google y redes sociales',    ['meta', 'og']],
    ];
}

function seccion_de(string $clave): string
{
    $prefijo = explode('.', $clave)[0];
    foreach (secciones() as $id => [, $prefijos]) if (in_array($prefijo, $prefijos, true)) return $id;
    return 'otros';
}

/** Nombre legible de una clave de texto, ej. "lote.a.superficie" → "Lote A · Superficie". */
function etiqueta_texto(string $clave, array $T): string
{
    $p = explode('.', $clave);
    $n = count($p);
    $base = [
        'titulo' => 'Título', 'sub' => 'Subtítulo', 'intro' => 'Texto de introducción', 'texto' => 'Texto',
        'nombre' => 'Nombre', 'donde' => 'Ubicación', 'para' => 'Para quién es', 'nota' => 'Nota',
        'descripcion' => 'Descripción', 'pie' => 'Texto al pie', 'btn' => 'Botón', 'btn_info' => 'Botón principal',
        'btn_video' => 'Botón del video', 'btn_visita' => 'Botón de visita', 'btn_preguntar' => 'Botón “preguntar”',
        'btn_whats' => 'Botón enviar por WhatsApp', 'btn_correo' => 'Botón enviar por correo',
        'sin_archivo' => 'Aviso cuando no hay video', 'no_soportado' => 'Aviso de navegador sin video',
        'aviso' => 'Aviso de privacidad', 'error' => 'Mensaje de error', 'nombre_error' => 'Error: falta nombre',
        'telefono_error' => 'Error: falta teléfono', 'mensaje_ph' => 'Texto de ejemplo del mensaje',
        'general' => 'Mensaje general', 'info' => 'Pedir información', 'video' => 'Después de ver el video',
        'asunto' => 'Asunto del correo', 'asunto_form' => 'Asunto del correo del formulario', 'encabezado' => 'Primera línea',
        'elegir' => 'Texto “elegir lote”', 'ver_lote' => 'Texto al tocar un lote', 'pendiente' => 'Palabra para “por confirmar”',
        'dato1' => 'Dato 1', 'dato2' => 'Dato 2', 'dato3' => 'Dato 3', 'direccion' => 'Dirección',
    ];
    $pref = [
        'hero' => 'Portada', 'terrenos' => 'Terrenos', 'croquis' => 'Croquis', 'video' => 'Video', 'propuesta' => 'Propuesta',
        'esquemas' => 'Formas de renta', 'pasos' => 'Pasos', 'faq' => 'Preguntas', 'contacto' => 'Contacto', 'mapa' => 'Mapa',
        'form' => 'Formulario', 'wa' => 'WhatsApp', 'msg' => 'Mensaje del formulario', 'correo' => 'Correo', 'nav' => 'Menú',
        'pie' => 'Pie de página', 'aria' => 'Accesibilidad', 'meta' => 'Google', 'og' => 'Redes sociales', 'canal' => 'Contacto',
    ];
    $ult = $p[$n - 1];
    $cual = $base[$ult] ?? ucfirst(str_replace('_', ' ', $ult));

    if ($p[0] === 'lote' && $n === 3 && strlen($p[1]) === 1) {
        $campo = $T["lote.campo.$ult"]['es'] ?? ($base[$ult] ?? ucfirst(str_replace('_', ' ', $ult)));
        return 'Lote ' . strtoupper($p[1]) . ' · ' . $campo;
    }
    if ($p[0] === 'lote' && ($p[1] ?? '') === 'campo') return 'Nombre del dato “' . ($T[$clave]['es'] ?? $ult) . '”';
    if ($p[0] === 'faq' && $n === 3) return 'Pregunta ' . $p[1] . ' · ' . ($ult === 'p' ? 'pregunta' : 'respuesta');
    if (in_array($p[0], ['uso', 'paso'], true) && $n === 3) return ($p[0] === 'uso' ? 'Giro ' : 'Paso ') . $p[1] . ' · ' . $cual;
    if ($p[0] === 'esquema' && $n === 3) {
        $cual = preg_match('/^li(\d)$/', $ult, $m) ? 'Punto ' . $m[1] : $cual;
        return 'Forma de renta ' . $p[1] . ' · ' . $cual;
    }
    if ($p[0] === 'wa' && ($p[1] ?? '') === 'esquema') return 'WhatsApp · Forma de renta ' . $ult;
    if ($p[0] === 'form' && $n === 3) return 'Formulario · opción de “' . ($base[$p[1]] ?? $p[1]) . '” ' . $ult;
    if ($p[0] === 'msg') return 'Mensaje del formulario · etiqueta “' . ($T[$clave]['es'] ?? $ult) . '”';
    return ($pref[$p[0]] ?? ucfirst($p[0])) . ' · ' . $cual;
}
