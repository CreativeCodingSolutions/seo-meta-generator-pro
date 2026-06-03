<?php
/**
 * SEO Meta Generator Pro — API Endpoint
 * 
 * Accepts POST JSON requests and returns JSON responses.
 * 
 * Endpoints (via action parameter):
 *   - analyze    : Analyze a single page
 *   - generate   : Generate meta tags
 *   - bulk       : Bulk analyze multiple pages
 *   - compare    : Compare multiple pages
 *   - keywords   : Analyze keyword density
 */

require_once __DIR__ . '/../src/Analyzer.php';
require_once __DIR__ . '/../src/Generator.php';
require_once __DIR__ . '/../src/Exporter.php';

use SEOMetaGen\Analyzer;
use SEOMetaGen\Generator;
use SEOMetaGen\Exporter;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    // Try POST data
    $input = $_POST;
}

$action = $input['action'] ?? '';
if (!$action && isset($_GET['action'])) {
    $action = $_GET['action'];
}

$analyzer = new Analyzer();
$generator = new Generator();
$exporter = new Exporter();

try {
    switch ($action) {
        case 'analyze':
            if (empty($input['url'])) {
                throw new \InvalidArgumentException('URL is required');
            }
            $result = $analyzer->fetchUrl($input['url']);
            echo json_encode(['success' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;

        case 'analyze_meta':
            $result = $analyzer->analyze(
                $input['url'] ?? '',
                $input['title'] ?? '',
                $input['description'] ?? '',
                $input['keywords'] ?? '',
                $input['image'] ?? ''
            );
            echo json_encode(['success' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;

        case 'generate':
            $data = $input['data'] ?? $input;
            $generated = $generator->generateAll($data);
            $html = $generator->renderHtml($generated);
            $response = ['success' => true, 'data' => $generated, 'html' => $html];
            if (!empty($input['full_html'])) {
                $response['full_html'] = $generator->renderFullHtml($generated, $data['locale'] ?? 'de');
            }
            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;

        case 'bulk':
            $items = $input['items'] ?? [];
            if (empty($items)) {
                throw new \InvalidArgumentException('Items array is required');
            }
            $result = $analyzer->bulkAnalyze($items);
            echo json_encode(['success' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;

        case 'compare':
            $urlsData = $input['competitors'] ?? [];
            if (count($urlsData) < 2) {
                throw new \InvalidArgumentException('At least 2 competitors required');
            }
            $result = $analyzer->compare($urlsData);
            echo json_encode(['success' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;

        case 'keywords':
            if (empty($input['text'])) {
                throw new \InvalidArgumentException('Text is required');
            }
            $limit = (int)($input['limit'] ?? 20);
            $keywords = $analyzer->extractKeywords($input['text'], $limit);
            echo json_encode(['success' => true, 'data' => $keywords], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;

        case 'export_html':
            $result = $analyzer->analyze(
                $input['url'] ?? '', $input['title'] ?? '',
                $input['description'] ?? '', $input['keywords'] ?? '', $input['image'] ?? ''
            );
            $html = $exporter->exportHtml($result);
            echo json_encode(['success' => true, 'html' => $html]);
            break;

        case 'export_json':
            $result = $analyzer->analyze(
                $input['url'] ?? '', $input['title'] ?? '',
                $input['description'] ?? '', $input['keywords'] ?? '', $input['image'] ?? ''
            );
            echo $exporter->exportJson($result);
            break;

        case 'version':
            echo json_encode([
                'success' => true,
                'version' => '2.0.0',
                'name' => 'SEO Meta Generator Pro',
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Unknown action. Available: analyze, analyze_meta, generate, bulk, compare, keywords, export_html, export_json, version',
            ], JSON_PRETTY_PRINT);
    }
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}
