/**
 * SEO Meta Generator Pro — Main JavaScript
 * 
 * Handles form interactions, AJAX calls, score visualization,
 * dark mode, localStorage history, export, and module loading.
 */

(function () {
    'use strict';

    // ── State ──────────────────────────────────────────────
    const state = {
        darkMode: localStorage.getItem('seometa_dark') === 'true',
        history: JSON.parse(localStorage.getItem('seometa_history') || '[]'),
        currentResult: null,
        currentGenerated: null,
    };

    // ── DOM Ready ──────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        initDarkMode();
        initTabs();
        initForms();
        initHistory();
        initExport();
        initPrint();
        initBulkMode();
        initCompareMode();
        initKeywordAnalysis();
        renderHistory();
    });

    // ── Dark Mode ──────────────────────────────────────────
    function initDarkMode() {
        if (state.darkMode) {
            document.body.classList.add('dark-mode');
        }
        const toggle = document.getElementById('darkModeToggle');
        if (toggle) {
            toggle.addEventListener('click', function () {
                state.darkMode = !state.darkMode;
                document.body.classList.toggle('dark-mode', state.darkMode);
                localStorage.setItem('seometa_dark', state.darkMode);
            });
        }
    }

    // ── Tabs ───────────────────────────────────────────────
    function initTabs() {
        document.querySelectorAll('.tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const tab = this.dataset.tab;
                document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
                document.querySelectorAll('.tab-content').forEach(function (c) { c.classList.remove('active'); });
                this.classList.add('active');
                const content = document.getElementById('tab-' + tab);
                if (content) content.classList.add('active');
            });
        });
    }

    // ── Forms ──────────────────────────────────────────────
    function initForms() {
        // Single analyze form
        const form = document.getElementById('analyzeForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                analyzeSingle();
            });
        }

        // Generate form
        const genForm = document.getElementById('generateForm');
        if (genForm) {
            genForm.addEventListener('submit', function (e) {
                e.preventDefault();
                generateMeta();
            });
        }
    }

    // ── Single Analysis ────────────────────────────────────
    function analyzeSingle() {
        const url = document.getElementById('an_url').value.trim();
        const title = document.getElementById('an_title').value.trim();
        const desc = document.getElementById('an_desc').value.trim();
        const keywords = document.getElementById('an_keywords').value.trim();
        const image = document.getElementById('an_image').value.trim();

        showLoading('resultArea');

        // Use API
        apiPost('analyze_meta', { url: url, title: title, description: desc, keywords: keywords, image: image })
            .then(function (resp) {
                if (resp.success) {
                    state.currentResult = resp.data;
                    renderAnalysisResult(resp.data);
                    addToHistory(resp.data);
                } else {
                    showError('resultArea', resp.error || 'Analysis failed');
                }
            })
            .catch(function (err) {
                showError('resultArea', 'Error: ' + err.message);
            });
    }

    // ── Generate Meta ──────────────────────────────────────
    function generateMeta() {
        const data = {
            title: document.getElementById('gen_title').value.trim(),
            site_name: document.getElementById('gen_site').value.trim(),
            url: document.getElementById('gen_url').value.trim(),
            description: document.getElementById('gen_desc').value.trim(),
            keywords: document.getElementById('gen_keywords').value.trim(),
            image_url: document.getElementById('gen_image').value.trim(),
            type: document.getElementById('gen_type').value,
            locale: document.getElementById('gen_locale').value,
            author_name: document.getElementById('gen_author').value.trim(),
            org_name: document.getElementById('gen_org').value.trim(),
            twitter_handle: document.getElementById('gen_twitter').value.trim(),
        };

        showLoading('generateResult');

        apiPost('generate', { data: data })
            .then(function (resp) {
                if (resp.success) {
                    state.currentGenerated = resp.data;
                    renderGeneratedResult(resp.data, resp.html);
                } else {
                    showError('generateResult', resp.error || 'Generation failed');
                }
            })
            .catch(function (err) {
                showError('generateResult', 'Error: ' + err.message);
            });
    }

    // ── Chart Instances ────────────────────────────────────
    let seoChart = null;

    // ── Render Analysis Result ─────────────────────────────
    function renderAnalysisResult(result) {
        const area = document.getElementById('resultArea');
        const gradeClass = (result.grade || 'f').toLowerCase();
        const score = result.overall_score || 0;

        let scoresHtml = '';
        for (const [key, val] of Object.entries(result.scores || {})) {
            const color = val >= 80 ? '#10b981' : val >= 50 ? '#f59e0b' : '#ef4444';
            scoresHtml += '<div class="score-item">' +
                '<span class="score-label">' + key + '</span>' +
                '<div class="score-bar"><div class="score-fill" style="width:' + val + '%;background:' + color + '"></div></div>' +
                '<span class="score-value" style="color:' + color + '">' + val + '%</span>' +
                '</div>';
        }

        let suggestionsHtml = '';
        (result.suggestions || []).forEach(function (s) {
            suggestionsHtml += '<li>' + s + '</li>';
        });

        area.innerHTML =
            '<div class="result-card">' +
            '<div class="result-header">' +
            '<div class="score-circle grade-' + gradeClass + '">' + (result.grade || '-') + '</div>' +
            '<div class="score-info">' +
            '<div class="score-number">' + score + '%</div>' +
            '<div class="score-label-main">SEO Score</div>' +
            '</div>' +
            '</div>' +
            '<div class="scores-grid">' + scoresHtml + '</div>' +
            '<div style="margin:1rem 0"><canvas id="seoScoreChart" height="200"></canvas></div>' +
            '<div class="suggestions">' +
            '<h4>💡 Suggestions</h4>' +
            '<ul>' + suggestionsHtml + '</ul>' +
            '</div>' +
            '</div>';

        // Render Chart.js chart
        renderScoreChart(result);
    }

    // ── Chart.js Score Chart ───────────────────────────────
    function renderScoreChart(result) {
        const canvas = document.getElementById('seoScoreChart');
        if (!canvas || typeof Chart === 'undefined') return;
        const ctx = canvas.getContext('2d');
        if (seoChart) seoChart.destroy();
        const labels = Object.keys(result.scores || {});
        const data = Object.values(result.scores || {});
        const colors = data.map(v => v >= 80 ? '#10b981' : v >= 50 ? '#f59e0b' : '#ef4444');
        seoChart = new Chart(ctx, {
            type:'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Score %',
                    data: data,
                    backgroundColor: colors,
                    borderRadius: 6,
                    barPercentage: 0.6,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 100, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } },
                    x: { ticks: { color: '#94a3b8' }, grid: { display: false } }
                }
            }
        });
    }

    // ── Render Generated Result ────────────────────────────
    function renderGeneratedResult(data, html) {
        const area = document.getElementById('generateResult');
        area.innerHTML =
            '<div class="result-card">' +
            '<h4>✅ Generated Meta Tags</h4>' +
            '<div class="meta-preview">' +
            '<div class="meta-item"><strong>Title:</strong> ' + escapeHtml(data.title) + '</div>' +
            '<div class="meta-item"><strong>Description:</strong> ' + escapeHtml(data.description) + '</div>' +
            '<div class="meta-item"><strong>Keywords:</strong> ' + escapeHtml(data.keywords) + '</div>' +
            '<div class="meta-item"><strong>Robots:</strong> ' + escapeHtml(data.robots) + '</div>' +
            '<div class="meta-item"><strong>Canonical:</strong> ' + escapeHtml(data.canonical) + '</div>' +
            '</div>' +
            '<h4>📄 HTML Output</h4>' +
            '<pre class="code-block">' + escapeHtml(html) + '</pre>' +
            '<button class="btn btn-sm" onclick="copyCode()">📋 Copy HTML</button>' +
            '</div>';
    }

    // ── Bulk Mode ──────────────────────────────────────────
    function initBulkMode() {
        const btn = document.getElementById('bulkAnalyzeBtn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            const textarea = document.getElementById('bulkInput');
            if (!textarea) return;
            const lines = textarea.value.trim().split('\n').filter(function (l) { return l.trim(); });
            if (lines.length === 0) return;

            const items = lines.map(function (line) {
                const parts = line.split('|').map(function (s) { return s.trim(); });
                return {
                    url: parts[0] || '',
                    title: parts[1] || '',
                    description: parts[2] || '',
                    keywords: parts[3] || '',
                    image: parts[4] || '',
                };
            });

            showLoading('bulkResult');
            apiPost('bulk', { items: items })
                .then(function (resp) {
                    if (resp.success) {
                        renderBulkResults(resp.data);
                    } else {
                        showError('bulkResult', resp.error);
                    }
                })
                .catch(function (err) { showError('bulkResult', err.message); });
        });
    }

    function renderBulkResults(data) {
        const area = document.getElementById('bulkResult');
        let rows = '';
        (data.results || []).forEach(function (r, i) {
            const gradeClass = (r.grade || 'f').toLowerCase();
            rows += '<tr><td>' + (i + 1) + '</td><td>' + escapeHtml(r.url) + '</td><td>' + escapeHtml(r.title) + '</td>' +
                '<td><span class="badge grade-' + gradeClass + '">' + (r.grade || '-') + '</span></td>' +
                '<td>' + (r.overall_score || 0) + '%</td></tr>';
        });

        area.innerHTML =
            '<div class="result-card">' +
            '<h4>📊 Bulk Analysis Results</h4>' +
            '<p>Average Score: <strong>' + (data.average_score || 0) + '%</strong> | Total: ' + (data.total || 0) + ' pages</p>' +
            '<table class="data-table"><thead><tr><th>#</th><th>URL</th><th>Title</th><th>Grade</th><th>Score</th></tr></thead>' +
            '<tbody>' + rows + '</tbody></table>' +
            '</div>';
    }

    // ── Compare Mode ───────────────────────────────────────
    function initCompareMode() {
        const btn = document.getElementById('compareBtn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            const competitors = [];
            document.querySelectorAll('.compare-input').forEach(function (el) {
                const idx = el.dataset.idx;
                competitors.push({
                    url: document.getElementById('comp_url_' + idx).value.trim(),
                    title: document.getElementById('comp_title_' + idx).value.trim(),
                    description: document.getElementById('comp_desc_' + idx).value.trim(),
                    keywords: document.getElementById('comp_kw_' + idx).value.trim(),
                    image: document.getElementById('comp_img_' + idx).value.trim(),
                });
            });

            showLoading('compareResult');
            apiPost('compare', { competitors: competitors })
                .then(function (resp) {
                    if (resp.success) {
                        renderCompareResults(resp.data);
                    } else {
                        showError('compareResult', resp.error);
                    }
                })
                .catch(function (err) { showError('compareResult', err.message); });
        });
    }

    function renderCompareResults(data) {
        const area = document.getElementById('compareResult');
        let cards = '';
        (data.competitors || []).forEach(function (r, i) {
            const isWinner = i === data.winner_index;
            const gradeClass = (r.grade || 'f').toLowerCase();
            cards += '<div class="compare-card ' + (isWinner ? 'winner' : '') + '">' +
                (isWinner ? '<div class="winner-badge">🏆 Winner</div>' : '') +
                '<div class="compare-header">' +
                '<span class="badge grade-' + gradeClass + '">' + (r.grade || '-') + '</span>' +
                '<span class="compare-score">' + (r.overall_score || 0) + '%</span>' +
                '</div>' +
                '<div class="compare-url">' + escapeHtml(r.url) + '</div>' +
                '<div class="compare-title">' + escapeHtml(r.title) + '</div>' +
                '<div class="compare-desc">' + escapeHtml(r.description) + '</div>' +
                '</div>';
        });

        area.innerHTML =
            '<div class="result-card">' +
            '<h4>⚔️ Competitor Comparison</h4>' +
            '<div class="compare-grid">' + cards + '</div>' +
            '</div>';
    }

    // ── Keyword Analysis ───────────────────────────────────
    function initKeywordAnalysis() {
        const btn = document.getElementById('kwAnalyzeBtn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            const text = document.getElementById('kwText').value.trim();
            if (!text) return;

            showLoading('kwResult');
            apiPost('keywords', { text: text, limit: 25 })
                .then(function (resp) {
                    if (resp.success) {
                        renderKeywordResults(resp.data);
                    } else {
                        showError('kwResult', resp.error);
                    }
                })
                .catch(function (err) { showError('kwResult', err.message); });
        });
    }

    function renderKeywordResults(data) {
        const area = document.getElementById('kwResult');
        let tableRows = '';
        (data.keywords || []).forEach(function (kw) {
            const bar = '█'.repeat(Math.min(20, Math.round(kw.density * 10)));
            tableRows += '<tr><td><strong>' + escapeHtml(kw.word) + '</strong></td><td>' + kw.count + '</td>' +
                '<td><span class="kw-density-bar">' + bar + '</span> ' + kw.density + '%</td></tr>';
        });

        let bigramsHtml = '';
        (data.bigrams || []).forEach(function (bg) {
            bigramsHtml += '<span class="kw-bigram">' + escapeHtml(bg.phrase) + ' (' + bg.count + ')</span>';
        });

        area.innerHTML =
            '<div class="result-card">' +
            '<h4>📊 Keyword Analysis</h4>' +
            '<div class="kw-stats">' +
            '<div class="kw-stat"><span class="kw-stat-val">' + (data.total_words || 0) + '</span><span class="kw-stat-label">Total Words</span></div>' +
            '<div class="kw-stat"><span class="kw-stat-val">' + (data.unique_words || 0) + '</span><span class="kw-stat-label">Unique Words</span></div>' +
            '</div>' +
            '<table class="data-table"><thead><tr><th>Keyword</th><th>Count</th><th>Density</th></tr></thead>' +
            '<tbody>' + tableRows + '</tbody></table>' +
            (bigramsHtml ? '<h4>🔗 Key Phrases</h4><div class="kw-bigrams">' + bigramsHtml + '</div>' : '') +
            '</div>';
    }

    // ── Export ─────────────────────────────────────────────
    function initExport() {
        document.querySelectorAll('.export-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const format = this.dataset.format;
                if (!state.currentResult && !state.currentGenerated) {
                    alert('Please analyze or generate first.');
                    return;
                }
                exportData(format);
            });
        });
    }

    function exportData(format) {
        const data = state.currentResult || state.currentGenerated;
        if (!data) return;

        let content, filename, mime;
        if (format === 'json') {
            content = JSON.stringify(data, null, 2);
            filename = 'seo-meta-report.json';
            mime = 'application/json';
        } else if (format === 'csv') {
            content = 'Key,Value\n';
            for (const [k, v] of Object.entries(data)) {
                if (typeof v !== 'object') {
                    content += '"' + k + '","' + String(v).replace(/"/g, '""') + '"\n';
                }
            }
            filename = 'seo-meta-report.csv';
            mime = 'text/csv';
        } else {
            // HTML report
            content = generateHtmlReport(data);
            filename = 'seo-meta-report.html';
            mime = 'text/html';
        }

        const blob = new Blob([content], { type: mime + ';charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    }

    function generateHtmlReport(data) {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>SEO Meta Report</title>' +
            '<style>body{font-family:sans-serif;padding:2rem;background:#0f172a;color:#e2e8f0}' +
            'table{width:100%;border-collapse:collapse}th,td{padding:0.5rem;border:1px solid #334155;text-align:left}' +
            'th{background:#1e293b}</style></head><body>' +
            '<h1>SEO Meta Report</h1>' +
            '<pre>' + escapeHtml(JSON.stringify(data, null, 2)) + '</pre>' +
            '</body></html>';
    }

    // ── Print ──────────────────────────────────────────────
    function initPrint() {
        const btn = document.getElementById('printBtn');
        if (btn) {
            btn.addEventListener('click', function () { window.print(); });
        }
    }

    // ── History (localStorage) ─────────────────────────────
    function addToHistory(result) {
        const entry = {
            url: result.url || '',
            title: result.title || '',
            score: result.overall_score || 0,
            grade: result.grade || '-',
            date: new Date().toISOString(),
        };
        state.history.unshift(entry);
        if (state.history.length > 50) state.history.pop();
        localStorage.setItem('seometa_history', JSON.stringify(state.history));
        renderHistory();
    }

    function renderHistory() {
        const container = document.getElementById('historyList');
        if (!container) return;
        if (state.history.length === 0) {
            container.innerHTML = '<p class="text-muted">No history yet. Analyze a page to start.</p>';
            return;
        }
        let html = '<table class="data-table"><thead><tr><th>Date</th><th>URL</th><th>Title</th><th>Score</th></tr></thead><tbody>';
        state.history.forEach(function (h) {
            const gradeClass = (h.grade || 'f').toLowerCase();
            html += '<tr><td>' + new Date(h.date).toLocaleDateString() + '</td><td>' + escapeHtml(h.url) + '</td><td>' + escapeHtml(h.title) + '</td>' +
                '<td><span class="badge grade-' + gradeClass + '">' + h.grade + '</span> ' + h.score + '%</td></tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function initHistory() {
        // Clear history button
        const clearBtn = document.getElementById('clearHistoryBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                state.history = [];
                localStorage.removeItem('seometa_history');
                renderHistory();
            });
        }
    }

    // ── API Helper ─────────────────────────────────────────
    function apiPost(action, data) {
        return fetch('api/?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        }).then(function (r) { return r.json(); });
    }

    // ── UI Helpers ─────────────────────────────────────────
    function showLoading(targetId) {
        const el = document.getElementById(targetId);
        if (el) el.innerHTML = '<div class="loading"><div class="spinner"></div><p>Analyzing...</p></div>';
    }

    function showError(targetId, message) {
        const el = document.getElementById(targetId);
        if (el) el.innerHTML = '<div class="error-message">❌ ' + escapeHtml(message) + '</div>';
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ── Global functions ───────────────────────────────────
    window.copyCode = function () {
        const code = document.querySelector('.code-block');
        if (code) {
            navigator.clipboard.writeText(code.textContent).then(function () {
                alert('Copied to clipboard!');
            });
        }
    };
})();
