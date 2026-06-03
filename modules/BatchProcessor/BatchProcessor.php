<?php
/**
 * SEO Meta Generator Pro — BatchProcessor Module
 * 
 * Analyzes multiple URLs at once. Supports:
 * - Web interface (via HTTP POST)
 * - CLI usage (php process.php --file=urls.txt)
 * - API endpoint (action=batch)
 * - SQLite history storage
 * 
 * Input format (pipe-separated per line):
 *   URL | Title | Description | Keywords | Image
 * 
 * @package SEOMetaGen\Modules\BatchProcessor
 * @version 3.0.0
 */

namespace SEOMetaGen\Modules\BatchProcessor;

require_once __DIR__ . '/../../src/Analyzer.php';
require_once __DIR__ . '/../../src/Generator.php';
require_once __DIR__ . '/../../src/Exporter.php';

use SEOMetaGen\Analyzer;
use SEOMetaGen\Exporter;

class BatchProcessor
{
    private Analyzer $analyzer;
    private Exporter $exporter;
    private string $dbPath;
    private ?\PDO $db = null;

    public function __construct()
    {
        $this->analyzer = new Analyzer();
        $this->exporter = new Exporter();
        $this->dbPath = dirname(__DIR__, 2) . '/data/history.sqlite';
        $this->initDb();
    }

    /**
     * Parse pipe-separated input lines into items array.
     * Format per line: URL | Title | Description | Keywords | Image
     */
    public function parseInput(string $text): array
    {
        $items = [];
        $lines = preg_split('/\r\n|\n|\r/', $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $items[] = [
                'url'         => $parts[0] ?? '',
                'title'       => $parts[1] ?? '',
                'description' => $parts[2] ?? '',
                'keywords'    => $parts[3] ?? '',
                'image'       => $parts[4] ?? '',
            ];
        }

        return $items;
    }

    /**
     * Parse a CSV file into items array.
     */
    public function parseCsvFile(string $filepath): array
    {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw new \RuntimeException("File not found or not readable: {$filepath}");
        }

