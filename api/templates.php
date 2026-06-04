<?php
/**
 * SEO Meta Generator Pro — Meta Templates API Endpoint
 *
 * CRUD for reusable meta templates.
 *
 * Endpoints:
 *   GET    /api/templates.php              — List all templates
 *   GET    /api/templates.php?id=<id>       — Get single template
 *   POST   /api/templates.php              — Create template
 *   PUT    /api/templates.php?id=<id>       — Update template
 *   DELETE /api/templates.php?id=<id>       — Delete template
 *   POST   /api/templates.php?action=apply — Apply template to data
 *
 * @package SEO Meta Generator Pro
 * @version 3.2.0
 */

// ── Security Headers ──────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('X-RateLimit-Limit: 100');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Configuration ─────────────────────────────────────────────
$templatesDir = dirname(__DIR__) . '/data/templates';
if (!is_dir($templatesDir)) {
    @mkdir($templatesDir, 0755, true);
}

$method = $_SERVER['REQUEST_METHOD'];

// ── Helpers ───────────────────────────────────────────────────

function sanitizeId(string $id): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
}

function templatePath(string $dir, string $id): string
{
    return $dir . '/' . sanitizeId($id) . '.json';
}

function loadTemplate(string $dir, string $id): ?array
{
    $path = templatePath($dir, $id);
    if (!file_exists($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        return null;
    }
    return $data;
}

function saveTemplate(string $dir, string $id, array $data): bool
{
    $path = templatePath($dir, $id);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

function deleteTemplate(string $dir, string $id): bool
{
    $path = templatePath($dir, $id);
    if (file_exists($path)) {
        return @unlink($path);
    }
    return true;
}

function listTemplates(string $dir): array
{
    $templates = [];
    foreach (glob($dir . '/*.json') as $file) {
        $data = @json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            $data['_id'] = basename($file, '.json');
            $data['_file_size'] = filesize($file);
            $data['_updated_at'] = date('Y-m-d H:i:s', filemtime($file));
            $templates[] = $data;
        }
    }
    // Sort by updated_at descending
    usort($templates, fn($a, $b) => strcmp($b['_updated_at'] ?? '', $a['_updated_at'] ?? ''));
    return $templates;
}

function generateTemplateId(string $name): string
{
    $id = strtolower(trim($name));
    $id = preg_replace('/[^a-zA-Z0-9]+/', '-', $id);
    $id = trim($id, '-');
    if (empty($id)) {
        $id = 'template-' . date('Ymd-His');
    }
    return $id;
}

function validateTemplateInput(array $input, bool $requireName = true): array
{
    $errors = [];

    if ($requireName && empty(trim($input['name'] ?? ''))) {
        $errors[] = 'Template name is required.';
    }

    if (isset($input['title_pattern']) && strlen($input['title_pattern']) > 500) {
        $errors[] = 'Title pattern must be 500 characters or less.';
    }

    if (isset($input['description_pattern']) && strlen($input['description_pattern']) > 1000) {
        $errors[] = 'Description pattern must be 1000 characters or less.';
    }

    if (isset($input['keywords']) && is_array($input['keywords']) && count($input['keywords']) > 50) {
        $errors[] = 'Maximum 50 keywords allowed.';
    }

    return $errors;
}

// ── Apply template placeholders ───────────────────────────────

function applyPlaceholders(string $pattern, array $data): string
{
    $replacements = [
        '{title}'       => $data['title'] ?? '',
        '{description}' => $data['description'] ?? '',
        '{keywords}'    => is_array($data['keywords'] ?? null) ? implode(', ', $data['keywords']) : ($data['keywords'] ?? ''),
        '{url}'         => $data['url'] ?? '',
        '{site_name}'   => $data['site_name'] ?? '',
        '{author}'      => $data['author_name'] ?? $data['author'] ?? '',
        '{locale}'      => $data['locale'] ?? 'en_US',
        '{type}'        => $data['type'] ?? 'website',
        '{date}'        => date('Y-m-d'),
        '{year}'        => date('Y'),
    ];

    // Support custom placeholders: {custom_key}
    foreach ($data as $key => $value) {
        if (is_scalar($value) && !isset($replacements['{' . $key . '}'])) {
            $replacements['{' . $key . '}'] = (string)$value;
        }
    }

    return str_replace(array_keys($replacements), array_values($replacements), $pattern);
}

// ── Route ─────────────────────────────────────────────────────

// Support method override via POST with _method
if ($method === 'POST' && isset($_GET['_method'])) {
    $override = strtoupper($_GET['_method']);
    if (in_array($override, ['PUT', 'DELETE'], true)) {
        $method = $override;
    }
}

// Also support X-HTTP-Method-Override header
if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $override = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    if (in_array($override, ['PUT', 'DELETE'], true)) {
        $method = $override;
    }
}

