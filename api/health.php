<?php
/**
 * SEO Meta Generator Pro — Health Check API Endpoint
 *
 * Returns system health status including PHP version, disk space,
 * writable directories, and cache status.
 *
 * Endpoint:
 *   GET /api/health.php
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
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'GET required.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$basePath = dirname(__DIR__);
$checks   = [];
$allOk    = true;

// ── PHP Version ────────────────────────────────────────────────
$phpVersion       = PHP_VERSION;
$phpVersionOk     = version_compare($phpVersion, '8.0.0', '>=');
$checks['php_version'] = [
    'status'  => $phpVersionOk ? 'ok' : 'warn',
    'value'   => $phpVersion,
    'message' => $phpVersionOk
        ? 'PHP ' . $phpVersion . ' meets minimum requirement (>= 8.0.0)'
        : 'PHP ' . $phpVersion . ' is below minimum 8.0.0',
];
if (!$phpVersionOk) $allOk = false;

// ── PHP Extensions ─────────────────────────────────────────────
$requiredExtensions = ['json', 'mbstring', 'pcre', 'fileinfo'];
$optionalExtensions = ['curl', 'pdo', 'pdo_sqlite', 'dom', 'libxml'];
$extChecks = [];

foreach ($requiredExtensions as $ext) {
    $loaded = extension_loaded($ext);
    $extChecks[$ext] = [
        'loaded'  => $loaded,
        'required'=> true,
        'status'  => $loaded ? 'ok' : 'fail',
    ];
    if (!$loaded) $allOk = false;
}

foreach ($optionalExtensions as $ext) {
    $loaded = extension_loaded($ext);
    $extChecks[$ext] = [
        'loaded'  => $loaded,
        'required'=> false,
        'status'  => $loaded ? 'ok' : 'warn',
    ];
}

$checks['php_extensions'] = [
    'status'  => $allOk ? 'ok' : 'warn',
    'details' => $extChecks,
];

// ── Disk Space ─────────────────────────────────────────────────
$freeSpace   = @disk_free_space($basePath);
$totalSpace  = @disk_total_space($basePath);
$usedSpace   = $totalSpace - $freeSpace;
$usedPercent = $totalSpace > 0 ? round(($usedSpace / $totalSpace) * 100, 1) : 0;

$diskOk = $freeSpace !== false && $freeSpace > 100 * 1024 * 1024; // 100 MB minimum
$diskStatus = $diskOk ? 'ok' : 'fail';
if (!$diskOk) $allOk = false;

$checks['disk_space'] = [
    'status'          => $diskStatus,
    'free_bytes'      => $freeSpace,
    'free_human'      => $freeSpace !== false ? formatBytes($freeSpace) : 'unknown',
    'total_bytes'     => $totalSpace,
    'total_human'     => $totalSpace !== false ? formatBytes($totalSpace) : 'unknown',
    'used_percent'    => $usedPercent,
    'message'         => $diskOk
        ? formatBytes($freeSpace) . ' free (' . $usedPercent . '% used)'
        : 'Low disk space: ' . ($freeSpace !== false ? formatBytes($freeSpace) : 'unknown') . ' remaining',
];

// ── Writable Directories ───────────────────────────────────────
$dirsToCheck = [
    'data'          => $basePath . '/data',
    'data/cache'    => $basePath . '/data/cache',
    'data/sitemaps' => $basePath . '/data/sitemaps',
    'data/templates'=> $basePath . '/data/templates',
    'api'           => $basePath . '/api',
    'includes'      => $basePath . '/includes',
    'modules'       => $basePath . '/modules',
    'src'           => $basePath . '/src',
    'templates'     => $basePath . '/templates',
    'assets'        => $basePath . '/assets',
];

$dirChecks = [];
foreach ($dirsToCheck as $label => $dirPath) {
    $exists = is_dir($dirPath);
    $writable = $exists && is_writable($dirPath);
    $status = $writable ? 'ok' : ($exists ? 'warn' : 'warn');

    $dirChecks[$label] = [
        'path'     => $dirPath,
        'exists'   => $exists,
        'writable' => $writable,
        'status'   => $status,
    ];

    // Only data dirs are critical for writing
    if (str_starts_with($label, 'data') && !$writable) {
        // Don't fail for missing dirs — they can be created
        if ($exists && !$writable) {
            $allOk = false;
        }
    }
}

$checks['directories'] = [
    'status'  => $allOk ? 'ok' : 'warn',
    'details' => $dirChecks,
];

// ── Cache Status ───────────────────────────────────────────────
$cacheDir = $basePath . '/data/cache';
$cache    = new FileCache($cacheDir);
try {
    $cacheStats = $cache->getStats();
    $cacheSize  = 0;
    foreach (glob($cacheDir . '/*.json') as $f) {
        $cacheSize += filesize($f);
    }

    $checks['cache'] = [
        'status'         => 'ok',
        'total_entries'  => $cacheStats['total_entries'],
        'active_entries'=> $cacheStats['active_entries'],
        'expired_entries'=> $cacheStats['expired_entries'],
        'size_bytes'     => $cacheSize,
        'size_human'     => formatBytes($cacheSize),
        'cache_dir'      => $cacheDir,
        'default_ttl'    => $cacheStats['default_ttl'],
    ];
} catch (\Exception $e) {
    $checks['cache'] = [
        'status'  => 'fail',
        'error'   => $e->getMessage(),
    ];
    $allOk = false;
}

// ── Memory Usage ───────────────────────────────────────────────
$memoryUsage    = memory_get_usage(true);
$memoryPeak     = memory_get_peak_usage(true);
$memoryLimit    = ini_get('memory_limit');
$memoryLimitBytes = parseMemoryLimit($memoryLimit);

$checks['memory'] = [
    'status'            => 'ok',
    'usage_bytes'       => $memoryUsage,
    'usage_human'       => formatBytes($memoryUsage),
    'peak_bytes'        => $memoryPeak,
    'peak_human'        => formatBytes($memoryPeak),
    'limit'             => $memoryLimit,
    'limit_bytes'       => $memoryLimitBytes,
];

// ── Server Info ────────────────────────────────────────────────
$checks['server'] = [
    'status'         => 'ok',
    'software'       => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'https'          => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'timezone'       => date_default_timezone_get(),
    'current_time'   => date('Y-m-d H:i:s T'),
];

// ── Application Version ────────────────────────────────────────
$appVersion = '3.2.0';
$appName    = 'SEO Meta Generator Pro';

// ── Response ───────────────────────────────────────────────────
http_response_code($allOk ? 200 : 503);
echo json_encode([
    'success'   => $allOk,
    'status'    => $allOk ? 'healthy' : 'degraded',
    'app'       => [
        'name'    => $appName,
        'version' => $appVersion,
    ],
    'checks'    => $checks,
    'timestamp' => date('Y-m-d H:i:s T'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// ── Helper Functions ───────────────────────────────────────────

function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow   = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function parseMemoryLimit(string $limit): ?int
{
    if ($limit === '-1') {
        return null; // No limit
    }
    $limit = trim($limit);
    $last  = strtolower($limit[strlen($limit) - 1]);
    $value = (int) $limit;

    switch ($last) {
        case 'g': $value *= 1024;
        case 'm': $value *= 1024;
        case 'k': $value *= 1024;
    }

    return $value;
}
