<?php
/**
 * SEO Meta Generator Pro — Keyword Research Module
 * 
 * Basic keyword density analyzer.
 * 
 * @package SEOMetaGen\Modules\KeywordResearch
 * @version 2.0.0
 */

namespace SEOMetaGen\Modules\KeywordResearch;

class KeywordResearch
{
    /** @var array Stop words for DE, EN, FR */
    private array $stopWords;

    public function __construct()
    {
        $this->stopWords = array_merge(
            $this->getGermanStopWords(),
            $this->getEnglishStopWords(),
            $this->getFrenchStopWords()
        );
    }

    /**
     * Analyze keyword density in text.
     */
    public function analyze(string $text, int $maxResults = 30, int $minLength = 4): array
    {
        $clean = mb_strtolower($text);
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $clean);
        $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, function ($w) use ($minLength) {
            return mb_strlen($w) >= $minLength && !in_array($w, $this->stopWords);
        });

        $totalWords = count($words);
        $counts = array_count_values($words);
        arsort($counts);

        $top = array_slice($counts, 0, $maxResults, true);
        $keywords = [];
        foreach ($top as $word => $count) {
            $keywords[] = [
                'word' => $word,
                'count' => $count,
                'density' => $totalWords > 0 ? round(($count / $totalWords) * 100, 2) : 0,
            ];
        }

        // Two-word phrases
        $bigrams = $this->extractNgrams($words, 2);

        return [
            'total_words' => $totalWords,
            'unique_words' => count($counts),
            'keywords' => $keywords,
            'bigrams' => array_slice($bigrams, 0, 10),
            'word_cloud' => $this->generateWordCloudData($keywords),
        ];
    }

    /**
     * Extract n-grams from word list.
     */
    private function extractNgrams(array $words, int $n): array
    {
        $ngrams = [];
        $count = count($words);
        for ($i = 0; $i <= $count - $n; $i++) {
            $gram = implode(' ', array_slice($words, $i, $n));
            if (isset($ngrams[$gram])) {
                $ngrams[$gram]++;
            } else {
                $ngrams[$gram] = 1;
            }
        }
        arsort($ngrams);
        $result = [];
        foreach ($ngrams as $gram => $count) {
            if ($count < 2) break;
            $result[] = ['phrase' => $gram, 'count' => $count];
        }
        return $result;
    }

    /**
     * Generate word cloud data (for JS rendering).
     */
    private function generateWordCloudData(array $keywords): array
    {
        $cloud = [];
        foreach ($keywords as $kw) {
            $cloud[] = [
                'text' => $kw['word'],
                'weight' => $kw['count'],
                'size' => min(36, max(12, 12 + $kw['count'] * 2)),
            ];
        }
        return $cloud;
    }

    /**
     * Render the keyword analysis HTML.
     */
    public function render(array $analysis): string
    {
        $total = $analysis['total_words'] ?? 0;
        $unique = $analysis['unique_words'] ?? 0;

        $html = '<div class="kw-research">';
        $html .= '<h3>📊 Keyword Analysis</h3>';
        $html .= '<div class="kw-stats">';
        $html .= '<div class="kw-stat"><span class="kw-stat-val">' . $total . '</span><span class="kw-stat-label">Total Words</span></div>';
        $html .= '<div class="kw-stat"><span class="kw-stat-val">' . $unique . '</span><span class="kw-stat-label">Unique Words</span></div>';
        $html .= '</div>';

        // Keyword table
        $html .= '<table class="kw-table"><thead><tr><th>Keyword</th><th>Count</th><th>Density</th></tr></thead><tbody>';
        foreach (array_slice($analysis['keywords'] ?? [], 0, 20) as $kw) {
            $bar = str_repeat('█', min(20, (int)($kw['density'] * 10)));
            $html .= '<tr>';
            $html .= '<td><strong>' . htmlspecialchars($kw['word']) . '</strong></td>';
            $html .= '<td>' . $kw['count'] . '</td>';
            $html .= '<td><span class="kw-density-bar">' . $bar . '</span> ' . $kw['density'] . '%</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // Bigrams
        if (!empty($analysis['bigrams'])) {
            $html .= '<h4>🔗 Key Phrases (Bigrams)</h4>';
            $html .= '<div class="kw-bigrams">';
            foreach ($analysis['bigrams'] as $bg) {
                $html .= '<span class="kw-bigram">' . htmlspecialchars($bg['phrase']) . ' (' . $bg['count'] . ')</span>';
            }
            $html .= '</div>';
        }

        // Word cloud
        if (!empty($analysis['word_cloud'])) {
            $html .= '<h4>☁️ Word Cloud</h4>';
            $html .= '<div class="kw-cloud">';
            foreach ($analysis['word_cloud'] as $item) {
                $size = (int)$item['size'];
                $opacity = min(1, 0.4 + ($item['weight'] * 0.1));
                $html .= '<span class="kw-cloud-word" style="font-size:' . $size . 'px;opacity:' . $opacity . '">' . htmlspecialchars($item['text']) . '</span>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function getGermanStopWords(): array
    {
        return ['aber','alle','allem','allen','aller','als','also','ander','andere','anderem','anderen','anderer','anderes','anderm','andern','anders','auch','auf','aus','bei','bin','bis','bist','da','damit','dann','der','den','des','dem','die','das','dass','daß','dein','deine','deinem','deinen','deiner','deines','denn','derer','dessen','dich','dir','du','dies','diese','diesem','diesen','dieser','dieses','doch','dort','durch','ein','eine','einem','einen','einer','eines','einig','einige','einigem','einigen','einiger','einiges','einmal','er','ihn','ihm','es','etwas','euer','eure','eurem','euren','eurer','eures','für','gegen','gewesen','hab','habe','haben','hat','hatte','hatten','hier','hin','hinter','ich','mich','mir','ihr','ihre','ihrem','ihren','ihrer','ihres','euch','im','in','indem','ins','ist','jede','jedem','jeden','jeder','jedes','jene','jenem','jenen','jener','jenes','jetzt','kann','kein','keine','keinem','keinen','keiner','keines','können','könnte','machen','man','manche','manchem','manchen','mancher','manches','mein','meine','meinem','meinen','meiner','meines','mit','muss','musste','nach','nicht','nichts','noch','nun','nur','ob','oder','ohne','sehr','sein','seine','seinem','seinen','seiner','seines','selbst','sich','sie','ihnen','sind','so','solche','solchem','solchen','solcher','solches','soll','sollte','sondern','sonst','über','um','und','uns','unse','unsem','unsen','unser','unses','unter','viel','vom','von','vor','während','war','waren','warst','was','weg','weil','weiter','welche','welchem','welchen','welcher','welches','wenn','werde','werden','wie','wieder','will','wir','wird','wirst','wo','wollen','wollte','würde','würden','zu','zum','zur','zwar','zwischen'];
    }

    private function getEnglishStopWords(): array
    {
        return ['the','and','for','are','but','not','you','all','can','had','her','was','one','our','out','day','get','has','him','his','how','its','may','new','now','old','see','two','who','boy','did','own','say','she','too','use','with','have','this','will','your','from','they','been','call','come','could','each','make','than','them','then','what','when','word','said','which','their','there','about','would','other','into','more','some','time','very','just','also','back','after','think'];
    }

    private function getFrenchStopWords(): array
    {
        return ['le','la','les','un','une','des','du','de','et','est','en','que','qui','dans','ce','il','ne','sur','se','pas','plus','par','je','avec','tout','faire','son','au','aux','pour','sont','mais','nous','vous','ils','elle','elles','cette','ces','mon','ma','mes','ton','ta','tes','sa','ses','leur','leurs'];
    }
}
