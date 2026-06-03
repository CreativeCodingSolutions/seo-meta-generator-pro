<?php
/**
 * SEO Meta Generator Pro — Analyzer
 * 
 * SEO analysis logic: URL validation, meta tag extraction,
 * scoring, competitor comparison, bulk analysis.
 * 
 * @package SEO Meta Generator Pro
 * @version 2.0.0
 */

namespace SEOMetaGen;

class Analyzer
{
    /** @var array Default configuration */
    private array $config = [
        'title_max_length' => 60,
        'description_max_length' => 160,
        'og_image_min_width' => 1200,
        'og_image_min_height' => 630,
    ];

    /** @var array Last analysis result */
    private array $lastResult = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Fetch and parse meta tags from a URL.
     */
    public function fetchUrl(string $url): array
    {
        if (!$this->validateUrl($url)) {
            return $this->errorResult($url, 'Invalid URL format');
        }

        $html = $this->httpGet($url);
        if ($html === null) {
            // Return simulated analysis for offline mode
            return $this->simulateAnalysis($url);
        }

        return $this->parseMetaTags($url, $html);
    }

    /**
     * Parse meta tags from HTML string.
     */
    public function parseHtml(string $html, string $url = ''): array
    {
        return $this->parseMetaTags($html, $url);
    }

    /**
     * Analyze meta tags from raw HTML input.
     */
    public function analyze(string $url, string $title = '', string $description = '', string $keywords = '', string $image = ''): array
    {
        $scores = [];
        $suggestions = [];

        // Title analysis
        $titleLen = mb_strlen($title);
        if ($titleLen === 0) {
            $scores['title'] = 0;
            $suggestions[] = '❌ Add a meta title — it\'s the most important SEO element.';
        } elseif ($titleLen < 30) {
            $scores['title'] = 40;
            $suggestions[] = '⚠️ Title is too short. Aim for 50-60 characters.';
        } elseif ($titleLen > $this->config['title_max_length']) {
            $scores['title'] = 60;
            $suggestions[] = '⚠️ Title exceeds recommended length. It may be truncated in search results.';
        } else {
            $scores['title'] = 100;
            $suggestions[] = '✅ Title length is optimal.';
        }

        // Description analysis
        $descLen = mb_strlen($description);
        if ($descLen === 0) {
            $scores['description'] = 0;
            $suggestions[] = '❌ Add a meta description — it influences click-through rates.';
        } elseif ($descLen < 70) {
            $scores['description'] = 40;
            $suggestions[] = '⚠️ Description is too short. Aim for 120-160 characters.';
        } elseif ($descLen > $this->config['description_max_length']) {
            $scores['description'] = 60;
            $suggestions[] = '⚠️ Description exceeds recommended length and may be truncated.';
        } else {
            $scores['description'] = 100;
            $suggestions[] = '✅ Description length is optimal.';
        }

        // Keywords analysis
        $kwCount = empty($keywords) ? 0 : count(array_filter(array_map('trim', explode(',', $keywords))));
        if ($kwCount === 0) {
            $scores['keywords'] = 30;
            $suggestions[] = '⚠️ No meta keywords defined (low SEO impact but still relevant for internal search).';
        } elseif ($kwCount > 15) {
            $scores['keywords'] = 50;
            $suggestions[] = '⚠️ Too many keywords. Stick to 5-10 relevant keywords.';
        } else {
            $scores['keywords'] = 100;
            $suggestions[] = '✅ Keywords count looks good.';
        }

        // Image analysis
        if (empty($image)) {
            $scores['image'] = 30;
            $suggestions[] = '❌ No OG image defined — social media previews will look bland.';
        } else {
            $scores['image'] = 100;
            $suggestions[] = '✅ OG image is set.';
        }

        // URL analysis
        if (empty($url)) {
            $scores['url'] = 0;
            $suggestions[] = '❌ No URL provided for canonical/link generation.';
        } elseif (mb_strlen($url) > 115) {
            $scores['url'] = 70;
            $suggestions[] = '⚠️ URL is quite long. Shorter URLs tend to perform better.';
        } else {
            $scores['url'] = 100;
            $suggestions[] = '✅ URL is properly defined.';
        }

        // Check HTTPS
        if (!empty($url) && strpos($url, 'https://') !== 0) {
            $scores['https'] = 40;
            $suggestions[] = '⚠️ URL does not use HTTPS. SSL is a ranking factor.';
        } else {
            $scores['https'] = 100;
            $suggestions[] = '✅ HTTPS is enabled.';
        }

        // Overall score
        $overallScore = (int) round(array_sum($scores) / count($scores));

        $result = [
            'url' => $url,
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'image' => $image,
            'scores' => $scores,
            'overall_score' => $overallScore,
            'suggestions' => $suggestions,
            'grade' => $this->scoreToGrade($overallScore),
            'analyzed_at' => date('Y-m-d H:i:s'),
        ];

        $this->lastResult = $result;
        return $result;
    }

