<?php
/**
 * SEO Meta Generator Pro — History Module
 * 
 * Provides SQLite-based storage and retrieval of analysis history.
 * Used by index.php, API, and BatchProcessor.
 * 
 * @package SEOMetaGen\Modules\History
 * @version 3.0.0
 */

namespace SEOMetaGen\Modules\History;

class History
{
    private string $dbPath;
    private ?\PDO $db = null;

    public function __construct(?string $dbPath = null)
    {
        $this->dbPath = $dbPath ?? (dirname(__DIR__, 2) . '/data/history.sqlite');
        $this->initDb();
    }

    /**
     * Save an analysis result to the database.
     */
    public function save(array $result, string $source = 'single'): bool
    {
        if (!$this->db) return false;

        try {
            $stmt = $this->db->prepare('
                INSERT INTO analysis_history 
                (url, title, description, keywords, image, overall_score, grade, scores_json, suggestions_json, source, created_at)
                VALUES (:url, :title, :desc, :kw, :img, :score, :grade, :scores, :sugg, :src, :created)
            ');
            return $stmt->execute([
                ':url'     => substr($result['url'] ?? '', 0, 2048),
                ':title'   => substr($result['title'] ?? '', 0, 512),
                ':desc'    => substr($result['description'] ?? '', 0, 1024),
                ':kw'      => substr($result['keywords'] ?? '', 0, 1024),
                ':img'     => substr($result['image'] ?? '', 0, 2048),
                ':score'   => $result['overall_score'] ?? 0,
                ':grade'   => $result['grade'] ?? 'F',
                ':scores'  => json_encode($result['scores'] ?? []),
                ':sugg'    => json_encode($result['suggestions'] ?? []),
                ':src'     => $source,
                ':created' => $result['analyzed_at'] ?? date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get recent history entries.
     */
    public function getRecent(int $limit = 50, int $offset = 0): array
    {
        if (!$this->db) return [];

        try {
            $stmt = $this->db->prepare('SELECT * FROM analysis_history ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            return $this->hydrateRows($stmt->fetchAll());
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Search history by URL or title.
     */
    public function search(string $query, int $limit = 25): array
    {
        if (!$this->db) return [];

        try {
            $stmt = $this->db->prepare('
                SELECT * FROM analysis_history 
                WHERE url LIKE :q OR title LIKE :q OR keywords LIKE :q
                ORDER BY created_at DESC 
                LIMIT :limit
            ');
            $stmt->bindValue(':q', '%' . $query . '%');
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $this->hydrateRows($stmt->fetchAll());
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Delete a history entry by ID.
     */
    public function delete(int $id): bool
    {
        if (!$this->db) return false;

        try {
            $stmt = $this->db->prepare('DELETE FROM analysis_history WHERE id = :id');
            return $stmt->execute([':id' => $id]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clear all history.
     */
    public function clear(): bool
    {
        if (!$this->db) return false;

        try {
            return $this->db->exec('DELETE FROM analysis_history') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get total count of history entries.
     */
    public function count(): int
    {
        if (!$this->db) return 0;

        try {
            return (int) $this->db->query('SELECT COUNT(*) FROM analysis_history')->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get statistics summary.
     */
    public function getStats(): array
    {
        $stats = [
            'total'              => 0,
            'avg_score'          => 0,
            'grade_distribution' => ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0],
            'recent_count_7d'    => 0,
            'sources'            => [],
            'oldest_entry'       => null,
            'newest_entry'       => null,
        ];

        if (!$this->db) return $stats;

        try {
            $stats['total'] = $this->count();

            $stmt = $this->db->query('SELECT AVG(overall_score) FROM analysis_history');
            $stats['avg_score'] = (int) round((float) $stmt->fetchColumn());

            $stmt = $this->db->query('SELECT grade, COUNT(*) as cnt FROM analysis_history GROUP BY grade');
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $stats['grade_distribution'][$row['grade']] = (int) $row['cnt'];
            }

            $stmt = $this->db->query("SELECT COUNT(*) FROM analysis_history WHERE created_at >= datetime('now', '-7 days')");
            $stats['recent_count_7d'] = (int) $stmt->fetchColumn();

            $stmt = $this->db->query('SELECT source, COUNT(*) as cnt FROM analysis_history GROUP BY source');
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $stats['sources'][$row['source']] = (int) $row['cnt'];
            }

            $stmt = $this->db->query('SELECT MIN(created_at), MAX(created_at) FROM analysis_history');
            $row = $stmt->fetch(\PDO::FETCH_NUM);
            $stats['oldest_entry'] = $row[0];
            $stats['newest_entry'] = $row[1];
        } catch (\Exception $e) {
            // Return defaults
        }

        return $stats;
    }

    /**
     * Export history as CSV string.
     */
    public function exportCsv(int $limit = 500): string
    {
        $entries = $this->getRecent($limit);
        $lines = ['ID;URL;Title;Description;Keywords;Score;Grade;Source;Created At'];

        foreach ($entries as $e) {
            $lines[] = implode(';', [
                $e['id'] ?? '',
                csvEscape($e['url'] ?? ''),
                csvEscape($e['title'] ?? ''),
                csvEscape($e['description'] ?? ''),
                csvEscape($e['keywords'] ?? ''),
                $e['overall_score'] ?? 0,
                $e['grade'] ?? '-',
                $e['source'] ?? '',
                $e['created_at'] ?? '',
            ]);
        }

        return implode("\n", $lines);
    }

    /**
     * Render history table HTML.
     */
    public function renderTable(int $limit = 50): string
    {
        $entries = $this->getRecent($limit);

        if (empty($entries)) {
            return '<p class="text-muted">Noch keine Einträge vorhanden. Analysiere eine Seite zum Starten.</p>';
        }

        $html = '<table class="data-table"><thead><tr><th>Datum</th><th>URL</th><th>Title</th><th>Score</th><th>Grade</th><th>Quelle</th><th>Aktion</th></tr></thead><tbody>';

        foreach ($entries as $e) {
            $gradeCls = strtolower($e['grade'] ?? 'f');
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($e['created_at'] ?? '') . '</td>';
            $html .= '<td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . htmlspecialchars($e['url'] ?? '') . '</td>';
            $html .= '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . htmlspecialchars($e['title'] ?? '') . '</td>';
            $html .= '<td>' . ($e['overall_score'] ?? 0) . '%</td>';
            $html .= '<td><span class="badge grade-' . $gradeCls . '">' . htmlspecialchars($e['grade'] ?? '-') . '</span></td>';
            $html .= '<td>' . htmlspecialchars($e['source'] ?? '-') . '</td>';
            $html .= '<td><button class="btn btn-sm btn-outline" onclick="deleteHistoryItem(' . (int)($e['id'] ?? 0) . ')">🗑️</button></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    // ── Private methods ────────────────────────────────────────

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

    private function hydrateRows(array $rows): array
    {
        return array_map(function ($row) {
            $row['scores'] = json_decode($row['scores_json'] ?? '{}', true) ?: [];
            $row['suggestions'] = json_decode($row['suggestions_json'] ?? '[]', true) ?: [];
            unset($row['scores_json'], $row['suggestions_json']);
            return $row;
        }, $rows);
    }
}

/**
 * CSV escape helper (module scope).
 */
function csvEscape(string $value): string {
    return '"' . str_replace('"', '""', $value) . '"';
}
