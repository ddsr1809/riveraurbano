<?php
// Chequeo de salud para Docker, el pipeline y monitoreo externo: /salud.php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
try {
    $n = (int) db()->query('SELECT COUNT(*) FROM textos')->fetchColumn();
    echo json_encode(['ok' => $n > 0, 'textos' => $n]);
    if ($n === 0) http_response_code(503);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'bd']);
}
