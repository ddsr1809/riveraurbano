<?php
/**
 * Agrega país, ciudad y proveedor a visitas guardadas sin ubicación.
 *   docker compose -f docker-compose.prod.yml exec web php tools/completar-ubicacion.php
 * (Hace lo mismo que el botón "Completar ubicación" del panel.)
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/visitas.php';
$g = estado_geoip();
echo 'Base ciudad:    ' . ($g['ciudad'] ? "{$g['ciudad']['archivo']} ({$g['ciudad']['version']})" : 'NO ENCONTRADA') . "\n";
echo 'Base proveedor: ' . ($g['proveedor'] ? "{$g['proveedor']['archivo']} ({$g['proveedor']['version']})" : 'NO ENCONTRADA') . "\n";
if (!$g['ciudad'] && !$g['proveedor']) { echo "Revisa: docker compose -f docker-compose.prod.yml logs geoip\n"; exit(1); }
$r = completar_ubicacion(100000);
echo "Revisadas: {$r['revisadas']} · completadas: {$r['completadas']} · pasaron a sospechoso: {$r['sospechosas']}\n";