    /**
     * Compare multiple URLs side by side.
     */
    public function compare(array $urlsData): array
    {
        $results = [];
        foreach ($urlsData as $data) {
            $results[] = $this->analyze(
                $data['url'] ?? '',
                $data['title'] ?? '',
                $data['description'] ?? '',
                $data['keywords'] ?? '',
                $data['image'] ?? ''
            );
        }

        // Determine winner
        $winnerIdx = 0;
        $maxScore = 0;
        foreach ($results as $i => $r) {
            if ($r['overall_score'] > $maxScore) {
                $maxScore = $r['overall_score'];
                $winnerIdx = $i;
            }
        }

        return [
            'competitors' => $results,
            'winner_index' => $winnerIdx,
            'winner_score' => $maxScore,
        ];
    }

    /**
     * Bulk analyze multiple URLs.
     */
    public function bulkAnalyze(array $items): array
    {
        $results = [];
        foreach ($items as $item) {
            $results[] = $this->analyze(
                $item['url'] ?? '',
                $item['title'] ?? '',
                $item['description'] ?? '',
                $item['keywords'] ?? '',
                $item['image'] ?? ''
            );
        }

        $avgScore = empty($results) ? 0 : (int) round(array_sum(array_column($results, 'overall_score')) / count($results));

        return [
            'results' => $results,
            'total' => count($results),
            'average_score' => $avgScore,
        ];
    }

    /**
     * Extract keywords from text (density analysis).
     */
    public function extractKeywords(string $text, int $limit = 20): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