try {
switch ($method) {

    // ── READ (List / Single) ───────────────────────────────────
    case 'GET': {
        header('Content-Type: application/json; charset=utf-8');

        $id = sanitizeId($_GET['id'] ?? '');
        if ($id) {
            $template = loadTemplate($templatesDir, $id);
            if (!$template) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => "Template '{$id}' not found."]);
                exit;
            }
            $template['_id'] = $id;
            echo json_encode(['success' => true, 'data' => $template], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $templates = listTemplates($templatesDir);
        echo json_encode([
            'success'  => true,
            'total'    => count($templates),
            'data'     => $templates,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── CREATE ─────────────────────────────────────────────────
    case 'POST': {
        header('Content-Type: application/json; charset=utf-8');

        // Special action: apply template
        $action = preg_replace('/[^a-zA-Z_-]/', '', $_GET['action'] ?? '');
        if ($action === 'apply') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            $templateId = sanitizeId($input['template_id'] ?? '');
            if (!$templateId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'template_id is required for apply action.']);
                exit;
            }

            $template = loadTemplate($templatesDir, $templateId);
            if (!$template) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => "Template '{$templateId}' not found."]);
                exit;
            }

            $data = $input['data'] ?? [];
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'data must be an object.']);
                exit;
            }

            $result = [
                'title'       => !empty($template['title_pattern']) ? applyPlaceholders($template['title_pattern'], $data) : ($data['title'] ?? ''),
                'description' => !empty($template['description_pattern']) ? applyPlaceholders($template['description_pattern'], $data) : ($data['description'] ?? ''),
                'keywords'    => is_array($template['keywords'] ?? null) ? implode(', ', $template['keywords']) : ($data['keywords'] ?? ''),
            ];

            // If keywords is a string in data, merge
            if (is_string($data['keywords'] ?? null) && !empty($data['keywords'])) {
                $result['keywords'] = $data['keywords'];
            }

            echo json_encode([
                'success'     => true,
                'template_id' => $templateId,
                'applied'     => $result,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Standard: create template
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $errors = validateTemplateInput($input, true);
        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'errors' => $errors], JSON_PRETTY_PRINT);
            exit;
        }

        $name = trim($input['name']);
        $id   = sanitizeId($input['id'] ?? '') ?: generateTemplateId($name);

        // Check if already exists
        if (file_exists(templatePath($templatesDir, $id))) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => "Template '{$id}' already exists. Use PUT to update."]);
            exit;
        }

        $template = [
            'name'               => $name,
            'title_pattern'      => trim($input['title_pattern'] ?? '{title} — {site_name}'),
            'description_pattern'=> trim($input['description_pattern'] ?? '{description}'),
            'keywords'           => [],
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        // Parse keywords
        if (isset($input['keywords'])) {
            if (is_array($input['keywords'])) {
                $template['keywords'] = array_values(array_filter(array_map('trim', $input['keywords'])));
            } elseif (is_string($input['keywords'])) {
                $template['keywords'] = array_values(array_filter(array_map('trim', explode(',', $input['keywords']))));
            }
        }

        // Optional fields
        if (isset($input['og_type'])) {
            $template['og_type'] = preg_replace('/[^a-zA-Z0-9_.-]/', '', $input['og_type']);
        }
        if (isset($input['robots'])) {
            $template['robots'] = preg_replace('/[^a-zA-Z, ]/', '', $input['robots']);
        }
        if (isset($input['extra_meta']) && is_array($input['extra_meta'])) {
            $template['extra_meta'] = $input['extra_meta'];
        }

        if (!saveTemplate($templatesDir, $id, $template)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save template. Check directory permissions.']);
            exit;
        }

        http_response_code(201);
        $template['_id'] = $id;
        echo json_encode([
            'success' => true,
            'message' => "Template '{$name}' created.",
            'id'      => $id,
            'data'    => $template,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── UPDATE ─────────────────────────────────────────────────
    case 'PUT': {
        header('Content-Type: application/json; charset=utf-8');

        $id = sanitizeId($_GET['id'] ?? '');
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Template id is required via ?id=<id>']);
            exit;
        }

        $existing = loadTemplate($templatesDir, $id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => "Template '{$id}' not found."]);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            parse_str(file_get_contents('php://input'), $input);
        }

        $errors = validateTemplateInput($input, false);
        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'errors' => $errors], JSON_PRETTY_PRINT);
            exit;
        }

        // Update fields
        if (isset($input['name'])) {
            $existing['name'] = trim($input['name']);
        }
        if (isset($input['title_pattern'])) {
            $existing['title_pattern'] = trim($input['title_pattern']);
        }
        if (isset($input['description_pattern'])) {
            $existing['description_pattern'] = trim($input['description_pattern']);
        }
        if (isset($input['keywords'])) {
            if (is_array($input['keywords'])) {
                $existing['keywords'] = array_values(array_filter(array_map('trim', $input['keywords'])));
            } elseif (is_string($input['keywords'])) {
                $existing['keywords'] = array_values(array_filter(array_map('trim', explode(',', $input['keywords']))));
            }
        }
        if (isset($input['og_type'])) {
            $existing['og_type'] = preg_replace('/[^a-zA-Z0-9_.-]/', '', $input['og_type']);
        }
        if (isset($input['robots'])) {
            $existing['robots'] = preg_replace('/[^a-zA-Z, ]/', '', $input['robots']);
        }
        if (isset($input['extra_meta']) && is_array($input['extra_meta'])) {
            $existing['extra_meta'] = $input['extra_meta'];
        }

        $existing['updated_at'] = date('Y-m-d H:i:s');

        if (!saveTemplate($templatesDir, $id, $existing)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update template.']);
            exit;
        }

        $existing['_id'] = $id;
        echo json_encode([
            'success' => true,
            'message' => "Template '{$id}' updated.",
            'data'    => $existing,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── DELETE ─────────────────────────────────────────────────
    case 'DELETE': {
        header('Content-Type: application/json; charset=utf-8');

        $id = sanitizeId($_GET['id'] ?? '');
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Template id is required via ?id=<id>']);
            exit;
        }

        if (!file_exists(templatePath($templatesDir, $id))) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => "Template '{$id}' not found."]);
            exit;
        }

        if (!deleteTemplate($templatesDir, $id)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete template.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => "Template '{$id}' deleted.",
        ], JSON_PRETTY_PRINT);
        exit;
    }

    default: {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'          => false,
            'error'            => 'Method not allowed.',
            'allowed_methods'  => ['GET', 'POST', 'PUT', 'DELETE'],
        ], JSON_PRETTY_PRINT);
        exit;
    }
}
} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'Internal error: ' . $e->getMessage(),
    ], JSON_PRETTY_PRINT);
    exit;
}
