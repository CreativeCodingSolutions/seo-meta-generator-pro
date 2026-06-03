<?php
/**
 * SEO Meta Generator Pro — Generator
 * 
 * Meta tag generation: builds meta titles, descriptions, Open Graph,
 * Twitter Cards, Schema.org JSON-LD, canonical, robots, hreflang.
 * 
 * @package SEO Meta Generator Pro
 * @version 2.0.0
 */

namespace SEOMetaGen;

class Generator
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'title_max_length' => 60,
            'description_max_length' => 160,
            'default_locale' => 'de_DE',
            'default_type' => 'website',
            'og_image_min_width' => 1200,
            'og_image_min_height' => 630,
            'brand_name' => '',
            'twitter_handle' => '',
            'default_image' => '',
            'organization_name' => '',
            'organization_logo' => '',
        ], $config);
    }

    /**
     * Generate an optimized meta title.
     */
    public function generateTitle(string $pageTitle, string $siteName = '', string $separator = ' | '): string
    {
        $site = $siteName ?: $this->config['brand_name'];
        $full = $site ? $pageTitle . $separator . $site : $pageTitle;
        return $this->truncate($full, $this->config['title_max_length']);
    }

    /**
     * Generate an optimized meta description.
     */
    public function generateDescription(string $contentHint = '', string $pageTitle = '', array $keywords = null): string
    {
        if ($contentHint) {
            $base = $contentHint;
        } elseif ($pageTitle) {
            $base = "Erfahre mehr über {$pageTitle}.";
        } else {
            $base = 'Entdecke wertvolle Informationen und Angebote auf unserer Seite.';
        }

        if ($keywords) {
            $kwText = implode(', ', array_slice($keywords, 0, 3));
            $base = "{$base} Themen: {$kwText}.";
        }

        $ctas = ['Jetzt entdecken!', 'Mehr erfahren →', 'Jetzt starten!', 'Kostenlos testen!', 'Direkt anfragen!'];
        foreach ($ctas as $cta) {
            $candidate = "{$base} {$cta}";
            if (mb_strlen($candidate) <= (int)$this->config['description_max_length']) {
                return $candidate;
            }
        }

        return $this->truncate($base, (int)$this->config['description_max_length']);
    }

    /**
     * Generate meta keywords string.
     */
    public function generateKeywords(string $pageTitle, array $extraKeywords = null): string
    {
        $words = [];

        foreach (mb_str_split(mb_strtolower($pageTitle)) as $char) {
            // handled below
        }

        $titleWords = preg_split('/\s+/', mb_strtolower($pageTitle));
        foreach ($titleWords as $word) {
            $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $word);
            if (mb_strlen($clean) > 3) {
                $words[] = $clean;
            }
        }

        if ($extraKeywords) {
            foreach ($extraKeywords as $kw) {
                $words[] = mb_strtolower(trim($kw));
            }
        }

        $words = array_unique($words);
        return implode(', ', array_slice($words, 0, 15));
    }

    /**
     * Generate Open Graph tags array.
     */
    public function generateOgTags(
        string $title,
        string $description,
        string $url,
        string $siteName = '',
        string $imageUrl = '',
        string $locale = 'de_DE',
        string $pageType = 'website'
    ): array {
        $og = [
            'og:title' => $title,
            'og:description' => $description,
            'og:url' => $url,
            'og:type' => $pageType,
            'og:locale' => $locale,
        ];

        $site = $siteName ?: $this->config['brand_name'] ?: $this->getSiteName($url);
        if ($site) {
            $og['og:site_name'] = $site;
        }

        $img = $imageUrl ?: $this->config['default_image'];
        if ($img) {
            $og['og:image'] = $img;
            $og['og:image:width'] = (string)$this->config['og_image_min_width'];
            $og['og:image:height'] = (string)$this->config['og_image_min_height'];
            $og['og:image:alt'] = $title;
        }

        return $og;
    }

    /**
     * Generate Twitter Card tags array.
     */
    public function generateTwitterCards(
        string $title,
        string $description,
        string $imageUrl = '',
        string $cardType = 'summary_large_image'
    ): array {
        $twitter = [
            'twitter:card' => $cardType,
            'twitter:title' => $this->truncate($title, 70),
            'twitter:description' => $this->truncate($description, 200),
        ];

        $handle = $this->config['twitter_handle'];
        if ($handle) {
            $twitter['twitter:site'] = str_starts_with($handle, '@') ? $handle : '@' . $handle;
        }

        $img = $imageUrl ?: $this->config['default_image'];
        if ($img) {
            $twitter['twitter:image'] = $img;
            $twitter['twitter:image:alt'] = $title;
        }

        return $twitter;
    }

    /**
     * Generate Schema.org JSON-LD structured data.
     */
    public function generateJsonLd(
        string $pageType,
        string $title,
        string $description,
        string $url,
        string $imageUrl = '',
        string $authorName = '',
        string $datePublished = '',
        string $dateModified = '',
        string $orgName = '',
        string $orgLogo = ''
    ): array {
        $now = gmdate('Y-m-d\TH:i:s+00:00');

        switch ($pageType) {
            case 'article':
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'Article',
                    'headline' => $title,
                    'description' => $description,
                    'url' => $url,
                    'datePublished' => $datePublished ?: $now,
                    'dateModified' => $dateModified ?: $now,
                ];
                if ($authorName) {
                    $schema['author'] = ['@type' => 'Person', 'name' => $authorName];
                }
                if ($imageUrl) {
                    $schema['image'] = $imageUrl;
                }
                break;

            case 'product':
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'Product',
                    'name' => $title,
                    'description' => $description,
                    'url' => $url,
                ];
                if ($imageUrl) {
                    $schema['image'] = $imageUrl;
                }
                break;

            case 'profile':
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'ProfilePage',
                    'name' => $title,
                    'description' => $description,
                    'url' => $url,
                ];
                if ($authorName) {
                    $schema['mainEntity'] = ['@type' => 'Person', 'name' => $authorName];
                }
                break;

            default:
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    'name' => $title,
                    'description' => $description,
                    'url' => $url,
                ];
        }

        $org = $orgName ?: $this->config['organization_name'];
        $logo = $orgLogo ?: $this->config['organization_logo'];
        if ($org) {
            $publisher = ['@type' => 'Organization', 'name' => $org, 'url' => $url];
            if ($logo) {
                $publisher['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
            }
            $schema['publisher'] = $publisher;
        }

        return $schema;
    }

    /**
     * Generate all meta output at once.
     */
    public function generateAll(array $data): array
    {
        $title = $this->generateTitle(
            $data['title'] ?? '',
            $data['site_name'] ?? '',
            $data['separator'] ?? ' | '
        );

        $keywordsArr = !empty($data['keywords']) ? array_filter(array_map('trim', explode(',', $data['keywords']))) : null;
        $description = $this->generateDescription(
            $data['description'] ?? '',
            $data['title'] ?? '',
            $keywordsArr
        );
        $keywordsStr = $this->generateKeywords(
            $data['title'] ?? '',
            $keywordsArr
        );
        $canonical = $data['url'] ?? '';
        $og = $this->generateOgTags(
            $title, $description, $canonical,
            $data['site_name'] ?? '', $data['image_url'] ?? '',
            $data['locale'] ?? 'de_DE', $data['type'] ?? 'website'
        );
        $twitter = $this->generateTwitterCards(
            $title, $description, $data['image_url'] ?? '',
            $data['twitter_card_type'] ?? 'summary_large_image'
        );
        $jsonLd = $this->generateJsonLd(
            $data['type'] ?? 'website', $title, $description, $canonical,
            $data['image_url'] ?? '', $data['author_name'] ?? '',
            $data['date_published'] ?? '', $data['date_modified'] ?? '',
            $data['org_name'] ?? '', $data['org_logo'] ?? ''
        );
        $robots = $this->generateRobots(
            !($data['no_index'] ?? false), !($data['no_follow'] ?? false)
        );

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywordsStr,
            'canonical' => $canonical,
            'robots' => $robots,
            'og_tags' => $og,
            'twitter_cards' => $twitter,
            'json_ld' => $jsonLd,
            'raw_data' => $data,
        ];
    }

    /**
     * Generate the complete HTML meta tag block.
     */
    public function renderHtml(array $generated): string
    {
        $lines = [];
        $lines[] = '<!--';
        $lines[] = '  SEO Meta Tags — Generated with SEO Meta Generator Pro v2.0';
        $lines[] = '  Generated at: ' . gmdate('Y-m-d H:i:s') . ' UTC';
        $lines[] = '  https://github.com/CreativeCodingSolutions/seo-meta-generator-pro';
        $lines[] = '-->';
        $lines[] = '';
        $lines[] = '<meta charset="UTF-8">';
        $lines[] = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $lines[] = '<title>' . htmlspecialchars($generated['title']) . '</title>';
        $lines[] = '<meta name="description" content="' . htmlspecialchars($generated['description']) . '">';
        if ($generated['keywords']) {
            $lines[] = '<meta name="keywords" content="' . htmlspecialchars($generated['keywords']) . '">';
        }
        $lines[] = '<meta name="robots" content="' . htmlspecialchars($generated['robots']) . '">';
        $lines[] = '';
        $lines[] = '<link rel="canonical" href="' . htmlspecialchars($generated['canonical']) . '">';
        $lines[] = '';

        // Open Graph
        $lines[] = '<!-- Open Graph / Facebook -->';
        foreach ($generated['og_tags'] as $k => $v) {
            $lines[] = '<meta property="' . htmlspecialchars($k) . '" content="' . htmlspecialchars($v) . '">';
        }
        $lines[] = '';

        // Twitter
        $lines[] = '<!-- Twitter Cards -->';
        foreach ($generated['twitter_cards'] as $k => $v) {
            $lines[] = '<meta name="' . htmlspecialchars($k) . '" content="' . htmlspecialchars($v) . '">';
        }
        $lines[] = '';

        // JSON-LD
        $lines[] = '<!-- Schema.org JSON-LD -->';
        $lines[] = '<script type="application/ld+json">';
        $lines[] = json_encode($generated['json_ld'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $lines[] = '</script>';

        return implode("\n", $lines);
    }

    /**
     * Generate complete HTML page.
     */
    public function renderFullHtml(array $generated, string $lang = 'de'): string
    {
        $meta = $this->renderHtml($generated);
        return <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
{$meta}
</head>
<body>
<!-- Your content here -->
</body>
</html>
HTML;
    }

    /**
     * Generate robots meta value.
     */
    public function generateRobots(bool $index = true, bool $follow = true): string
    {
        return ($index ? 'index' : 'noindex') . ', ' . ($follow ? 'follow' : 'nofollow');
    }

    /**
     * Generate hreflang tags.
     */
    public function generateHreflang(string $url, array $locales = ['de', 'en']): array
    {
        $langMap = [
            'de' => 'de-DE', 'de_DE' => 'de-DE', 'de_AT' => 'de-AT', 'de_CH' => 'de-CH',
            'en' => 'en-US', 'en_US' => 'en-US', 'en_GB' => 'en-GB',
            'fr' => 'fr-FR', 'fr_FR' => 'fr-FR',
        ];
        $tags = [];
        foreach ($locales as $locale) {
            $lang = $langMap[$locale] ?? $locale;
            $tags[] = ['lang' => $lang, 'url' => $url];
        }
        return $tags;
    }

    // ── Private helpers ──────────────────────────────────────

    private function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) return $text;
        $truncated = wordwrap($text, $maxLength, "\n", true);
        $lines = explode("\n", $truncated);
        return ($lines[0] ?? mb_substr($text, 0, $maxLength)) . '…';
    }

    private function getSiteName(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return 'Website';
        $name = preg_replace('/^www\./', '', $host);
        return ucfirst(strtok($name, '.'));
    }
}
