<?php
/**
 * SEO Meta Generator Pro — Sitemap Generator API Endpoint
 *
 * Generates XML sitemaps (sitemap.xml format) from a list of URLs.
 *
 * Endpoints:
 *   POST /api/sitemap.php  { "urls": ["https://a.com", ...], "priority": 0.8, "changefreq": "weekly" }
 *   GET  /api/sitemap.php?action=list
 *   GET  /api/sitemap.php?action=view&file=<filename>
 *
 * @package SEO Meta Generator Pro
 * @version 3.2.0
 */

require_once __DIR__ . '/../includes/cache.php';

use SEOMetaGen\Cache\FileCache;

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

// ── Configuration ─────────────────────────────────────────────
$sitemapDir = dirname(__DIR__) . '/data/sitemaps';
$cacheDir   = dirname(__DIR__) . '/data/cache';

if (!is_dir($sitemapDir)) {
    @mkdir($sitemapDir, 0755, true);
}

$cache  = new FileCache($cacheDir);
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: List or View ─────────────────────────────────────────
if ($method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $action = preg_replace('/[^a-zA-Z_-]/', '', $_GET['action'] ?? 'list');

    if ($action === 'list') {
        $sitemaps = [];
        foreach (glob($sitemapDir . '/*.xml') as $file) {
            $sitemaps[] = [
                'filename'   => basename($file),
                'url'        => 'data/sitemaps/' . basename($file),
                'size_bytes' => filesize($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }
        // Sort newest first
        usort($sitemaps, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        echo json_encode([
            'success'  => true,
            'total'    => count($sitemaps),
            'sitemaps' => $sitemaps,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'view') {
        $file = basename($_GET['file'] ?? '');
        $path = $sitemapDir . '/' . $file;
        if (!$file || !file_exists($path) || pathinfo($file, PATHINFO_EXTENSION) !== 'xml') {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Sitemap file not found']);
            exit;
        }
        header('Content-Type: application/xml; charset=utf-8');
        readfile($path);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action. Use ?action=list or ?action=view&file=<filename>']);
    exit;
}

// ── POST: Generate Sitemap ────────────────────────────────────
if ($method !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST to generate, GET to list/view.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Validate URLs
$urls = $input['urls'] ?? [];
if (!is_array($urls) || empty($urls)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Request body must contain a non-empty "urls" array.']);
    exit;
}

// Validate and sanitize URLs
$validUrls = [];
$invalidUrls = [];
foreach ($urls as $i => $url) {
    $url = trim($url);
    $filtered = filter_var($url, FILTER_VALIDATE_URL);
    if ($filtered && (str_starts_with($filtered, 'http://') || str_starts_with($filtered, 'https://'))) {
        $validUrls[] = $filtered;
    } else {
        $invalidUrls[] = ['index' => $i, 'url' => substr($url, 0, 200)];
    }
}

if (empty($validUrls)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'No valid URLs provided.',
        'invalid' => $invalidUrls,
    ], JSON_PRETTY_PRINT);
    exit;
}

// Optional per-URL overrides
$urlOverrides = [];
if (!empty($input['url_settings']) && is_array($input['url_settings'])) {
    foreach ($input['url_settings'] as $setting) {
        if (!empty($setting['url'])) {
            $urlOverrides[$setting['url']] = [
                'priority'   => isset($setting['priority']) ? (float)$setting['priority'] : null,
                'changefreq' => $setting['changefreq'] ?? null,
                'lastmod'    => $setting['lastmod'] ?? null,
            ];
        }
    }
}

// Default settings
$defaultPriority   = isset($input['priority']) ? (float)$input['priority'] : 0.8;
$defaultChangeFreq = $input['changefreq'] ?? 'weekly';

// Clamp priority
$defaultPriority = max(0.0, min(1.0, $defaultPriority));

// Valid changefreq values
$validFreqs = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
if (!in_array($defaultChangeFreq, $validFreqs, true)) {
    $defaultChangeFreq = 'weekly';
}

// ── Build XML Sitemap ────────────────────────────────---------
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;

$urlset = $dom->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
$dom->appendChild($urlset);

// Add image namespace if images provided
$hasImages = false;
foreach ($urlOverrides as $s) {
    if (!empty($s['image'])) {
        $hasImages = true;
        break;
    }
}
if ($hasImages) {
    $urlset->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
}

$now = date('Y-m-d');
$urlCount = 0;

foreach ($validUrls as $url) {
    $urlEl = $dom->createElement('url');
    $urlset->appendChild($urlEl);

    // loc
    $locEl = $dom->createElement('loc', htmlspecialchars($url, ENT_XML1, 'UTF-8'));
    $urlEl->appendChild($locEl);

    // lastmod
    $lastmod = $now;
    if (isset($urlOverrides[$url]['lastmod'])) {
        $lastmod = $urlOverrides[$url]['lastmod'];
    }
    $urlEl->appendChild($dom->createElement('lastmod', $lastmod));

    // changefreq
    $freq = $urlOverrides[$url]['changefreq'] ?? $defaultChangeFreq;
    if (!in_array($freq, $validFreqs, true)) {
        $freq = $defaultChangeFreq;
    }
    $urlEl->appendChild($dom->createElement('changefreq', $freq));

    // priority
    $priority = $urlOverrides[$url]['priority'] ?? $defaultPriority;
    $priority = max(0.0, min(1.0, $priority));
    $urlEl->appendChild($dom->createElement('priority', number_format($priority, 1)));

    $urlCount++;
}

// ── Save to file ──────────────────────────────────────────────
$timestamp = date('Ymd-His');
$filename  = "sitemap-{$timestamp}.xml";
$filepath  = $sitemapDir . '/' . $filename;

$xmlContent = $dom->saveXML();
if (file_put_contents($filepath, $xmlContent, LOCK_EX) === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Failed to write sitemap file. Check directory permissions.']);
    exit;
}

// Cache the sitemap metadata
$cacheKey = 'sitemap_' . md5($filename);
$cache->set($cacheKey, [
    'filename'     => $filename,
    'url_count'    => $urlCount,
    'generated_at' => date('Y-m-d H:i:s'),
], 86400);

// ── Enforce max sitemaps limit (keep last 20) ────────────────-
$maxSitemaps = 20;
$allSitemaps = glob($sitemapDir . '/*.xml');
if ($allSitemaps === false) {
    $allSitemaps = [];
}
if (count($allSitemaps) > $maxSitemaps) {
    // Sort by modification time ascending (oldest first)
    usort($allSitemaps, fn($a, $b) => filemtime($a) - filemtime($b));
    $toDelete = array_slice($allSitemaps, 0, count($allSitemaps) - $maxSitemaps);
    foreach ($toDelete as $old) {
        @unlink($old);
    }
}

// ── Response ──────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success'      => true,
    'generated_at' => date('Y-m-d H:i:s'),
    'sitemap_url'  => 'data/sitemaps/' . $filename,
    'filename'     => $filename,
    'stats'        => [
        'urls_submitted'  => count($urls),
        'urls_valid'      => count($validUrls),
        'urls_invalid'    => count($invalidUrls),
        'urls_in_sitemap' => $urlCount,
        'file_size_bytes' => filesize($filepath),
        'default_priority'   => $defaultPriority,
        'default_changefreq' => $defaultChangeFreq,
    ],
    'invalid_urls' => $invalidUrls ?: null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
