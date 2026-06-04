<?php
/**
 * SEO Meta Generator Pro — Shared Helper Functions
 *
 * Common utilities used across API endpoints:
 *   - sendJsonResponse()   : Send a JSON response with proper headers
 *   - sendJsonError()      : Send a JSON error response
 *   - getJsonInput()       : Read and decode JSON request body
 *   - applySecurityHeaders(): Set standard security + CORS headers
 *   - handleOptionsRequest(): Handle CORS preflight
 *
 * @package SEO Meta Generator Pro
 * @version 3.3.0
 */

/**
 * Apply standard security and CORS headers to every API response.
 *
 * @param string $allowedMethods Comma-separated allowed HTTP methods
 * @return void
 */
function applySecurityHeaders(string $allowedMethods = 'GET, POST, OPTIONS'): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: ' . $allowedMethods);
    header('X-RateLimit-Limit: 100');
}

/**
 * Handle CORS preflight (OPTIONS) requests.
 * Call at the top of every API endpoint.
 *
 * @return void  Exits with 204 if OPTIONS request
 */
function handleOptionsRequest(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Read JSON input from request body.
 * Falls back to $_POST if body is not valid JSON.
 *
 * @return array The parsed input
 */
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

/**
 * Send a JSON success response and exit.
 *
 * @param mixed  $data    Response data
 * @param int    $status  HTTP status code
 * @param string $message Optional message
 * @return void
 */
function sendJsonResponse($data, int $status = 200, string $message = ''): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => true, 'data' => $data];
    if ($message !== '') {
        $response['message'] = $message;
    }
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send a JSON error response and exit.
 *
 * @param string $error  Error message
 * @param int    $status HTTP status code
 * @return void
 */
function sendJsonError(string $error, int $status = 400): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => $error,
    ], JSON_PRETTY_PRINT);
    exit;
}

/**
 * Validate that a value is a non-empty string.
 *
 * @param mixed  $value
 * @param string $fieldName
 * @return string
 */
function requireString($value, string $fieldName): string
{
    if (!is_string($value) || trim($value) === '') {
        sendJsonError("{$fieldName} is required and must be a non-empty string.", 400);
    }
    return trim($value);
}

/**
 * Validate a URL string.
 *
 * @param string $url
 * @return string
 */
function requireUrl(string $url): string
{
    $filtered = filter_var(trim($url), FILTER_VALIDATE_URL);
    if (!$filtered) {
        sendJsonError('A valid URL is required.', 400);
    }
    return $filtered;
}

/**
 * Calculate Flesch-Kincaid readability score for German/English text.
 * Returns a 0-100 score where 100 = very easy to read.
 *
 * @param string $text
 * @return int
 */
function calculateReadabilityScore(string $text): int
{
    $text = trim($text);
    if ($text === '') {
        return 0;
    }

    $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $sentenceCount = max(1, count(array_filter(array_map('trim', $sentences))));

    $words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', '', $text), -1, PREG_SPLIT_NO_EMPTY);
    $wordCount = max(1, count($words));

    // Count syllables (approximation)
    $syllableCount = 0;
    foreach ($words as $word) {
        $syllableCount += max(1, estimateSyllables($word));
    }

    // Flesch Reading Ease (English formula, adapted)
    $score = 206.835 - (1.015 * ($wordCount / $sentenceCount)) - (84.6 * ($syllableCount / $wordCount));
    $score = max(0, min(100, (int) round($score)));

    return $score;
}

/**
 * Estimate syllable count for a single word.
 *
 * @param string $word
 * @return int
 */
function estimateSyllables(string $word): int
{
    $word = mb_strtolower(trim($word));
    if ($word === '') {
        return 0;
    }

    // Count vowel groups
    preg_match_all('/[aeiouyäöü]+/u', $word, $matches);
    $count = count($matches[0]);

    // Silent e at end
    if (preg_match('/e$/', $word) && $count > 1) {
        $count--;
    }

    return max(1, $count);
}

/**
 * Extract keyword density from text.
 *
 * @param string $text
 * @param int    $limit
 * @return array
 */
