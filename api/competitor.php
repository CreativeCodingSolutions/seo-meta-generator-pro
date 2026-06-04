<?php
/**
 * SEO Meta Generator Pro — Competitor Analysis API
 *
 * Analyzes multiple competitor URLs and returns a comparison matrix.
 *
 * Endpoints:
 *   POST /api/competitor.php  { "urls": ["https://a.com", "https://b.com", "https://c.com"] }
 *   GET  /api/competitor.php?format=summary (after POST session)
 *
 * @package SEO Meta Generator Pro
 * @version 3.1.0
 */

require_once __DIR__ . '/../src/Analyzer.php';
require_once __DIR__ . '/../src/Generator.php';

use SEOMetaGen\Analyzer;
use SEOMetaGen\Generator;

// Security Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-RateLimit-Limit: 100');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'POST required. Send JSON body with "urls" array (2-5 URLs).',
    ], JSON_PRETTY_PRINT);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['urls']) || !is_array($input['urls'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Request body must contain a "urls" array with 2-5 URLs.',
    ], JSON_PRETTY_PRINT);
    exit;
}

$urls = array_slice(array_map('trim', $input['urls']), 0, 5);
if (count($urls) < 2) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'At least 2 URLs required for comparison.',
    ], JSON_PRETTY_PRINT);
    exit;
}

$analyzer = new Analyzer();
$generator = new Generator();
$results = [];
$errors = [];

try {
foreach ($urls as $i => $url) {
    // Validate URL
    $url = filter_var($url, FILTER_VALIDATE_URL);
    if (!$url) {
        $errors[] = "Invalid URL at index $i";
        continue;
    }

    try {
        $result = $analyzer->fetchUrl($url);
        $meta = $generator->generateAll($result);
        $results[] = [
            'url' => $url,
            'title' => $result['title'] ?? '',
            'description' => $result['description'] ?? '',
            'keywords' => $result['keywords'] ?? '',
            'score' => $result['overall_score'] ?? 0,
            'grade' => $result['grade'] ?? 'F',
            'word_count' => $result['word_count'] ?? 0,
            'has_og_tags' => $result['has_og_tags'] ?? false,
            'has_twitter_cards' => $result['has_twitter_cards'] ?? false,
            'has_canonical' => $result['has_canonical'] ?? false,
            'has_robots_meta' => $result['has_robots_meta'] ?? false,
            'mobile_friendly' => $result['mobile_friendly'] ?? null,
            'load_time_ms' => $result['load_time_ms'] ?? null,
            'suggestions_count' => count($result['suggestions'] ?? []),
            'top_suggestions' => array_slice($result['suggestions'] ?? [], 0, 3),
        ];
    } catch (\Exception $e) {
        $errors[] = "Failed to analyze $url: " . substr($e->getMessage(), 0, 100);
    }
}
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Analysis failed: ' . $e->getMessage(),
    ], JSON_PRETTY_PRINT);
    exit;
}

if (empty($results)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Could not analyze any of the provided URLs.',
        'details' => $errors,
    ], JSON_PRETTY_PRINT);
    exit;
}

// Build comparison matrix
$comparison = buildComparisonMatrix($results);

echo json_encode([
    'success' => true,
    'analyzed_at' => date('Y-m-d H:i:s'),
    'urls_analyzed' => count($results),
    'urls_failed' => count($errors),
    'errors' => $errors ?: null,
    'competitors' => $results,
    'comparison' => $comparison,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// ── Helper Functions ──────────────────────────────────────────

function buildComparisonMatrix(array $results): array
{
    if (count($results) < 2) return [];

    $scores = array_column($results, 'score');
    $maxScore = max($scores);
    $minScore = min($scores);
    $avgScore = (int) round(array_sum($scores) / count($scores));

    // Winner per category
    $winner = null;
    foreach ($results as $r) {
        if ($r['score'] === $maxScore) {
            $winner = $r['url'];
            break;
        }
    }

    // Feature checklist comparison
    $features = ['has_og_tags', 'has_twitter_cards', 'has_canonical', 'has_robots_meta'];
    $featureComparison = [];
    foreach ($features as $feature) {
        $featureComparison[$feature] = [];
        foreach ($results as $r) {
            $featureComparison[$feature][$r['url']] = $r[$feature] ?? false;
        }
    }

    // Strengths per competitor
    $strengths = [];
    foreach ($results as $r) {
        $s = [];
        if ($r['score'] >= 80) $s[] = 'High overall score';
        if ($r['has_og_tags']) $s[] = 'Open Graph tags';
        if ($r['has_twitter_cards']) $s[] = 'Twitter Cards';
        if ($r['has_canonical']) $s[] = 'Canonical URL';
        if ($r['word_count'] >= 500) $s[] = 'Good content length';
        if ($r['load_time_ms'] && $r['load_time_ms'] < 2000) $s[] = 'Fast loading';
        $strengths[$r['url']] = $s;
    }

    return [
        'score_range' => ['min' => $minScore, 'max' => $maxScore, 'avg' => $avgScore],
        'overall_winner' => $winner,
        'feature_comparison' => $featureComparison,
        'strengths' => $strengths,
        'recommendation' => generateRecommendation($results, $maxScore),
    ];
}

function generateRecommendation(array $results, int $maxScore): string
{
    if ($maxScore >= 85) {
        return 'Competitor SEO is strong. Focus on content differentiation and technical improvements.';
    } elseif ($maxScore >= 60) {
        return 'Moderate competition. Good opportunity to outrank with better meta tags and content.';
    } else {
        return 'Weak competitor SEO. Strong opportunity to dominate SERP with proper optimization.';
    }
}
