<?php
/**
 * SEO Meta Generator Pro — Export API Endpoint
 *
 * Exports analysis history or current session data as JSON or CSV.
 *
 * Endpoints:
 *   GET /api/export.php?format=json[&url=<encoded_url>]
 *   GET /api/export.php?format=csv[&url=<encoded_url>]
 *   POST /api/export.php  with JSON body { "format": "json", "data": {...} }
 *
 * @package SEO Meta Generator Pro
 * @version 3.1.0
 */

require_once __DIR__ . '/../src/Analyzer.php';
require_once __DIR__ . '/../src/Generator.php';
require_once __DIR__ . '/../src/Exporter.php';
require_once __DIR__ . '/../modules/History/History.php';

use SEOMetaGen\Exporter;
use SEOMetaGen\Modules\History\History;

// ── Security Headers ──────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-RateLimit-Limit: 100');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Input Sanitisation ────────────────────────────────────────
$format = strtolower(trim($_GET['format'] ?? $_POST['format'] ?? ''));
$format = in_array($format, ['json', 'csv'], true) ? $format : '';

if (!$format) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid or missing format. Use ?format=json or ?format=csv',
    ], JSON_PRETTY_PRINT);
    exit;
}

$exporter = new Exporter();
$history  = new History();

try {
    // ── Export from history (database) ────────────────────────────
    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        $entries = $history->getRecent(500);
        echo json_encode([
            'success'     => true,
            'exported_at' => date('Y-m-d H:i:s'),
            'total'       => count($entries),
            'data'        => $entries,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="seo-history-export-' . date('Y-m-d-His') . '.csv"');
        echo $history->exportCsv(500);
        exit;
    }
} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'Export failed: ' . $e->getMessage(),
    ], JSON_PRETTY_PRINT);
    exit;
}
