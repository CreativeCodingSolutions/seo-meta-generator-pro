<?php
/**
 * SEO Meta Generator Pro v2.0
 * Web Interface Entry Point
 */

require_once __DIR__ . '/src/Analyzer.php';
require_once __DIR__ . '/src/Generator.php';
require_once __DIR__ . '/src/Exporter.php';

use SEOMetaGen\Analyzer;
use SEOMetaGen\Generator;
use SEOMetaGen\Exporter;

$result = null;
$error = null;
$url = $_POST['url'] ?? '';
$action = $_POST['action'] ?? 'analyze';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $url) {
    try {
        $analyzer = new Analyzer();
        $generator = new Generator();
        
        if ($action === 'analyze') {
            $result = $analyzer->analyze($url);
        } elseif ($action === 'generate') {
            $analysis = $analyzer->analyze($url);
            $result = $generator->generate($analysis);
        }
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Meta Generator Pro v2.0</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 SEO Meta Generator Pro <span class="badge">v2.0</span></h1>
            <p>Analyze and generate perfect meta tags for any URL</p>
        </header>

        <form method="POST" class="main-form">
            <div class="input-group">
                <input type="url" name="url" value="<?= htmlspecialchars($url) ?>" 
                       placeholder="Enter URL to analyze (e.g. https://example.com)" required>
                <select name="action">
                    <option value="analyze" <?= $action === 'analyze' ? 'selected' : '' ?>>Analyze</option>
                    <option value="generate" <?= $action === 'generate' ? 'selected' : '' ?>>Generate Meta Tags</option>
                </select>
                <button type="submit">Analyze</button>
            </div>
        </form>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="results">
                <h2>Results</h2>
                <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) ?></pre>
            </div>
        <?php endif; ?>

        <footer>
            <p>SEO Meta Generator Pro v2.0 — OWL Digital Factory</p>
        </footer>
    </div>
</body>
</html>
