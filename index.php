<?php
/**
 * SEO Meta Generator Pro v3.0
 * Enhanced Web Interface — Dark Mode, Charts, Print View, Responsive
 */

require_once __DIR__ . '/src/Analyzer.php';
require_once __DIR__ . '/src/Generator.php';
require_once __DIR__ . '/src/Exporter.php';

use SEOMetaGen\Analyzer;
use SEOMetaGen\Generator;
use SEOMetaGen\Exporter;

$result = null;
$generated = null;
$error = null;
$url = $_POST['url'] ?? '';
$action = $_POST['action'] ?? 'analyze';

// Load modules
require_once __DIR__ . '/modules/SocialPreview/SocialPreview.php';
require_once __DIR__ . '/modules/KeywordResearch/KeywordResearch.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $url) {
    try {
        $analyzer = new Analyzer();
        $generator = new Generator();

        if ($action === 'analyze') {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $keywords = $_POST['keywords'] ?? '';
            $image = $_POST['image'] ?? '';
            $result = $analyzer->analyze($url, $title, $description, $keywords, $image);
        } elseif ($action === 'generate') {
            $data = [
                'url' => $url,
                'title' => $_POST['title'] ?? '',
                'site_name' => $_POST['site_name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'keywords' => $_POST['keywords'] ?? '',
                'image_url' => $_POST['image_url'] ?? '',
                'type' => $_POST['type'] ?? 'website',
                'locale' => $_POST['locale'] ?? 'de_DE',
                'author_name' => $_POST['author_name'] ?? '',
                'org_name' => $_POST['org_name'] ?? '',
                'twitter_handle' => $_POST['twitter_handle'] ?? '',
            ];
            $generated = $generator->generateAll($data);
        } elseif ($action === 'fetch_analyze') {
            $result = $analyzer->fetchUrl($url);
        }
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

/**
 * Helper: render a score bar inline
 */
function scoreBar(int $value): string {
    $color = $value >= 80 ? '#10b981' : ($value >= 50 ? '#f59e0b' : '#ef4444');
    return '<div class="score-bar"><div class="score-fill" style="width:' . $ value . '%;background:' . $color . '"></div></div>'
        . '<span class="score-value" style="color:' . $color . '">' . $value . '%</span>';
}
?>
<!DOCTYPE html>
<html lang="de" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Meta Generator Pro v3.0</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <!-- ── Header ─────────────────────────────────────────── -->
    <header class="site-header no-print">
        <div class="container">
            <div class="header-inner">
                <div class="site-title">
                    🔍 SEO Meta Generator Pro <span class="badge">v3.0</span>
                </div>
                <div class="header-actions">
                    <button id="printBtn" class="btn btn-outline btn-sm" title="Drucken / Print">🖨️ Drucken</button>
                    <button id="darkModeToggle" class="toggle-dark" title="Dark Mode umschalten"></button>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <!-- ── Hero ──────────────────────────────────────────── -->
        <section class="hero no-print">
            <h2>SEO Meta Tag-Analyse &amp; Generator</h2>
            <p>Analysiere und generiere perfekte Meta-Tags für jede URL — mit Score-Visualisierung, Charts und Social Previews.</p>
        </section>

        <!-- ── Tabs ──────────────────────────────────────────── -->
        <div class="tabs no-print">
            <div class="tab-buttons">
                <button class="tab-btn active" data-tab="analyze">🔍 Analysieren</button>
                <button class="tab-btn" data-tab="generate">✨ Generieren</button>
                <button class="tab-btn" data-tab="batch">📦 Batch</button>
                <button class="tab-btn" data-tab="history">📜 Verlauf</button>
            </div>
        </div>

        <!-- ── Analyze Tab ───────────────────────────────────── -->
        <div id="tab-analyze" class="tab-content active">
            <div class="input-card">
                <form method="POST" id="analyzeForm">
                    <div class="grid-2 mb-2">
                        <div>
                            <label class="field-label" for="an_url">URL</label>
                            <input type="url" id="an_url" name="url" class="input-field"
                                   value="<?= htmlspecialchars($url) ?>"
                                   placeholder="https://example.com" required>
                        </div>
                        <div>
                            <label class="field-label" for="an_title">Meta Title</label>
                            <input type="text" id="an_title" name="title" class="input-field"
                                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                                   placeholder="Page Title">
                        </div>
                        <div>
                            <label class="field-label" for="an_desc">Meta Description</label>
                            <textarea id="an_desc" name="description" class="textarea-field"
                                      placeholder="Page description..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="field-label" for="an_keywords">Keywords</label>
                            <input type="text" id="an_keywords" name="keywords" class="input-field"
                                   value="<?= htmlspecialchars($_POST['keywords'] ?? '') ?>"
                                   placeholder="keyword1, keyword2, ...">
                            <label class="field-label mt-1" for="an_image">OG Image URL</label>
                            <input type="url" id="an_image" name="image" class="input-field"
                                   value="<?= htmlspecialchars($_POST['image'] ?? '') ?>"
                                   placeholder="https://example.com/image.jpg">
                        </div>
                    </div>
                    <input type="hidden" name="action" value="analyze">
                    <div class="btn-group mt-2">
                        <button type="submit" class="btn btn-success">🔍 Analysieren</button>
                        <button type="submit" class="btn btn-outline" formaction="" onclick="document.getElementById('an_url').closest('form').querySelector('[name=action]').value='fetch_analyze'">🌐 URL abrufen & analysieren</button>
                    </div>
                </form>
            </div>

            <div id="resultArea">
                <?php if ($error): ?>
                    <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($result): ?>
                    <?php include __DIR__ . '/templates/result-card.php'; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Generate Tab ──────────────────────────────────── -->
        <div id="tab-generate" class="tab-content">
            <div class="input-card">
                <form method="POST" id="generateForm">
                    <input type="hidden" name="action" value="generate">
                    <div class="grid-2 mb-2">
                        <div>
                            <label class="field-label" for="gen_url">URL / Canonical</label>
                            <input type="url" id="gen_url" name="url" class="input-field"
                                   placeholder="https://example.com" required>
                        </div>
                        <div>
                            <label class="field-label" for="gen_title">Seitentitel</label>
                            <input type="text" id="gen_title" name="title" class="input-field"
                                   placeholder="Meine tolle Seite">
                        </div>
                        <div>
                            <label class="field-label" for="gen_site">Site-Name</label>
                            <input type="text" id="gen_site" name="site_name" class="input-field"
                                   placeholder="Meine Website">
                        </div>
                        <div>
                            <label class="field-label" for="gen_desc">Beschreibung</label>
                            <textarea id="gen_desc" name="description" class="textarea-field"
                                      placeholder="Seitenbeschreibung..."></textarea>
                        </div>
                        <div>
                            <label class="field-label" for="gen_keywords">Keywords</label>
                            <input type="text" id="gen_keywords" name="keywords" class="input-field"
                                   placeholder="keyword1, keyword2">
                            <label class="field-label mt-1" for="gen_image">OG Image URL</label>
                            <input type="url" id="gen_image" name="image_url" class="input-field"
                                   placeholder="https://example.com/og.jpg">
                        </div>
                        <div>
                            <label class="field-label" for="gen_type">Typ</label>
                            <select id="gen_type" name="type" class="input-field">
                                <option value="website">Website</option>
                                <option value="article">Article</option>
                                <option value="product">Product</option>
                                <option value="profile">Profile</option>
                            </select>
                            <label class="field-label mt-1" for="gen_locale">Locale</label>
                            <input type="text" id="gen_locale" name="locale" class="input-field"
                                   value="de_DE" placeholder="de_DE">
                        </div>
                        <div>
                            <label class="field-label" for="gen_author">Autor</label>
                            <input type="text" id="gen_author" name="author_name" class="input-field"
                                   placeholder="Max Mustermann">
                            <label class="field-label mt-1" for="gen_org">Organisation</label>
                            <input type="text" id="gen_org" name="org_name" class="input-field"
                                   placeholder="Muster GmbH">
                        </div>
                        <div>
                            <label class="field-label" for="gen_twitter">Twitter Handle</label>
                            <input type="text" id="gen_twitter" name="twitter_handle" class="input-field"
                                   placeholder="@meinhandle">
                        </div>
                    </div>
                    <div class="btn-group mt-2">
                        <button type="submit" class="btn btn-success">✨ Meta Tags generieren</button>
                    </div>
                </form>
            </div>

            <div id="generateResult">
                <?php if ($generated): ?>
                    <div class="result-card">
                        <h4>✅ Generierte Meta Tags</h4>
                        <div class="meta-preview">
                            <div class="meta-item"><strong>Title</strong><?= htmlspecialchars($generated['title']) ?></div>
                            <div class="meta-item"><strong>Description</strong><?= htmlspecialchars($generated['description']) ?></div>
                            <div class="meta-item"><strong>Keywords</strong><?= htmlspecialchars($generated['keywords']) ?></div>
                            <div class="meta-item"><strong>Robots</strong><?= htmlspecialchars($generated['robots']) ?></div>
                            <div class="meta-item"><strong>Canonical</strong><?= htmlspecialchars($generated['canonical']) ?></div>
                        </div>
                        <h4 class="mt-2">📄 HTML Output</h4>
                        <?php
                            $generator = new Generator();
                            $html = $generator->renderHtml($generated);
                        ?>
                        <pre class="code-block" id="generatedHtmlCode"><?= htmlspecialchars($html) ?></pre>
                        <div class="btn-group mt-1">
                            <button class="btn btn-sm btn-outline" onclick="copyGeneratedHtml()">📋 HTML kopieren</button>
                            <a href="data:text/html;charset=utf-8,<?= rawurlencode($html) ?>" download="meta-tags.html" class="btn btn-sm btn-outline">💾 HTML herunterladen</a>
                        </div>
                    </div>

                    <!-- Social Preview -->
                    <?php
                        $sp = new \SEOMetaGen\Modules\SocialPreview\SocialPreview();
                        $previews = $sp->generatePreviews($generated);
                    ?>
                    <div class="result-card">
                        <h4>📱 Social Media Vorschau</h4>
                        <div class="compare-grid">
                            <?php foreach ($previews as $platform => $preview): ?>
                                <div class="compare-card">
                                    <div class="compare-header">
                                        <span><?= $preview['icon'] ?></span>
                                        <span style="font-weight:600"><?= $preview['platform'] ?></span>
                                        <span class="text-muted" style="font-size:.75rem"><?= $preview['card_type'] ?></span>
                                    </div>
                                    <?php if (!empty($preview['image'])): ?>
                                        <div style="height:150px;overflow:hidden;border-radius:8px;margin:.5rem 0;background:var(--bg-tertiary);">
                                            <img src="<?= htmlspecialchars($preview['image']) ?>" alt=""
                                                 style="width:100%;height:100%;object-fit:cover"
                                                 onerror="this.style.display='none'">
                                        </div>
                                    <?php endif; ?>
                                    <div class="compare-url"><?= htmlspecialchars($preview['url']) ?></div>
                                    <div class="compare-title" style="font-weight:600"><?= htmlspecialchars($preview['title']) ?></div>
                                    <div class="compare-desc"><?= htmlspecialchars($preview['description']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Batch Tab ─────────────────────────────────────── -->
        <div id="tab-batch" class="tab-content">
            <div class="input-card">
                <h4>📦 Batch-Analyse (mehrere URLs)</h4>
                <p class="text-muted mb-1">Eine URL pro Zeile. Format: <code>URL | Title | Description | Keywords | Image</code></p>
                <textarea id="bulkInput" class="textarea-field" rows="6"
                          placeholder="https://example.com|Example Title|Description here|kw1, kw2|https://example.com/img.jpg&#10;&#10;https://another.com|Another Title|Another desc|kw3, kw4|"></textarea>
                <div class="btn-group mt-1">
                    <button id="bulkAnalyzeBtn" class="btn btn-success">📊 Batch starten</button>
                </div>
            </div>
            <div id="bulkResult"></div>
        </div>

        <!-- ── History Tab ───────────────────────────────────── -->
        <div id="tab-history" class="tab-content">
            <div class="input-card">
                <div class="flex-between" style="flex-wrap:wrap;gap:.5rem">
                    <h4 style="margin:0">📜 Analyse-Verlauf</h4>
                    <button id="clearHistoryBtn" class="btn btn-sm btn-outline">🗑️ Verlauf löschen</button>
                </div>
                <div id="historyList">
                    <p class="text-muted">Lade Verlauf...</p>
                </div>
            </div>

            <!-- Database History (SQLite) -->
            <?php
            $dbHistory = [];
            $dbPath = __DIR__ . '/data/history.sqlite';
            if (file_exists($dbPath)) {
                try {
                    $db = new PDO('sqlite:' . $dbPath);
                    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $stmt = $db->query('SELECT * FROM analysis_history ORDER BY created_at DESC LIMIT 50');
                    $dbHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    // Silently ignore DB errors in UI
                }
            }
            ?>
            <?php if (!empty($dbHistory)): ?>
            <div class="result-card">
                <h4>🗄️ Datenbank-Verlauf</h4>
                <table class="data-table">
                    <thead><tr><th>Datum</th><th>URL</th><th>Title</th><th>Score</th><th>Grade</th></tr></thead>
                    <tbody>
                    <?php foreach ($dbHistory as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['created_at'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['url'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['title'] ?? '') ?></td>
                            <td><?= (int)($row['overall_score'] ?? 0) ?>%</td>
                            <td><span class="badge grade-<?= strtolower($row['grade'] ?? 'f') ?>"><?= htmlspecialchars($row['grade'] ?? '-') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Export Buttons ────────────────────────────────── -->
        <?php if ($result || $generated): ?>
        <div class="no-print mt-1" id="exportButtons">
            <div class="btn-group">
                <button class="export-btn btn btn-outline btn-sm" data-format="json">📄 Export JSON</button>
                <button class="export-btn btn btn-outline btn-sm" data-format="csv">📊 Export CSV</button>
                <button class="export-btn btn btn-outline btn-sm" data-format="html">🌐 Export HTML</button>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- ── Footer ─────────────────────────────────────────── -->
    <footer class="site-footer">
        <p>SEO Meta Generator Pro v3.0 — OWL Digital Factory — <a href="https://github.com/CreativeCodingSolutions/seo-meta-generator-pro" target="_blank">GitHub</a></p>
    </footer>

    <!-- ── JS Data ────────────────────────────────────────── -->
    <script>
    window.__seoMetaResult = <?= $result ? json_encode($result, JSON_HEX_TAG | JSON_HEX_APOS) : 'null' ?>;
    window.__seoMetaGenerated = <?= $generated ? json_encode($generated, JSON_HEX_TAG | JSON_HEX_APOS) : 'null' ?>;
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