function extractKeywordDensity(string $text, int $limit = 15): array
{
    $text = mb_strtolower($text);
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

    $stopWords = [
        'aber', 'alle', 'allem', 'allen', 'aller', 'als', 'also', 'ander', 'andere', 'anderem',
        'anderen', 'anderer', 'anderes', 'anderm', 'andern', 'anders', 'auch', 'auf', 'aus',
        'bei', 'bin', 'bis', 'bist', 'da', 'damit', 'dann', 'der', 'den', 'des', 'dem', 'die',
        'das', 'dass', 'daß', 'dein', 'deine', 'deinem', 'deinen', 'deiner', 'deines', 'denn',
        'dich', 'dir', 'du', 'dies', 'diese', 'diesem', 'diesen', 'dieser', 'dieses', 'doch',
        'durch', 'ein', 'eine', 'einem', 'einen', 'einer', 'eines', 'er', 'ihn', 'ihm', 'es',
        'etwas', 'euer', 'eure', 'eurem', 'euren', 'eurer', 'eures', 'für', 'gegen', 'hab',
        'habe', 'haben', 'hat', 'hatte', 'hatten', 'hier', 'hin', 'hinter', 'ich', 'mich', 'mir',
        'ihr', 'ihre', 'ihrem', 'ihren', 'ihrer', 'ihres', 'euch', 'im', 'in', 'indem', 'ins',
        'ist', 'jede', 'jedem', 'jeden', 'jeder', 'jedes', 'jene', 'jenem', 'jenen', 'jener',
        'jenes', 'jetzt', 'kann', 'kein', 'keine', 'keinem', 'keinen', 'keiner', 'keines',
        'können', 'könnte', 'machen', 'man', 'mein', 'meine', 'meinem', 'meinen', 'meiner',
        'meines', 'mit', 'muss', 'musste', 'nach', 'nicht', 'nichts', 'noch', 'nun', 'nur',
        'ob', 'oder', 'ohne', 'sehr', 'sein', 'seine', 'seinem', 'seinen', 'seiner', 'seines',
        'sich', 'sie', 'ihnen', 'sind', 'so', 'soll', 'sollte', 'sondern', 'sonst', 'über',
        'um', 'und', 'uns', 'unse', 'unsem', 'unsen', 'unser', 'unses', 'unter', 'viel', 'vom',
        'von', 'vor', 'während', 'war', 'waren', 'warst', 'was', 'weg', 'weil', 'weiter',
        'welche', 'welchem', 'welchen', 'welcher', 'welches', 'wenn', 'werde', 'werden', 'wie',
        'wieder', 'will', 'wir', 'wird', 'wirst', 'wo', 'wollen', 'wollte', 'würde', 'würden',
        'zu', 'zum', 'zur', 'zwar', 'zwischen',
        'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'had', 'her', 'was',
        'one', 'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how', 'its', 'may', 'new',
        'now', 'old', 'see', 'two', 'who', 'boy', 'did', 'own', 'say', 'she', 'too', 'use',
        'with', 'have', 'this', 'will', 'your', 'from', 'they', 'been', 'call', 'come', 'could',
        'each', 'make', 'than', 'them', 'then', 'what', 'when', 'word', 'said', 'which', 'their',
        'there', 'about', 'would', 'other', 'into', 'more', 'some', 'time', 'very', 'just',
        'also', 'back', 'after', 'think', 'that', 'these', 'being', 'does', 'most', 'made',
    ];

    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $words = array_filter($words, function ($w) use ($stopWords) {
        return mb_strlen($w) > 3 && !in_array($w, $stopWords);
    });

    $counts = array_count_values($words);
    arsort($counts);

    $top = array_slice($counts, 0, $limit, true);
    $total = max(1, array_sum($top));

    $keywords = [];
    foreach ($top as $word => $count) {
        $keywords[] = [
            'word'    => $word,
            'count'   => $count,
            'density' => round(($count / $total) * 100, 2),
        ];
    }

    return $keywords;
}

/**
 * Extract heading structure from HTML.
 *
 * @param string $html
 * @return array
 */
function extractHeadingStructure(string $html): array
{
    $structure = [];
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    for ($level = 1; $level <= 6; $level++) {
        $headings = $dom->getElementsByTagName('h' . $level);
        $items = [];
        foreach ($headings as $h) {
            $items[] = trim($h->textContent);
        }
        if (!empty($items)) {
            $structure['h' . $level] = [
                'count' => count($items),
                'items' => $items,
            ];
        }
    }

    return $structure;
}

/**
 * Count images in HTML.
 *
 * @param string $html
 * @return array
 */
function analyzeImages(string $html): array
{
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $images = $dom->getElementsByTagName('img');
    $withAlt = 0;
    $withoutAlt = 0;
    $imageList = [];

    foreach ($images as $img) {
        $src = $img->getAttribute('src') ?? '';
        $alt = $img->getAttribute('alt') ?? '';
        if (trim($alt) !== '') {
            $withAlt++;
        } else {
            $withoutAlt++;
        }
        $imageList[] = ['src' => $src, 'alt' => $alt];
    }

    return [
        'total'       => $images->length,
        'with_alt'    => $withAlt,
        'without_alt' => $withoutAlt,
        'images'      => $imageList,
    ];
}

/**
 * Count internal and external links in HTML.
 *
 * @param string $html
 * @param string $baseUrl
 * @return array
 */
function analyzeLinks(string $html, string $baseUrl = ''): array
{
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $links = $dom->getElementsByTagName('a');
    $internal = 0;
    $external = 0;
    $internalLinks = [];
    $externalLinks = [];

    $baseHost = $baseUrl ? parse_url($baseUrl, PHP_URL_HOST) : '';

    foreach ($links as $link) {
        $href = trim($link->getAttribute('href') ?? '');
        $text = trim($link->textContent);

        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
            continue;
        }

        $linkHost = parse_url($href, PHP_URL_HOST);
        if ($linkHost === null || $linkHost === '' || ($baseHost && $linkHost === $baseHost)) {
            $internal++;
            $internalLinks[] = ['href' => $href, 'text' => $text];
        } else {
            $external++;
            $externalLinks[] = ['href' => $href, 'text' => $text];
        }
    }

    return [
        'internal_count' => $internal,
        'external_count' => $external,
        'total'          => $internal + $external,
        'internal_links' => $internalLinks,
        'external_links' => $externalLinks,
    ];
}

