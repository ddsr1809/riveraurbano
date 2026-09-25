<?php
declare(strict_types=1);

/**
 * riveraurbano.com — Conexión a MariaDB, detección de idioma y carga de textos.
 */

const RAIZ = __DIR__ . '/..';
const ZONA_HORARIA = 'America/Tijuana';   // Mexicali
date_default_timezone_set(ZONA_HORARIA);

/* ------------------------------------------------------------------
 * Configuración: config/config.php y, si existen, variables de entorno
 * (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS). Las variables de
 * entorno ganan, así Docker o el hosting no necesitan editar archivos.
 * ------------------------------------------------------------------ */
function config(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $archivo = RAIZ . '/config/config.php';
    $cfg = is_file($archivo) ? require $archivo : [];
    $cfg += ['db_host' => 'localhost', 'db_port' => 3306, 'db_name' => 'rivera',
             'db_user' => 'riverauser', 'db_pass' => '', 'debug' => false];

    foreach (['db_host' => 'DB_HOST', 'db_port' => 'DB_PORT', 'db_name' => 'DB_NAME',
              'db_user' => 'DB_USER', 'db_pass' => 'DB_PASS'] as $k => $env) {
        $v = getenv($env);
        if ($v !== false && $v !== '') $cfg[$k] = $v;
    }
    return $cfg;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = config();
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                   $c['db_host'], (int) $c['db_port'], $c['db_name']);
    $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ]);
    // Misma hora que PHP (Mexicali), sin depender de las tablas de zonas de MariaDB
    $pdo->exec("SET time_zone = '" . (new DateTime('now'))->format('P') . "'");
    return $pdo;
}

/* ------------------------------------------------------------------
 * Red: HTTPS e IP real (detrás de Caddy)
 * ------------------------------------------------------------------ */

function es_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** IP real del visitante. Solo confía en X-Forwarded-For si la conexión viene de la red interna (Caddy). */
function ip_cliente(): string
{
    $remota = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $publica = filter_var($remota, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if ($publica === false && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $primera = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($primera, FILTER_VALIDATE_IP)) return $primera;
    }
    return $remota;
}

/* ------------------------------------------------------------------
 * Caché de respaldo: cada carga correcta se guarda en /cache. Si la
 * base de datos se cae, el sitio sigue funcionando con la última copia.
 * ------------------------------------------------------------------ */
function cache_leer(string $nombre): ?array
{
    $f = RAIZ . "/cache/$nombre.json";
    if (!is_file($f)) return null;
    $d = json_decode((string) file_get_contents($f), true);
    return is_array($d) ? $d : null;
}

function cache_guardar(string $nombre, array $datos): void
{
    $dir = RAIZ . '/cache';
    if (!is_dir($dir) || !is_writable($dir)) return;
    @file_put_contents("$dir/$nombre.json.tmp", json_encode($datos, JSON_UNESCAPED_UNICODE));
    @rename("$dir/$nombre.json.tmp", "$dir/$nombre.json");
}

/** Consulta con respaldo en caché. */
function cargar(string $nombre, callable $consulta): array
{
    try {
        $datos = $consulta(db());
        cache_guardar($nombre, $datos);
        return $datos;
    } catch (Throwable $e) {
        error_log("[riveraurbano] BD no disponible ($nombre): " . $e->getMessage());
        $respaldo = cache_leer($nombre);
        if ($respaldo !== null) return $respaldo;
        throw $e;
    }
}

/* ------------------------------------------------------------------
 * Idiomas
 * ------------------------------------------------------------------ */
function idiomas(): array
{
    static $lista = null;
    return $lista ??= cargar('idiomas', fn(PDO $db) =>
        $db->query('SELECT codigo, nombre, locale, por_defecto FROM idiomas
                    WHERE activo = 1 ORDER BY orden, codigo')->fetchAll());
}

/**
 * Orden de prioridad: /en/ en la URL → ?lang=en → cookie → navegador → por defecto.
 * Devuelve [codigo, elegido_explicitamente].
 */
function detectar_idioma(): array
{
    $validos = array_column(idiomas(), 'codigo');
    $defecto = 'es';
    foreach (idiomas() as $i) if ($i['por_defecto']) $defecto = $i['codigo'];

    $ruta = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $seg  = strtolower(explode('/', $ruta)[0] ?? '');
    if (in_array($seg, $validos, true)) return [$seg, true];

    $param = strtolower((string) ($_GET['lang'] ?? ''));
    if (in_array($param, $validos, true)) return [$param, true];

    $cookie = (string) ($_COOKIE['idioma'] ?? '');
    if (in_array($cookie, $validos, true)) return [$cookie, false];

    foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $parte) {
        $cod = strtolower(substr(trim($parte), 0, 2));
        if (in_array($cod, $validos, true)) return [$cod, false];
    }
    return [$defecto, false];
}

/* ------------------------------------------------------------------
 * Textos y configuración
 * ------------------------------------------------------------------ */
$GLOBALS['TEXTOS'] = [];
$GLOBALS['TEXTOS_RESPALDO'] = [];

function cargar_textos(string $idioma): array
{
    return cargar("textos-$idioma", function (PDO $db) use ($idioma) {
        $st = $db->prepare('SELECT clave, valor FROM textos WHERE idioma = ?');
        $st->execute([$idioma]);
        return $st->fetchAll(PDO::FETCH_KEY_PAIR);
    });
}

function cargar_config(): array
{
    return cargar('configuracion', fn(PDO $db) =>
        $db->query('SELECT clave, valor FROM configuracion')->fetchAll(PDO::FETCH_KEY_PAIR));
}

/**
 * Texto traducido (sin escapar). Si falta en el idioma actual usa el
 * idioma por defecto; si tampoco existe, devuelve la clave para que se note.
 */
function t(string $clave, array $vars = []): string
{
    $txt = $GLOBALS['TEXTOS'][$clave] ?? $GLOBALS['TEXTOS_RESPALDO'][$clave] ?? $clave;
    foreach ($vars as $k => $v) $txt = str_replace('{' . $k . '}', (string) $v, $txt);
    return $txt;
}

/** Texto traducido y escapado para HTML. */
function e(string $clave, array $vars = []): string
{
    return htmlspecialchars(t($clave, $vars), ENT_QUOTES, 'UTF-8');
}

/** Escapa cualquier valor. */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function cfg(string $clave, string $defecto = ''): string
{
    return (string) ($GLOBALS['CONFIG'][$clave] ?? $defecto);
}

function link_whats(string $mensaje): string
{
    return 'https://wa.me/' . rawurlencode(cfg('whatsapp')) . '?text=' . rawurlencode($mensaje);
}
