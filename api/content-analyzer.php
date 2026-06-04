<?php
/**
 * SEO Meta Generator Pro — Content Analyzer API
 *
 * Analyzes HTML content (from URL or direct input) and returns:
 *   - word_count
 *   - readability_score
 *   - keyword_density
 *   - heading_structure
 *   - image_count (with/without alt)
 *   - internal_links / external_links
 *   - meta_description_length
 *   - recommendations
 *
 * Endpoint:
 *   POST /api/content-analyzer.php
 *   Body: {"url": "https://example.com"} OR {"html": "<html>..."}
 *
 * @package SEO Meta Generator Pro
 * @version 3.3.0
 */

require_once __DIR__ . '/../includes/functions.php';

use SEOMetaGen\Analyzer;

// ── Headers & CORS ─────────────────────────────────────────────
applySecurityHeaders('POST, OPTIONS');
handleOptionsRequest();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('POST required. Send JSON body with "url" or "html".', 405);
}

// ── Input ──────────────────────────────────────────────────────
$input = getJsonInput();
$url  = trim($input['url'] ?? '');
$html = trim($input['html'] ?? '');

if ($url === '' && $html === '') {
    sendJsonError('Provide either "url" or "html" in the request body.', 400);
}

// ── Fetch / Validate HTML ──────────────────────────────────────
$fetchedUrl = $url;
$rawHtml    = $html;

if ($url !== '') {
    $filtered = filter_var($url, FILTER_VALIDATE_URL);
    if (!$filtered) {
        sendJsonError('Invalid URL format.', 400);
    }

    // Try to fetch the URL
    $rawHtml = fetchUrlContent($filtered);
    if ($rawHtml === null) {
        sendJsonError('Could not fetch URL: ' . $filtered . '. Check that the URL is accessible.', 422);
    }
    $fetchedUrl = $filtered;
}

if ($rawHtml === '' || mb_strlen($rawHtml) < 50) {
    sendJsonError('HTML content is too short or empty for analysis.', 400);
}

// ── Analyze Content ────────────────────────────────────────────
try {
    $dom = new DOMDocument();
    @$dom->loadHTML($rawHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // ── Word Count ─────────────────────────────────────────────
    $body = $dom->getElementsByTagName('body')->item(0);
    $textContent = $body ? $body->textContent : $dom->textContent;
    $textContent = preg_replace('/\s+/', ' ', trim($textContent));
    $words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', '', $textContent), -1, PREG_SPLIT_NO_EMPTY);
    $wordCount = count($words);

    // ── Readability Score ──────────────────────────────────────
    $readabilityScore = calculateReadabilityScore($textContent);

    // ── Keyword Density ────────────────────────────────────────
    $keywordDensity = extractKeywordDensity($textContent, 15);

    // ── Heading Structure ──────────────────────────────────────
    $headingStructure = extractHeadingStructure($rawHtml);

    // ── Image Analysis ─────────────────────────────────────────
    $imageAnalysis = analyzeImages($rawHtml);

    // ── Link Analysis ──────────────────────────────────────────
    $linkAnalysis = analyzeLinks($rawHtml, $fetchedUrl);

    // ── Meta Description ───────────────────────────────────────
    $metaDescription = getMetaDescriptionInfo($rawHtml);

    // ── Compile Analysis ───────────────────────────────────────
    $analysis = [
        'word_count'              => $wordCount,
        'readability_score'       => $readabilityScore,
        'readability_label'       => getReadabilityLabel($readabilityScore),
        'keyword_density'         => $keywordDensity,
        'heading_structure'       => $headingStructure,
        'image_analysis'          => $imageAnalysis,
        'link_analysis'           => $linkAnalysis,
        'meta_description'        => $metaDescription,
    ];

    // ── Recommendations ────────────────────────────────────────
    $recommendations = generateContentRecommendations($analysis);

    // ── Response ───────────────────────────────────────────────
    sendJsonResponse([
        'source'          => $fetchedUrl ? 'url' : 'html_input',
        'url'             => $fetchedUrl,
        'analyzed_at'     => date('Y-m-d H:i:s T'),
        'word_count'      => $wordCount,
        'readability_score' => $readabilityScore,
        'readability_label' => getReadabilityLabel($readabilityScore),
        'keyword_density' => $keywordDensity,
        'heading_structure' => $headingStructure,
        'image_count'     => $imageAnalysis['total'],
        'images_with_alt' => $imageAnalysis['with_alt'],
        'images_without_alt' => $imageAnalysis['without_alt'],
        'internal_links'  => $linkAnalysis['internal_count'],
        'external_links'  => $linkAnalysis['external_count'],
        'total_links'     => $linkAnalysis['total'],
        'meta_description_length' => $metaDescription['length'],
        'meta_description_optimal' => $metaDescription['optimal'],
        'recommendations' => $recommendations,
        'full_analysis'   => $analysis,
    ], 200);

} catch (\Exception $e) {
    sendJsonError('Analysis failed: ' . $e->getMessage(), 500);
}

// ── Helper: Fetch URL content ──────────────────────────────────

function fetchUrlContent(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'SEO-Meta-Generator-Pro/3.3',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_MAXREDIRS      => 5,
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 400 && $html) {
            return $html;
        }
        return null;
    }

    // Fallback: file_get_contents
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header'  => "User-Agent: SEO-Meta-Generator-Pro/3.3\r\n",
        ],
    ]);
    $html = @file_get_contents($url, false, $context);
    return $html !== false ? $html : null;
}

// ── Helper: Readability Label ──────────────────────────────────

function getReadabilityLabel(int $score): string
{
    if ($score >= 80) return 'Sehr leicht (einfach)';
    if ($score >= 60) return 'Leicht';
    if ($score >= 40) return 'Mittel';
    if ($score >= 20) return 'Schwer';
    return 'Sehr schwer (akademisch)';
}