/**
 * Get meta description length from HTML.
 *
 * @param string $html
 * @return array
 */
function getMetaDescriptionInfo(string $html): array
{
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $metas = $dom->getElementsByTagName('meta');
    foreach ($metas as $meta) {
        $name = strtolower($meta->getAttribute('name') ?? '');
        if ($name === 'description') {
            $content = $meta->getAttribute('content') ?? '';
            return [
                'exists'   => true,
                'length'   => mb_strlen($content),
                'value'    => $content,
                'optimal'  => mb_strlen($content) >= 120 && mb_strlen($content) <= 160,
            ];
        }
    }

    return [
        'exists'  => false,
        'length'  => 0,
        'value'   => '',
        'optimal' => false,
    ];
}

/**
 * Generate content analysis recommendations.
 *
 * @param array $analysis The full analysis result
 * @return array
 */
function generateContentRecommendations(array $analysis): array
{
    $recommendations = [];

    // Word count
    $wc = $analysis['word_count'] ?? 0;
    if ($wc < 300) {
        $recommendations[] = '⚠️ Content is very short (' . $wc . ' words). Aim for at least 300 words for SEO.';
    } elseif ($wc < 800) {
        $recommendations[] = 'ℹ️ Content length is moderate (' . $wc . ' words). Consider expanding to 800+ words for better rankings.';
    } else {
        $recommendations[] = '✅ Good content length (' . $wc . ' words).';
    }

    // Readability
    $rs = $analysis['readability_score'] ?? 0;
    if ($rs < 30) {
        $recommendations[] = '⚠️ Content is difficult to read. Use shorter sentences and simpler words.';
    } elseif ($rs < 60) {
        $recommendations[] = 'ℹ️ Readability is acceptable. Consider simplifying complex sentences.';
    } else {
        $recommendations[] = '✅ Content readability is good.';
    }

    // Heading structure
    $hs = $analysis['heading_structure'] ?? [];
    if (empty($hs)) {
        $recommendations[] = '❌ No headings found. Add H1-H6 tags to structure your content.';
    } else {
        $h1Count = $hs['h1']['count'] ?? 0;
        if ($h1Count === 0) {
            $recommendations[] = '❌ No H1 tag found. Every page should have exactly one H1 heading.';
        } elseif ($h1Count > 1) {
            $recommendations[] = '⚠️ Multiple H1 tags found (' . $h1Count . '). Use only one H1 per page.';
        } else {
            $recommendations[] = '✅ H1 tag is present.';
        }
    }

    // Images
    $img = $analysis['image_analysis'] ?? [];
    $imgTotal = $img['total'] ?? 0;
    $imgNoAlt = $img['without_alt'] ?? 0;
    if ($imgTotal === 0) {
        $recommendations[] = 'ℹ️ No images found. Consider adding relevant images with alt text.';
    } elseif ($imgNoAlt > 0) {
        $recommendations[] = '⚠️ ' . $imgNoAlt . ' image(s) without alt text. Add descriptive alt attributes.';
    } else {
        $recommendations[] = '✅ All images have alt text.';
    }

    // Links
    $links = $analysis['link_analysis'] ?? [];
    $intLinks = $links['internal_count'] ?? 0;
    $extLinks = $links['external_count'] ?? 0;
    if ($intLinks === 0) {
        $recommendations[] = 'ℹ️ No internal links found. Add links to related content.';
    }
    if ($extLinks === 0) {
        $recommendations[] = 'ℹ️ No external links found. Consider linking to authoritative sources.';
    }

    // Meta description
    $meta = $analysis['meta_description'] ?? [];
    if (!($meta['exists'] ?? false)) {
        $recommendations[] = '❌ No meta description found. Add a compelling description (120-160 chars).';
    } elseif (!($meta['optimal'] ?? false)) {
        $recommendations[] = '⚠️ Meta description length (' . ($meta['length'] ?? 0) . ') is not optimal. Aim for 120-160 characters.';
    } else {
        $recommendations[] = '✅ Meta description is present and well-sized.';
    }

    // Keyword density
    $kd = $analysis['keyword_density'] ?? [];
    if (empty($kd)) {
        $recommendations[] = 'ℹ️ No significant keywords found. Ensure your content includes relevant terms.';
    } else {
        $topKw = $kd[0] ?? null;
        if ($topKw && ($topKw['density'] ?? 0) > 10) {
            $recommendations[] = '⚠️ Keyword "' . $topKw['word'] . '" density is very high (' . $topKw['density'] . '%). Avoid keyword stuffing.';
        } elseif ($topKw && ($topKw['density'] ?? 0) < 1) {
            $recommendations[] = 'ℹ️ Top keyword density is low. Consider using target keywords more frequently.';
        }
    }

    return $recommendations;
}