        $stopWords = [
            'aber', 'alle', 'allem', 'allen', 'aller', 'als', 'also', 'ander', 'andere', 'anderem',
            'anderen', 'anderer', 'anderes', 'anderm', 'andern', 'anders', 'auch', 'auf', 'aus',
            'bei', 'bin', 'bis', 'bist', 'da', 'damit', 'dann', 'der', 'den', 'des', 'dem', 'die',
            'das', 'dass', 'daß', 'derselbe', 'derselben', 'denselben', 'desselben', 'demselben',
            'dieselbe', 'dieselben', 'dasselbe', 'dazu', 'dein', 'deine', 'deinem', 'deinen',
            'deiner', 'deines', 'denn', 'derer', 'dessen', 'dich', 'dir', 'du', 'dies', 'diese',
            'diesem', 'diesen', 'dieser', 'dieses', 'doch', 'dort', 'durch', 'ein', 'eine', 'einem',
            'einen', 'einer', 'eines', 'einig', 'einige', 'einigem', 'einigen', 'einiger', 'einiges',
            'einmal', 'er', 'ihn', 'ihm', 'es', 'etwas', 'euer', 'eure', 'eurem', 'euren', 'eurer',
            'eures', 'für', 'gegen', 'gewesen', 'hab', 'habe', 'haben', 'hat', 'hatte', 'hatten',
            'hier', 'hin', 'hinter', 'ich', 'mich', 'mir', 'ihr', 'ihre', 'ihrem', 'ihren', 'ihrer',
            'ihres', 'euch', 'im', 'in', 'indem', 'ins', 'ist', 'jede', 'jedem', 'jeden', 'jeder',
            'jedes', 'jene', 'jenem', 'jenen', 'jener', 'jenes', 'jetzt', 'kann', 'kein', 'keine',
            'keinem', 'keinen', 'keiner', 'keines', 'können', 'könnte', 'machen', 'man', 'manche',
            'manchem', 'manchen', 'mancher', 'manches', 'mein', 'meine', 'meinem', 'meinen', 'meiner',
            'meines', 'mit', 'muss', 'musste', 'nach', 'nicht', 'nichts', 'noch', 'nun', 'nur',
            'ob', 'oder', 'ohne', 'sehr', 'sein', 'seine', 'seinem', 'seinen', 'seiner', 'seines',
            'selbst', 'sich', 'sie', 'ihnen', 'sind', 'so', 'solche', 'solchem', 'solchen', 'solcher',
            'solches', 'soll', 'sollte', 'sondern', 'sonst', 'über', 'um', 'und', 'uns', 'unse',
            'unsem', 'unsen', 'unser', 'unses', 'unter', 'viel', 'vom', 'von', 'vor', 'während',
            'war', 'waren', 'warst', 'was', 'weg', 'weil', 'weiter', 'welche', 'welchem', 'welchen',
            'welcher', 'welches', 'wenn', 'werde', 'werden', 'wie', 'wieder', 'will', 'wir', 'wird',
            'wirst', 'wo', 'wollen', 'wollte', 'würde', 'würden', 'zu', 'zum', 'zur', 'zwar', 'zwischen',
            // English stop words
            'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'had', 'her', 'was', 'one',
            'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how', 'its', 'may', 'new', 'now', 'old',
            'see', 'two', 'who', 'boy', 'did', 'own', 'say', 'she', 'too', 'use', 'with', 'have', 'this',
            'will', 'your', 'from', 'they', 'been', 'call', 'come', 'could', 'each', 'make', 'than',
            'them', 'then', 'what', 'when', 'word', 'said', 'which', 'their', 'there', 'about', 'would',
            'other', 'into', 'more', 'some', 'time', 'very', 'just', 'also', 'back', 'after', 'think',
        ];

        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, function ($w) use ($stopWords) {
            return mb_strlen($w) > 3 && !in_array($w, $stopWords);
        });

        $counts = array_count_values($words);
        arsort($counts);

        $top = array_slice($counts, 0, $limit, true);
        $total = array_sum($top);

        $keywords = [];
        foreach ($top as $word => $count) {
            $keywords[] = [
                'word' => $word,
                'count' => $count,
                'density' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
            ];
        }

        return $keywords;
    }

    /**
     * Get the last analysis result.
     */
    public function getLastResult(): array
    {
        return $this->lastResult;
    }

    // ── Private helpers ──────────────────────────────────────

    private function parseMetaTags(string $html, string $url): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $title = '';
        $description = '';
        $keywords = '';
        $image = '';

        // Title
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            $title = trim($titles->item(0)->textContent);
        }

        // Meta tags
        $metas = $dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            $name = strtolower($meta->getAttribute('name') ?? $meta->getAttribute('property') ?? '');
            $content = $meta->getAttribute('content') ?? '';

            if ($name === 'description') $description = $content;
            if ($name === 'keywords') $keywords = $content;
            if ($name === 'og:image') $image = $content;
        }

        return $this->analyze($url, $title, $description, $keywords, $image);
    }

    private function httpGet(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'SEO-Meta-Generator-Pro/2.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code >= 200 && $code < 400 && $html) ? $html : null;
    }

    private function simulateAnalysis(string $url): array
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? $url;
        $path = $parsed['path'] ?? '';

        $pageName = ucwords(str_replace(['-', '_', '/'], ' ', trim($path, '/')));
        $siteName = ucwords(str_replace(['www.'], '', $host));
        $pageName = $pageName ?: $siteName;

        $title = $siteName . ($pageName !== $siteName ? ' | ' . $pageName : '');
        $description = "Entdecke {$pageName} auf {$siteName}. Informationen, Angebote und mehr.";

        return $this->analyze($url, $title, $description, '', '');
    }

    private function errorResult(string $url, string $error): array
    {
        return [
            'url' => $url,
            'title' => '',
            'description' => '',
            'keywords' => '',
            'image' => '',
            'scores' => ['title' => 0, 'description' => 0, 'keywords' => 0, 'image' => 0, 'url' => 0, 'https' => 0],
            'overall_score' => 0,
            'suggestions' => ["❌ Error: {$error}"],
            'grade' => 'F',
            'analyzed_at' => date('Y-m-d H:i:s'),
            'error' => true,
            'error_message' => $error,
        ];
    }

    private function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function scoreToGrade(int $score): string
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        if ($score >= 40) return 'E';
        return 'F';
    }
}
