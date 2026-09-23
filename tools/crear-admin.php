<?php
/**
 * Crea un usuario del panel o le asigna una contraseña nueva (sirve para recuperar el acceso).
 *
 *   En el servidor (Docker):
 *     docker compose -f docker-compose.prod.yml exec web php tools/crear-admin.php USUARIO "Tu nombre"
 *   En tu computadora:
 *     php tools/crear-admin.php USUARIO "Tu nombre"
 *
 * Genera una contraseña segura y la muestra UNA sola vez. Cámbiala después en "Mi cuenta".
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/bootstrap.php';

$usuario = mb_strtolower(trim($argv[1] ?? ''));
$nombre  = trim($argv[2] ?? '');
if (!preg_match('/^[a-z0-9._-]{3,40}$/', $usuario)) {
    fwrite(STDERR, "Uso: php tools/crear-admin.php USUARIO [\"Nombre\"]\n");
    fwrite(STDERR, "El usuario debe tener de 3 a 40 caracteres: letras minúsculas, números, punto, guion o guion bajo.\n");
    exit(1);
}

$alfabeto = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$clave = '';
for ($i = 0; $i < 20; $i++) $clave .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
$clave = implode('-', str_split($clave, 5));

$db = db();
$st = $db->prepare('SELECT id FROM administradores WHERE usuario = ?');
$st->execute([$usuario]);
$existe = $st->fetchColumn();

if ($existe) {
    $db->prepare('UPDATE administradores SET hash = ?, activo = 1' . ($nombre !== '' ? ', nombre = ?' : '') . ' WHERE id = ?')
       ->execute(array_merge([password_hash($clave, PASSWORD_DEFAULT)], $nombre !== '' ? [$nombre] : [], [$existe]));
    $db->prepare('DELETE FROM intentos_login WHERE usuario = ?')->execute([$usuario]);
    echo "Se asignó una contraseña nueva a '$usuario'.\n";
} else {
    $db->prepare('INSERT INTO administradores (usuario, nombre, hash) VALUES (?, ?, ?)')
       ->execute([$usuario, $nombre, password_hash($clave, PASSWORD_DEFAULT)]);
    echo "Usuario '$usuario' creado.\n";
}
$db->prepare("INSERT INTO bitacora (usuario, accion, detalle) VALUES ('consola', 'Cuenta', ?)")
   ->execute([($existe ? 'Nueva contraseña para ' : 'Alta de ') . $usuario]);

echo "\n  Entra en:    /admin/\n  Usuario:     $usuario\n  Contraseña:  $clave\n\n";
echo "Guárdala ahora: no se vuelve a mostrar. Puedes cambiarla en 'Mi cuenta'.\n";
