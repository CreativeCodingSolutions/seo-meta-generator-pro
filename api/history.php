<?php
/**
 * SEO Meta Generator Pro — History API Endpoint
 *
 * Thin REST wrapper around the History module.
 *
 * Endpoints:
 *   GET  /api/history.php?action=list&format=json&limit=50
 *   GET  /api/history.php?action=search&q=<query>
 *   GET  /api/history.php?action=stats
 *   POST /api/history.php  { "action": "delete", "id": 5 }
 *   POST /api/history.php  { "action": "clear" }
 *
 * @package SEO Meta Generator Pro
 * @version 3.1.0
 */

require_once __DIR__ . '/../modules/History/History.php';

use SEOMetaGen\Modules\History\History;

// ── Security Headers ──────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$history = new History();

// ── Input sanitisation ────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = trim(sanitizeAction($input['action'] ?? ($_GET['action'] ?? '')));
$format = strtolower(trim(sanitizeAction($input['format'] ?? ($_GET['format'] ?? 'json'))));

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($action) {
        case 'list': {
            $limit  = min(500, max(1, (int)($_GET['limit'] ?? 50)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));
            $entries = $history->getRecent($limit, $offset);
            echo json_encode([
                'success' => true,
                'total'   => $history->count(),
                'limit'   => $limit,
                'offset'  => $offset,
                'data'    => $entries,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
        }

        case 'search': {
            $q = trim($_GET['q'] ?? $input['q'] ?? '');
            if (!$q) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Parameter "q" is required']);
                exit;
            }
            $q = substr($q, 0, 200); // limit query length
            $entries = $history->search($q, 25);
            echo json_encode([
                'success' => true,
                'query'   => $q,
                'data'    => $entries,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
        }

        case 'stats': {
            $stats = $history->getStats();
            echo json_encode([
                'success' => true,
                'data'    => $stats,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
        }

        case 'delete': {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'POST required']);
                exit;
            }
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Valid id required']);
                exit;
            }
            echo json_encode(['success' => $history->delete($id)]);
            break;
        }

        case 'clear': {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'POST required']);
                exit;
            }
            echo json_encode(['success' => $history->clear()]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode([
                'success'   => false,
                'error'     => 'Unknown action',
                'available' => ['list', 'search', 'stats', 'delete', 'clear'],
            ], JSON_PRETTY_PRINT);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Internal error: ' . $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}

// ── Helpers ───────────────────────────────────────────────────

function sanitizeAction(string $action): string
{
    return preg_replace('/[^a-zA-Z_-]/', '', $action);
}