        $items = [];
        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$filepath}");
        }

        // Detect delimiter
        $sample = fread($handle, 1024);
        rewind($handle);
        $delimiter = str_contains($sample, ';') ? ';' : ',';
        if (str_contains($sample, "\t")) $delimiter = "\t";

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            throw new \RuntimeException("Empty CSV file");
        }

        $header = array_map('strtolower', array_map('trim', $header));

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) < 1) continue;
            $data = [];
            foreach ($header as $i => $col) {
                $data[$col] = trim($row[$i] ?? '');
            }
            $items[] = [
                'url'         => $data['url'] ?? $data['link'] ?? $data['address'] ?? '',
                'title'       => $data['title'] ?? $data['titel'] ?? '',
                'description' => $data['description'] ?? $data['beschreibung'] ?? '',
                'keywords'    => $data['keywords'] ?? $data['schlüsselwörter'] ?? '',
                'image'       => $data['image'] ?? $data['bild'] ?? $data['img'] ?? '',
            ];
        }
        fclose($handle);

        return $items;
    }

    /**
     * Process a batch of items and return results.
     */
    public function process(array $items): array
    {
        $results = [];
        $total = count($items);

        foreach ($items as $i => $item) {
            try {
                $result = $this->analyzer->analyze(
                    $item['url'] ?? '',
                    $item['title'] ?? '',
                    $item['description'] ?? '',
                    $item['keywords'] ?? '',
                    $item['image'] ?? ''
                );
                $result['_batch_index'] = $i;
                $result['_batch_status'] = 'ok';
                $result['_batch_url'] = $item['url'];

                // Save to SQLite
                $this->saveToHistory($result);

                $results[] = $result;
            } catch (\Exception $e) {
                $results[] = [
                    'url'           => $item['url'] ?? '',
                    'title'         => $item['title'] ?? '',
                    'description'   => '',
                    'keywords'      => '',
                    'image'         => '',
                    'scores'        => [],
                    'overall_score' => 0,
                    'suggestions'   => ['❌ Error: ' . $e->getMessage()],
                    'grade'         => 'F',
                    'analyzed_at'   => date('Y-m-d H:i:s'),
                    '_batch_index'  => $i,
                    '_batch_status' => 'error',
                    '_batch_error'  => $e->getMessage(),
                ];
            }
        }

        $avgScore = empty($results) ? 0 : (int) round(
            array_sum(array_column($results, 'overall_score')) / count($results)
        );

        return [
            'results'       => $results,
            'total'         => $total,
            'successful'    => count(array_filter($results, fn($r) => ($r['_batch_status'] ?? '') === 'ok')),
            'failed'        => count(array_filter($results, fn($r) => ($r['_batch_status'] ?? '') === 'error')),
            'average_score' => $avgGrade,
            'processed_at'  => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Export batch results to various formats.
     */
    public function export(array $batchResult, string $format = 'json'): string
    {
        $results = $batchResult['results'] ?? [];

        switch (strtolower($format)) {
            case 'csv':
                return $this->exporter->exportCsv($results);
            case 'html':
                $html = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Batch SEO Report</title>';
                $html .= '<style>body{font-family:sans-serif;padding:2rem;background:#0f172a;color:#e2e8f0;line-height:1.6}';
                $html .= 'table{width:100%;border-collapse:collapse;margin:1rem 0}th,td{padding:.6rem .75rem;border:1px solid #334155;text-align:left}';
                $html .= 'th{background:#1e293b}h1,h2,h3{color:#818cf8}.summary{background:#1e293b;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem}';
                $html .= '.grade-a{color:#10b981}.grade-b{color:#3b82f6}.grade-c{color:#f59e0b}.grade-d{color:#ef4444}.grade-f{color:#7f1d1d}</style></head><body>';
                $html .= '<h1>🔍 Batch SEO Meta Report</h1>';
                $html .= '<div class="summary">';
                $html .= '<p><strong>Gesamt:</strong> ' . ($batchResult['total'] ?? 0) . ' URLs</p>';
                $html .= '<p><strong>Erfolgreich:</strong> ' . ($batchResult['successful'] ?? 0) . '</p>';
                $html .= '<p><strong>Fehler:</strong> ' . ($batchResult['failed'] ?? 0) . '</p>';
                $html .= '<p><strong>Ø Score:</strong> ' . ($batchResult['average_score'] ?? 0) . '%</p>';
                $html .= '<p><strong>Datum:</strong> ' . ($batchResult['processed_at'] ?? '') . '</p>';
                $html .= '</div>';
                $html .= '<h2>Ergebnisse</h2>';
                $html .= '<table><thead><tr><th>#</th><th>URL</th><th>Title</th><th>Score</th><th>Grade</th><th>Status</th></tr></thead><tbody>';
                foreach ($results as $i => $r) {
                    $gradeCls = strtolower($r['grade'] ?? 'f');
                    $status = ($r['_batch_status'] ?? '') === 'error' ? '❌ ' . ($r['_batch_error'] ?? 'Error') : '✅ OK';
                    $html .= '<tr>';
                    $html .= '<td>' . ($i + 1) . '</td>';
                    $html .= '<td>' . htmlspecialchars($r['url'] ?? '') . '</td>';
                    $html .= '<td>' . htmlspecialchars($r['title'] ?? '') . '</td>';
                    $html .= '<td>' . ($r['overall_score'] ?? 0) . '%</td>';
                    $html .= '<td><span class="grade-' . $gradeCls . '">' . htmlspecialchars($r['grade'] ?? '-') . '</span></td>';
                    $html .= '<td>' . htmlspecialchars($status) . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table></body></html>';
                return $html;

            case 'json':
            default:
                return json_encode($batchResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Get batch processing statistics.
     */
    public function getStats(): array
    {
        $stats = [
            'total_analyzed' => 0,
            'average_score'  => 0,
            'grade_distribution' => ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0],
            'last_batch'     => null,
        ];

        if (!$this->db) return $stats;

        try {
            $stmt = $this->db->query('SELECT COUNT(*) FROM analysis_history');
            $stats['total_analyzed'] = (int) $stmt->fetchColumn();

            $stmt = $this->db->query('SELECT AVG(overall_score) FROM analysis_history');
            $stats['average_score'] = (int) round((float) $stmt->fetchColumn());

            $stmt = $this->db->query('SELECT grade, COUNT(*) as cnt FROM analysis_history GROUP BY grade');
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $stats['grade_distribution'][$row['grade']] = (int) $row['cnt'];
            }

            $stmt = $this->db->query('SELECT MAX(created_at) FROM analysis_history');
            $stats['last_batch'] = $stmt->fetchColumn();
        } catch (\Exception $e) {
            // Return defaults
        }

        return $stats;
    }

    // ── Private: Database ──────────────────────────────────────

    private function initDb(): void
    {
        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        try {
            $this->db = new \PDO('sqlite:' . $this->dbPath);
            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            $this->db->exec('
                CREATE TABLE IF NOT EXISTS analysis_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    url TEXT NOT NULL DEFAULT "",
                    title TEXT NOT NULL DEFAULT "",
                    description TEXT NOT NULL DEFAULT "",
                    keywords TEXT NOT NULL DEFAULT "",
                    image TEXT NOT NULL DEFAULT "",
                    overall_score INTEGER DEFAULT 0,
                    grade TEXT DEFAULT "F",
                    scores_json TEXT DEFAULT "{}",
                    suggestions_json TEXT DEFAULT "[]",
                    source TEXT DEFAULT "single",
                    created_at TEXT NOT NULL
                )
            ');
            $this->db->exec('CREATE INDEX IF NOT EXISTS idx_history_url ON analysis_history(url)');
            $this->db->exec('CREATE INDEX IF NOT EXISTS idx_history_created ON analysis_history(created_at)');
        } catch (\Exception $e) {
            $this->db = null;
        }
    }

    private function saveToHistory(array $result): void
    {
        if (!$this->db) return;

        try {
            $stmt = $this->db->prepare('
                INSERT INTO analysis_history (url, title, description, keywords, image, overall_score, grade, scores_json, suggestions_json, source, created_at)
                VALUES (:url, :title, :desc, :kw, :img, :score, :grade, :scores, :sugg, :src, :created)
            ');
            $stmt->execute([
                ':url'     => substr($result['url'] ?? '', 0, 2048),
                ':title'   => substr($result['title'] ?? '', 0, 512),
                ':desc'    => substr($result['description'] ?? '', 0, 1024),
                ':kw'      => substr($result['keywords'] ?? '', 0, 1024),
                ':img'     => substr($result['image'] ?? '', 0, 2048),
                ':score'   => $result['overall_score'] ?? 0,
                ':grade'   => $result['grade'] ?? 'F',
                ':scores'  => json_encode($result['scores'] ?? []),
                ':sugg'    => json_encode($result['suggestions'] ?? []),
                ':src'     => 'batch',
                ':created' => $result['analyzed_at'] ?? date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Silently fail DB writes
        }
    }
}
