<?php
/**
 * SEO Meta Generator Pro — History Module Entry Point
 * 
 * Web API + CLI interface for the history module.
 * 
 * Web endpoints (GET/POST):
 *   action=list     — List recent entries (HTML or JSON)
 *   action=search&q= — Search by URL/title
 *   action=stats    — Get statistics
 *   action=delete&id= — Delete entry
 *   action=clear    — Clear all history (POST only)
 *   action=export&format=csv — Export as CSV
 * 
 * CLI:
 *   php history.php list [--limit=20] [--json]
 *   php history.php search "query"
 *   php history.php stats
 *   php history.php clear
 *   php history.php export [--format=csv] [--limit=500]
 */

require_once __DIR__ . '/History.php';

use SEOMetaGen\Modules\History\History;

$history = new History();

// ── CLI Mode ───────────────────────────────────────────────
if (PHP_SAPI === 'cli') {
    $command = $argv[1] ?? 'help';
    $format = 'text';

    switch ($command) {
        case 'list':
            $limit = 20;
            foreach ($argv as $arg) {
                if (str_starts_with($arg, '--limit=')) $limit = (int) substr($arg, 8);
                if ($arg === '--json') $format = 'json';
            }
            $entries = $history->getRecent($limit);

            if ($format === 'json') {
                echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                printf("%-5s %-25s %-30s %-8s %-6s %s\n", 'ID', 'Date', 'URL', 'Score', 'Grade', 'Title');
                echo str_repeat('—', 100) . "\n";
                foreach ($entries as $e) {
                    printf(
                        "%-5d %-25s %-30s %-8s %-6s %s\n",
                        $e['id'] ?? 0,
                        $e['created_at'] ?? '',
                        substr($e['url'] ?? '', 0, 30),
                        ($e['overall_score'] ?? 0) . '%',
                        $e['grade'] ?? '-',
                        substr($e['title'] ?? '', 0, 40)
                    );
                }
                echo "\nTotal: " . $history->count() . " entries\n";
            }
            break;

        case 'search':
            $query = $argv[2] ?? '';
            if (!$query) {
                fwrite(STDERR, "Usage: php history.php search \"query\"\n");
                exit(1);
            }
            $entries = $history->search($query);
            echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            break;

        case 'stats':
            $stats = $history->getStats();
            echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            break;

        case 'delete':
            $id = (int)($argv[2] ?? 0);
            if (!$id) {
                fwrite(STDERR, "Usage: php history.php delete <id>\n");
                exit(1);
            }
            echo $history->delete($id) ? "Deleted entry {$id}\n" : "Failed to delete entry {$id}\n";
            break;

        case 'clear':
            echo $history->clear() ? "History cleared.\n" : "Failed to clear history.\n";
            break;

        case 'export':
            $fmt = 'csv';
            foreach ($argv as $arg) {
                if (str_starts_with($arg, '--format=')) $fmt = substr($arg, 9);
            }
            header('Content-Type: text/csv; charset=utf-8');
            echo $history->exportCsv(500) . "\n";
            break;

        case 'help':
        default:
            echo <<<HELP
SEO Meta Generator Pro — History CLI
═════════════════════════════════════

Commands:
  list [--limit=20] [--json]    List recent entries
  search "query"                Search by URL/title
  stats                         Show statistics
  delete <id>                   Delete an entry
  clear                         Clear all history
  export [--format=csv]         Export history
  help                          Show this help

HELP;
    }
    exit(0);
}

// ── Web Mode ───────────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $format = $_GET['format'] ?? $_POST['format'] ?? 'html';
        $limit = (int)($_GET['limit'] ?? 50);

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'data' => $history->getRecent($limit),
                'total' => $history->count(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo $history->renderTable($limit);
        }
        break;

    case 'search':
        $q = $_GET['q'] ?? $_POST['q'] ?? '';
        header('Content-Type: application/json; charset=utf-8');
        if (!$q) {
            echo json_encode(['success' => false, 'error' => 'Query parameter "q" required']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'data' => $history->search($q),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;

    case 'stats':
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'data' => $history->getStats(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        header('Content-Type: application/json; charset=utf-8');
        if (!$id || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'POST with id required']);
            exit;
        }
        echo json_encode(['success' => $history->delete($id)]);
        break;

    case 'clear':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $history->clear()]);
        break;

    case 'export':
        $format = $_GET['format'] ?? 'csv';
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="seo-history.csv"');
            echo $history->exportCsv();
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'data' => $history->getRecent(500),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        break;

    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Unknown action',
            'available' => ['list', 'search', 'stats', 'delete', 'clear', 'export'],
        ], JSON_PRETTY_PRINT);
}
