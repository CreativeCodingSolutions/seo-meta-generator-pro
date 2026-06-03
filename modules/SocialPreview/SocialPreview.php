<?php
/**
 * SEO Meta Generator Pro — Social Preview Module
 * 
 * Shows how meta tags render on social media platforms:
 * Facebook, Twitter/X, LinkedIn previews.
 * 
 * @package SEO Meta Generator Pro\Modules\SocialPreview
 * @version 2.0.0
 */

namespace SEOMetaGen\Modules\SocialPreview;

class SocialPreview
{
    /**
     * Generate social media preview data.
     */
    public function generatePreviews(array $meta): array
    {
        $title = $meta['og_tags']['og:title'] ?? $meta['title'] ?? '';
        $description = $meta['og_tags']['og:description'] ?? $meta['description'] ?? '';
        $image = $meta['og_tags']['og:image'] ?? $meta['image'] ?? '';
        $url = $meta['og_tags']['og:url'] ?? $meta['url'] ?? '';
        $siteName = $meta['og_tags']['og:site_name'] ?? '';

        return [
            'facebook' => $this->facebookPreview($title, $description, $image, $url, $siteName),
            'twitter' => $this->twitterPreview($title, $description, $image, $url, $siteName),
            'linkedin' => $this->linkedinPreview($title, $description, $image, $url, $siteName),
        ];
    }

    /**
     * Render social preview HTML for all platforms.
     */
    public function render(array $meta): string
    {
        $previews = $this->generatePreviews($meta);
        $html = '<div class="social-previews">';
        $html .= '<h3>📱 Social Media Previews</h3>';
        $html .= '<div class="social-preview-grid">';

        foreach ($previews as $platform => $preview) {
            $html .= $this->renderPlatformCard($platform, $preview);
        }

        $html .= '</div></div>';
        return $html;
    }

    private function facebookPreview(string $title, string $description, string $image, string $url, string $siteName): array
    {
        return [
            'platform' => 'Facebook',
            'icon' => '📘',
            'title' => $this->truncate($title, 100),
            'description' => $this->truncate($description, 200),
            'image' => $image,
            'url' => $this->formatUrl($url),
            'site_name' => $siteName,
            'card_type' => 'Large Image Preview',
        ];
    }

    private function twitterPreview(string $title, string $description, string $image, string $url, string $siteName): array
    {
        return [
            'platform' => 'Twitter / X',
            'icon' => '🐦',
            'title' => $this->truncate($title, 70),
            'description' => $this->truncate($description, 200),
            'image' => $image,
            'url' => $this->formatUrl($url),
            'site_name' => $siteName,
            'card_type' => 'summary_large_image',
        ];
    }

    private function linkedinPreview(string $title, string $description, string $image, string $url, string $siteName): array
    {
        return [
            'platform' => 'LinkedIn',
            'icon' => '💼',
            'title' => $this->truncate($title, 100),
            'description' => $this->truncate($description, 150),
            'image' => $image,
            'url' => $this->formatUrl($url),
            'site_name' => $siteName,
            'card_type' => 'Shared Link Preview',
        ];
    }

    private function renderPlatformCard(string $platform, array $preview): string
    {
        $imgHtml = '';
        if (!empty($preview['image'])) {
            $imgHtml = '<div class="sp-image"><img src="' . htmlspecialchars($preview['image']) . '" alt="" onerror="this.parentElement.style.display=\'none\'"></div>';
        } else {
            $imgHtml = '<div class="sp-image sp-no-image"><span>No image</span></div>';
        }

        return <<<HTML
<div class="sp-card sp-{$platform}">
    <div class="sp-header">
        <span class="sp-icon">{$preview['icon']}</span>
        <span class="sp-platform">{$preview['platform']}</span>
        <span class="sp-card-type">{$preview['card_type']}</span>
    </div>
    {$imgHtml}
    <div class="sp-content">
        <div class="sp-url">{$preview['url']}</div>
        <div class="sp-title">{$preview['title']}</div>
        <div class="sp-description">{$preview['description']}</div>
    </div>
</div>
HTML;
    }

    private function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) return $text;
        return mb_substr($text, 0, $max - 1) . '…';
    }

    private function formatUrl(string $url): string
    {
        if (empty($url)) return 'example.com';
        $host = parse_url($url, PHP_URL_HOST);
        return $host ?: $url;
    }
}
