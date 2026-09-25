<?php
// Señales del navegador para el registro de visitas (las envía assets/js/sitio.js con sendBeacon).
declare(strict_types=1);
require __DIR__ . '/includes/visitas.php';
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        registrar_senal((string) ($_POST['t'] ?? ''), (string) ($_POST['e'] ?? ''), mb_substr((string) ($_POST['d'] ?? ''), 0, 255));
    } catch (Throwable $e) {
        error_log('[visitas] señal: ' . $e->getMessage());
    }
}
http_response_code(204);
