# Changelog

Alle wichtigen Änderungen am SEO Meta Generator Pro werden hier dokumentiert.

## [1.0.0] — 2026-06-02

### 🎉 Erstveröffentlichung

**Features:**
- Meta Title Generation (optimiert für 60 Zeichen)
- Meta Description Generation (optimiert für 160 Zeichen, mit CTA)
- Meta Keywords Generation (Legacy-Support)
- Open Graph Tags (og:title, og:desc, og:image, og:url, og:type, og:locale)
- Twitter Card Tags (summary_large_image, summary)
- Schema.org JSON-LD (Article, Product, Profile, WebSite)
- Canonical URL Generation
- Robots Meta-Tag (index/nofollow)
- Hreflang-Tags für mehrsprachige Seiten
- Batch-Verarbeitung (URLs-Datei oder JSON-Config)
- Interaktiver Modus
- 3 Ausgabeformate: HTML, JSON, Plaintext
- Vollständige HTML-Seiten-Generierung
- Multi-Language Support (de_DE, de_AT, de_CH, en_US, en_GB, fr_FR)
- Konfigurierbar via Python API

**Dateien:**
- `seo_meta_gen.py` — Haupt-CLI-Tool (30+ KB)
- `examples.py` — Tests & Beispiele
- `sample_config.json` — Beispiel-JSON-Konfiguration
- `sample_urls.txt` — Beispiel-URLs für Batch-Modus
- `README.md` — Komplette Dokumentation (DE)

**Technische Details:**
- Python 3.8+ (keine externen Abhängigkeiten)
- 100% offline nutzbar
- ~1.500 Zeilen Code
- Umfangreiche Docstrings und Kommentare
