<?php
/**
 * SEO Meta Generator Pro — Schema Markup Generator API
 *
 * Generiert JSON-LD Schema.org Markup für verschiedene Content-Typen.
 *
 * Endpoints:
 *   POST /api/schema-markup.php?action=generate  { "type": "Article", "data": {...} }
 *   GET  /api/schema-markup.php?action=types
 *   POST /api/schema-markup.php?action=validate  { "schema": "{...}" }
 *   POST /api/schema-markup.php?action=bulk      { "items": [{"type": "Article", "data": {...}}] }
 *
 * @package SEO Meta Generator Pro
 * @version 3.3.0
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cache.php';

use SEOMetaGen\Cache\FileCache;

// ── Security Headers ──────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-RateLimit-Limit: 100');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$cacheDir = dirname(__DIR__) . '/data/cache';
$cache    = new FileCache($cacheDir);
$action   = preg_replace('/[^a-zA-Z_-]/', '', $_GET['action'] ?? $_POST['action'] ?? 'generate');

switch ($action) {
    case 'generate': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['error' => 'POST required for generate action'], 405);
        }
        $input = getInput();
        $type  = sanitizeString($input['type'] ?? 'Article');
        $data  = $input['data'] ?? [];
        if (empty($data) || !is_array($data)) {
            jsonResponse(['error' => 'data ist erforderlich (Array)'], 400);
        }
        $cacheKey = 'schema_' . md5($type . serialize($data));
        $cached   = $cache->get($cacheKey);
        if ($cached !== null) {
            echo json_encode(['success' => true, 'cached' => true, 'data' => $cached], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
        $schema = generateSchemaMarkup($type, $data);
        $cache->set($cacheKey, $schema, 3600);
        echo json_encode(['success' => true, 'cached' => false, 'data' => $schema], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        break;
    }

    case 'types': {
        jsonResponse([
            'available_types' => [
                'Article', 'NewsArticle', 'BlogPosting', 'Product', 'LocalBusiness',
                'Organization', 'Person', 'Event', 'FAQPage', 'HowTo', 'Recipe',
                'Review', 'VideoObject', 'BreadcrumbList', 'WebSite', 'Service',
            ],
            'examples' => [
                'Article' => ['headline', 'author', 'datePublished', 'image', 'description'],
                'Product' => ['name', 'description', 'brand', 'price', 'currency', 'availability', 'review'],
                'LocalBusiness' => ['name', 'address', 'telephone', 'openingHours', 'priceRange'],
                'FAQPage' => ['questions' => [['question', 'answer']]],
                'HowTo' => ['name', 'steps' => [['name', 'text', 'image']]],
            ],
        ]);
        break;
    }

    case 'validate': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['error' => 'POST required for validate action'], 405);
        }
        $input  = getInput();
        $schema = $input['schema'] ?? '';
        if (empty($schema)) {
            jsonResponse(['error' => 'schema JSON ist erforderlich'], 400);
        }
        // Accept both JSON string and array
        if (is_array($schema)) {
            $decoded = $schema;
        } else {
            $decoded = json_decode($schema, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                jsonResponse(['valid' => false, 'error' => 'Ungültiges JSON: ' . json_last_error_msg()]);
            }
        }
        $errors   = validateSchemaMarkup($decoded);
        $warnings = validateSchemaWarnings($decoded);
        jsonResponse([
            'valid'    => empty($errors),
            'errors'   => $errors,
            'warnings' => $warnings,
            'type'     => $decoded['@type'] ?? 'unknown',
            'context'  => $decoded['@context'] ?? 'missing',
        ]);
        break;
    }

    case 'bulk': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['error' => 'POST required for bulk action'], 405);
        }
        $input = getInput();
        $items = $input['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            jsonResponse(['error' => 'items Array ist erforderlich'], 400);
        }
        $results = [];
        foreach ($items as $item) {
            $type    = sanitizeString($item['type'] ?? 'Article');
            $data    = $item['data'] ?? [];
            $results[] = generateSchemaMarkup($type, $data);
        }
        jsonResponse(['count' => count($results), 'schemas' => $results]);
        break;
    }

    default:
        jsonResponse(['error' => 'Unbekannte action. Verfügbar: generate, types, validate, bulk'], 400);
}

// ── Schema Markup Generator Function ───────────────────────────

function generateSchemaMarkup(string $type, array $data): array
{
    $base = [
        '@context' => 'https://schema.org',
        '@type'    => $type,
    ];

    switch ($type) {
        case 'Article':
        case 'NewsArticle':
        case 'BlogPosting':
            return array_merge($base, [
                'headline'        => sanitizeString($data['headline'] ?? $data['title'] ?? ''),
                'description'     => sanitizeString($data['description'] ?? ''),
                'author'          => parseAuthor($data['author'] ?? ''),
                'datePublished'   => $data['datePublished'] ?? $data['date'] ?? date('Y-m-d'),
                'dateModified'    => $data['dateModified'] ?? $data['datePublished'] ?? date('Y-m-d'),
                'image'           => $data['image'] ?? '',
                'publisher'       => [
                    '@type' => 'Organization',
                    'name'  => $data['publisher'] ?? $data['siteName'] ?? '',
                    'logo'  => ['@type' => 'ImageObject', 'url' => $data['publisherLogo'] ?? ''],
                ],
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $data['url'] ?? ''],
            ]);

        case 'Product': {
            $product = array_merge($base, [
                'name'        => sanitizeString($data['name'] ?? ''),
                'description' => sanitizeString($data['description'] ?? ''),
                'brand'       => ['@type' => 'Brand', 'name' => sanitizeString($data['brand'] ?? '')],
                'image'       => $data['image'] ?? '',
                'url'         => $data['url'] ?? '',
            ]);
            if (!empty($data['price'])) {
                $product['offers'] = [
                    '@type'        => 'Offer',
                    'price'        => (float) $data['price'],
                    'priceCurrency' => $data['currency'] ?? 'EUR',
                    'availability' => ($data['availability'] ?? 'InStock') === 'InStock'
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                ];
            }
            if (!empty($data['review']) || !empty($data['rating'])) {
                $product['aggregateRating'] = [
                    '@type'       => 'AggregateRating',
                    'ratingValue' => (float) ($data['rating'] ?? 4.5),
                    'reviewCount' => (int) ($data['reviewCount'] ?? 1),
                ];
            }
            return $product;
        }

        case 'LocalBusiness':
            return array_merge($base, [
                'name'        => sanitizeString($data['name'] ?? ''),
                'description' => sanitizeString($data['description'] ?? ''),
                'address'     => [
                    '@type'            => 'PostalAddress',
                    'streetAddress'   => $data['street'] ?? '',
                    'addressLocality' => $data['city'] ?? '',
                    'postalCode'      => $data['zip'] ?? '',
                    'addressCountry'  => $data['country'] ?? 'DE',
                ],
                'telephone'    => $data['telephone'] ?? $data['phone'] ?? '',
                'openingHours' => $data['openingHours'] ?? '',
                'priceRange'   => $data['priceRange'] ?? '',
                'url'          => $data['url'] ?? '',
                'image'        => $data['image'] ?? '',
            ]);

        case 'FAQPage':
            $faq = $base;
            $faq['mainEntity'] = [];
            $questions = $data['questions'] ?? [];
            foreach ($questions as $q) {
                $faq['mainEntity'][] = [
                    '@type'          => 'Question',
                    'name'           => sanitizeString($q['question'] ?? ''),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => sanitizeString($q['answer'] ?? ''),
                    ],
                ];
            }
            return $faq;

        case 'HowTo':
            $howto = array_merge($base, [
                'name'        => sanitizeString($data['name'] ?? ''),
                'description' => sanitizeString($data['description'] ?? ''),
            ]);
            $howto['step'] = [];
            $steps = $data['steps'] ?? [];
            foreach ($steps as $i => $step) {
                $howto['step'][] = [
                    '@type'    => 'HowToStep',
                    'position' => $i + 1,
                    'name'     => sanitizeString($step['name'] ?? ''),
                    'text'     => sanitizeString($step['text'] ?? ''),
                    'image'    => $step['image'] ?? '',
                ];
            }
            return $howto;

        case 'Event':
            return array_merge($base, [
                'name'        => sanitizeString($data['name'] ?? ''),
                'description' => sanitizeString($data['description'] ?? ''),
                'startDate'   => $data['startDate'] ?? '',
                'endDate'     => $data['endDate'] ?? '',
                'location'    => [
                    '@type' => 'Place',
                    'name'  => sanitizeString($data['location'] ?? ''),
                ],
                'organizer' => [
                    '@type' => 'Organization',
                    'name'  => sanitizeString($data['organizer'] ?? ''),
                ],
                'image' => $data['image'] ?? '',
            ]);

        case 'Organization':
            return array_merge($base, [
                'name'        => sanitizeString($data['name'] ?? ''),
                'url'         => $data['url'] ?? '',
                'logo'        => $data['logo'] ?? '',
                'description' => sanitizeString($data['description'] ?? ''),
                'address'     => $data['address'] ?? '',
                'telephone'   => $data['telephone'] ?? '',
            ]);

        case 'Person':
            return array_merge($base, [
                'name'        => sanitizeString($data['name'] ?? ''),
                'url'         => $data['url'] ?? '',
                'image'       => $data['image'] ?? '',
                'description' => sanitizeString($data['description'] ?? ''),
                'jobTitle'    => sanitizeString($data['jobTitle'] ?? ''),
                'worksFor'    => [
                    '@type' => 'Organization',
                    'name'  => sanitizeString($data['worksFor'] ?? ''),
                ],
            ]);

        case 'Recipe':
            $recipe = array_merge($base, [
                'name'        => sanitizeString($data['name'] ?? ''),
                'description' => sanitizeString($data['description'] ?? ''),
                'image'       => $data['image'] ?? '',
                'author'      => parseAuthor($data['author'] ?? ''),
                'datePublished' => $data['datePublished'] ?? date('Y-m-d'),
                'prepTime'    => $data['prepTime'] ?? '',
                'cookTime'    => $data['cookTime'] ?? '',
                'totalTime'   => $data['totalTime'] ?? '',
                'recipeYield' => $data['recipeYield'] ?? '',
            ]);
            if (!empty($data['ingredients']) && is_array($data['ingredients'])) {
                $recipe['recipeIngredient'] = array_map('sanitizeString', $data['ingredients']);
            }
            if (!empty($data['instructions']) && is_array($data['instructions'])) {
                $recipe['recipeInstructions'] = [];
                foreach ($data['instructions'] as $i => $step) {
                    $recipe['recipeInstructions'][] = [
                        '@type' => 'HowToStep',
                        'position' => $i + 1,
                        'text' => sanitizeString(is_string($step) ? $step : ($step['text'] ?? '')),
                    ];
                }
            }
            return $recipe;

        case 'Review':
            return array_merge($base, [
                'itemReviewed' => [
                    '@type' => $data['itemType'] ?? 'Product',
                    'name'  => sanitizeString($data['itemName'] ?? ''),
                ],
                'author' => parseAuthor($data['author'] ?? ''),
                'reviewRating' => [
                    '@type'       => 'Rating',
                    'ratingValue' => (float) ($data['rating'] ?? 5),
                    'bestRating'  => (float) ($data['bestRating'] ?? 5),
                ],
                'reviewBody'   => sanitizeString($data['reviewBody'] ?? ''),
                'datePublished' => $data['datePublished'] ?? date('Y-m-d'),
            ]);

        case 'VideoObject':
            return array_merge($base, [
                'name'         => sanitizeString($data['name'] ?? ''),
                'description'  => sanitizeString($data['description'] ?? ''),
                'thumbnailUrl' => $data['thumbnailUrl'] ?? $data['image'] ?? '',
                'uploadDate'   => $data['uploadDate'] ?? date('Y-m-d'),
                'contentUrl'   => $data['contentUrl'] ?? '',
                'embedUrl'     => $data['embedUrl'] ?? '',
                'duration'     => $data['duration'] ?? '',
            ]);

        case 'WebSite':
            $website = array_merge($base, [
                'name'  => sanitizeString($data['name'] ?? ''),
                'url'   => $data['url'] ?? '',
            ]);
            if (!empty($data['searchAction'])) {
                $website['potentialAction'] = [
                    '@type'       => 'SearchAction',
                    'target'      => $data['searchAction']['target'] ?? '',
                    'query-input' => $data['searchAction']['query-input'] ?? 'required name=search_term_string',
                ];
            }
            return $website;

        case 'Service':
            return array_merge($base, [
                'name'        => sanitizeString($data['name'] ?? ''),
                'description' => sanitizeString($data['description'] ?? ''),
                'provider'    => [
                    '@type' => 'Organization',
                    'name'  => sanitizeString($data['provider'] ?? ''),
                ],
                'areaServed'  => $data['areaServed'] ?? '',
                'url'         => $data['url'] ?? '',
            ]);

        case 'BreadcrumbList':
            $list = $base;
            $list['itemListElement'] = [];
            $items = $data['items'] ?? [];
            foreach ($items as $i => $item) {
                $list['itemListElement'][] = [
                    '@type'    => 'ListItem',
                    'position' => $i + 1,
                    'name'     => sanitizeString($item['name'] ?? ''),
                    'item'     => $item['url'] ?? '',
                ];
            }
            return $list;

        default:
            return array_merge($base, $data);
    }
}

// ── Helper Functions ───────────────────────────────────────────

function parseAuthor($author): array
{
    if (is_string($author)) {
        return ['@type' => 'Person', 'name' => sanitizeString($author)];
    }
    if (is_array($author)) {
        return ['@type' => 'Person', 'name' => sanitizeString($author['name'] ?? '')];
    }
    return ['@type' => 'Person', 'name' => ''];
}

function validateSchemaMarkup(array $schema): array
{
    $errors = [];
    if (empty($schema['@context'])) {
        $errors[] = '@context fehlt (sollte https://schema.org sein)';
    } elseif ($schema['@context'] !== 'https://schema.org') {
        $errors[] = '@context sollte "https://schema.org" sein, gefunden: ' . $schema['@context'];
    }
    if (empty($schema['@type'])) {
        $errors[] = '@type fehlt';
    }
    $validTypes = [
        'Article', 'NewsArticle', 'BlogPosting', 'Product', 'LocalBusiness',
        'Person', 'Event', 'FAQPage', 'HowTo', 'Recipe', 'Review',
        'VideoObject', 'BreadcrumbList', 'WebSite', 'Service', 'Organization',
    ];
    $type = $schema['@type'] ?? '';
    if ($type && !in_array($type, $validTypes, true)) {
        $errors[] = 'Unbekannter @type: ' . $type;
    }
    return $errors;
}

function validateSchemaWarnings(array $schema): array
{
    $warnings = [];
    $type = $schema['@type'] ?? '';

    if (in_array($type, ['Article', 'NewsArticle', 'BlogPosting'], true)) {
        if (empty($schema['headline'])) {
            $warnings[] = 'headline empfohlen für ' . $type;
        }
        if (empty($schema['author'])) {
            $warnings[] = 'author empfohlen für ' . $type;
        }
        if (empty($schema['datePublished'])) {
            $warnings[] = 'datePublished empfohlen für ' . $type;
        }
        if (empty($schema['image'])) {
            $warnings[] = 'image empfohlen für ' . $type;
        }
    }

    if ($type === 'Product') {
        if (empty($schema['name'])) {
            $warnings[] = 'name ist erforderlich für Product';
        }
        if (empty($schema['offers'])) {
            $warnings[] = 'offers (Preis) empfohlen für Product';
        }
    }

    if ($type === 'FAQPage') {
        if (empty($schema['mainEntity'])) {
            $warnings[] = 'mainEntity (Fragen & Antworten) empfohlen für FAQPage';
        }
    }

    if ($type === 'LocalBusiness') {
        if (empty($schema['address'])) {
            $warnings[] = 'address empfohlen für LocalBusiness';
        }
        if (empty($schema['telephone'])) {
            $warnings[] = 'telephone empfohlen für LocalBusiness';
        }
    }

    return $warnings;
}

function sanitizeString(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function getInput(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?? [];
    }
    return $_POST;
}

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
