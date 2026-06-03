<?php
/**
 * SEO Meta Generator Pro — Result Card Template
 * Reusable component for displaying analysis results
 * 
 * Expected variables:
 *   $result  array  — Analyzer result with keys: url, title, description, keywords, image,
 *                      scores, overall_score, suggestions, grade, analyzed_at
 *   $generator object|null — optional Generator instance
 */

if (!isset($result) || !is_array($result)) return;

$gradeClass = strtolower($result['grade'] ?? 'f');
$score = $result['overall_score'] ?? 0;
$scores = $result['scores'] ?? [];
$suggestions = $result['suggestions'] ?? [];

function scoreColor(int $val): string {
    return $val >= 80 ? '#10b981' : ($val >= 50 ? '#f59e0b' : '#ef4444');
}
?>

<div class="result-card" id="analysisResultCard">
    <div class="score-overview">
        <!-- Score Circle -->
        <div class="score-circle grade-<?= $gradeClass ?>"><?= htmlspecialchars($result['grade'] ?? '-') ?></div>
        <div class="score-info">
            <div class="score-number"><?= $score ?>%</div>
            <div class="score-label-main">SEO Score</div>
            <div class="text-muted" style="font-size:.8rem"><?= htmlspecialchars($result['analyzed_at'] ?? date('Y-m-d H:i:s')) ?></div>
        </div>
    </div>

    <!-- ── Charts ────────────────────────────────────────── -->
    <div class="grid-2 mb-2">
        <div>
            <h4>📊 Score-Aufschlüsselung</h4>
            <div class="scores-grid">
                <?php foreach ($scores as $key => $val): ?>
                    <div class="score-item">
                        <span class="score-label"><?= htmlspecialchars($key) ?></span>
                        <?= scoreBar((int)$val) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <h4>📈 Chart-Visualisierung</h4>
            <div class="chart-container">
                <canvas id="seoScoreChart"
                        data-labels='<?= json_encode(array_keys($scores)) ?>'
                        data-values='<?= json_encode(array_values($scores)) ?>'>
                </canvas>
            </div>
        </div>
    </div>

    <!-- Length Chart -->
    <div class="mb-2">
        <h4>📏 Längen-Analyse</h4>
        <div class="chart-container" style="max-width:700px">
            <canvas id="seoLengthChart"
                    data-title-len="<?= (int)mb_strlen($result['title'] ?? '') ?>"
                    data-desc-len="<?= (int)mb_strlen($result['description'] ?? '') ?>"
                    data-title-max="60"
                    data-desc-max="160">
            </canvas>
        </div>
    </div>

    <!-- Keyword Density Chart -->
    <?php if (!empty($result['title']) || !empty($result['description'])): ?>
    <?php
        $analyzer = new \SEOMetaGen\Analyzer();
        $combinedText = trim(($result['title'] ?? '') . ' ' . ($result['description'] ?? '') . ' ' . ($result['keywords'] ?? ''));
        $kwData = $analyzer->extractKeywords($combinedText, 10);
    ?>
    <?php if (!empty($kwData)): ?>
    <div class="mb-2">
        <h4>🔑 Keyword-Dichte</h4>
        <div class="chart-container" style="max-width:600px">
            <canvas id="seoKeywordChart"
                    data-keywords='<?= json_encode(array_column($kwData, 'word')) ?>'
                    data-densities='<?= json_encode(array_column($kwData, 'density')) ?>'>
            </canvas>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- ── Meta Details ──────────────────────────────────── -->
    <h4 class="mt-2">📋 Meta Details</h4>
    <div class="meta-preview">
        <div class="meta-item"><strong>URL</strong><?= htmlspecialchars($result['url'] ?? '') ?></div>
        <div class="meta-item">
            <strong>Title (<?= mb_strlen($result['title'] ?? '') ?> Zeichen)</strong>
            <?= htmlspecialchars($result['title'] ?? '—') ?>
        </div>
        <div class="meta-item">
            <strong>Description (<?= mb_strlen($result['description'] ?? '') ?> Zeichen)</strong>
            <?= htmlspecialchars($result['description'] ?? '—') ?>
        </div>
        <?php if (!empty($result['keywords'])): ?>
            <div class="meta-item"><strong>Keywords</strong><?= htmlspecialchars($result['keywords']) ?></div>
        <?php endif; ?>
        <?php if (!empty($result['image'])): ?>
            <div class="meta-item"><strong>OG Image</strong><?= htmlspecialchars($result['image']) ?></div>
        <?php endif; ?>
    </div>

    <!-- ── Suggestions ───────────────────────────────────── -->
    <h4 class="mt-2">💡 Empfehlungen</h4>
    <ul class="suggestions-list">
        <?php foreach ($suggestions as $s): ?>
            <li><?= htmlspecialchars($s) ?></li>
        <?php endforeach; ?>
    </ul>

    <!-- ── Google Preview ────────────────────────────────── -->
    <div class="result-card mt-2" style="background:var(--bg-tertiary)">
        <h4>🔎 Google Suchergebnis-Vorschau</h4>
        <div style="font-family:Arial,sans-serif;max-width:600px">
            <div style="font-size:18px;color:#1a0dab;line-height:24px;font-weight:400;word-break:break-word">
                <?= htmlspecialchars(mb_substr($result['title'] ?? '', 0, 70)) ?>
            </div>
            <div style="font-size:14px;color:#006621;margin:2px 0;word-break:break-all">
                <?= htmlspecialchars($result['url'] ?? '') ?>
            </div>
            <div style="font-size:13px;color:#545454;line-height:20px;word-break:break-word">
                <?= htmlspecialchars(mb_substr($result['description'] ?? '', 0, 165)) ?>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Score Bar helper (inline for template isolation)
 */
function scoreBar(int $value): string {
    $color = $value >= 80 ? '#10b981' : ($value >= 50 ? '#f59e0b' : '#ef4444');
    return '<div class="score-bar"><div class="score-fill" style="width:' . $value . '%;background:' . $color . '"></div></div>'
        . '<span class="score-value" style="color:' . $color . '">' . $value . '%</span>';
}
?>
