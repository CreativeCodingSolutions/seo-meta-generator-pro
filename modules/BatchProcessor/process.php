<?php
/**
 * SEO Meta Generator Pro — BatchProcessor Entry Point
 * 
 * Supports:
 *   Web: POST action=batch with items[] in body
 *   CLI: php process.php [--file=urls.txt] [--format=json|csv|html]
 * 
 * Usage examples:
 *   CLI:  php process.php --file=urls.txt --format=json
 *   Web:  POST process.php with action=batch & items[][url]=...
 */

require_once __DIR__ . '/BatchProcessor.php';

use SEOMetaGen\Modules\BatchProcessor\BatchProcessor;

$processor = new BatchProcessor();

// ── CLI Mode ───────────────────────────────────────────────
if (PHP_SAPI === 'cli') {
    $options = getopt('', ['file:', 'format:', 'help']);
    $format = $options['format'] ?? 'json';

    if (isset($options['help']) || $argc < 2) {
        echo <<<HELP
SEO Meta Generator Pro — BatchProcessor CLI
════════════════════════════════════════════

Usage:
  php process.php --file=<path> [--format=json|csv|html]

Options:
  --file=<path>    Path to input file (pipe-separated or CSV)
  --format=<fmt>   Output format: json (default), csv, html
  --help           Show this help

Input formats:
  Pipe-separated (one URL per line):
    https://example.com|Title|Description|kw1,kw2|https://example.com/img.jpg
  
  CSV (auto-detected columns: url, title, description, keywords, image):

  php process.php --file=urls.txt --format=csv

HELP;
        exit(0);
    }

    $filepath = $options['file'] ?? '';
    if (!$filepath || !file_exists($filepath)) {
        fwrite(STDERR, "Error: File not found: {$filepath}\n");
        exit(1);
    }

    // Detect format from extension
    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    if ($ext === 'csv' || $ext === 'tsv') {
        $items = $processor->parseCsvFile($filepath);
    } else {
        $text = file_get_contents($filepath);
        $items = $processor->parseInput($text);
    }

    if (empty($items)) {
        fwrite(STDERR, "Error: No valid URLs found in file.\n");
        exit(1);
    }

    echo "Processing " . count($items) . " URLs...\n";

    $batchResult = $processor->process($items);

    echo $processor->export($batchResult, $format);
    echo "\nDone.\n";
    exit(0);
}

// ── Web Mode ───────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'batch':
        // Support both items array and text input
        if (!empty($_POST['text'])) {
            $items = $processor->parseInput($_POST['text']);
        } elseif (!empty($_POST['items']) && is_array($_POST['items'])) {
            $items = $_POST['items'];
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No items provided. Send text or items[]']);
            exit;
        }

        if (empty($items)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No valid items found.']);
            exit;
        }

        $batchResult = $processor->process($items);

        // If export requested
        $format = $_POST['format'] ?? 'json';
        if ($format !== 'json') {
            header('Content-Type: text/' . ($format === 'csv' ? 'csv' : 'html') . '; charset=utf-8');
            header('Content-Disposition: attachment; filename="batch-report.' . $format . '"');
            echo $processor->export($batchResult, $format);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $batchResult], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;

    case 'stats':
        $stats = $processor->getStats();
        echo json_encode(['success' => true, 'data' => $stats], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode([
            'success' => false,
            'error' => 'Unknown action. Available: batch, stats',
            'usage' => [
                'batch' => 'POST action=batch&text=URL|Title|Description|Keywords|Image (one per line)',
                'stats' => 'GET action=stats',
            ],
        ], JSON_PRETTY_PRINT);
}
