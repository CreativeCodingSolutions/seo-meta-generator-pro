# 🔍 SEO Meta Generator Pro v1.0

**Generiere SEO-optimierte Meta-Tags für jede Website — automatisch, intelligent und offline.**

---

## 📋 Inhaltsverzeichnis

1. [Was ist SEO Meta Generator Pro?](#-was-ist-seo-meta-generator-pro)
2. [Features](#-features)
3. [Systemanforderungen](#-systemanforderungen)
4. [Installation](#-installation)
5. [Schnellstart](#-schnellstart)
6. [Verwendung](#-verwendung)
   - [Einzelseite](#einzelseite)
   - [Batch-Verarbeitung](#batch-verarbeitung)
   - [JSON-Konfiguration](#json-konfiguration)
   - [Interaktiver Modus](#interaktiver-modus)
7. [Ausgabeformate](#-ausgabeformate)
8. [API / Python-Modul](#-api--python-modul)
9. [Beispiele](#-beispiele)
10. [Konfiguration](#-konfiguration)
11. [Lizenz & Support](#-lizenz--support)

---

## 🔍 Was ist SEO Meta Generator Pro?

SEO Meta Generator Pro ist ein professionelles Python-CLI-Tool, das automatisch **SEO-optimierte Meta-Tags** generiert:

- ✅ **Meta Title** (optimiert für 60 Zeichen)
- ✅ **Meta Description** (optimiert für 160 Zeichen)
- ✅ **Meta Keywords** (Legacy-Support)
- ✅ **Open Graph Tags** (Facebook, LinkedIn, WhatsApp)
- ✅ **Twitter Card Tags** (Twitter/X)
- ✅ **Schema.org JSON-LD** (Structured Data für Google)
- ✅ **Canonical URLs**
- ✅ **Robots Meta-Tags** (index/nofollow)
- ✅ **Hreflang-Tags** (Mehrsprachigkeit)

**Keine API-Kosten. Keine Internetverbindung nötig. 100% offline.**

---

## ⭐ Features

| Feature | Beschreibung |
|---------|-------------|
| **Smart Title Generation** | Automatische Titel-Kürzung am Wortende (max. 60 Zeichen) |
| **Intelligent Description** | Generiert SEO-freundliche Beschreibungen mit CTA |
| **Open Graph Vollsupport** | og:title, og:description, og:image, og:url, og:type, og:locale |
| **Twitter Cards** | summary_large_image, summary, App-Cards |
| **Schema.org JSON-LD** | Article, Product, Profile, WebSite |
| **Batch-Verarbeitung** | Hunderte URLs auf einmal verarbeiten |
| **JSON-Konfigurationsdatei** | Komplexe Projekte als JSON definieren |
| **Interaktiver Modus** | Schritt-für-Schritt Assistent |
| **Multi-Language** | de_DE, de_AT, de_CH, en_US, en_GB, fr_FR |
| **3 Ausgabeformate** | HTML, JSON, Plaintext |
| **Hreflang-Support** | Automatische Hreflang-Generierung |
| **Template-System** | Eigene Templates für wiederkehrende Projekte |

---

## 💻 Systemanforderungen

- **Python 3.8+** (keine externen Abhängigkeiten!)
- **Betriebssystem:** Windows, macOS, Linux
- **RAM:** Minimal (< 50 MB)
- **Internet:** Nicht erforderlich (100% offline)

### Python-Version prüfen:
```bash
python3 --version
```

---

## 🚀 Installation

### Schritt 1: Dateien entpacken
```bash
unzip seo-meta-generator-pro_v1.zip
cd seo-meta-generator-pro_v1/
```

### Schritt 2: Ausführbar machen (Linux/macOS)
```bash
chmod +x seo_meta_gen.py
```

### Schritt 3: Installation prüfen
```bash
python3 seo_meta_gen.py --version
# Ausgabe: SEO Meta Generator Pro v1.0.0
```

### Optional: Global installieren
```bash
# Linux/macOS
sudo cp seo_meta_gen.py /usr/local/bin/seo-meta-gen
sudo chmod +x /usr/local/bin/seo-meta-gen

# Jetzt überall verfügbar:
seo-meta-gen --version
```

---

## ⚡ Schnellstart

### Beispiel 1: Einfache Meta-Tags generieren
```bash
python3 seo_meta_gen.py \
  --title "Webdesign für KMU" \
  --site "WebAgentur Berlin" \
  --url "https://webagentur-berlin.de" \
  --output meta.html
```

### Beispiel 2: Artikel mit JSON-LD
```bash
python3 seo_meta_gen.py \
  --title "10 SEO-Tipps für 2025" \
  --desc "Die wichtigsten SEO-Strategien für 2025" \
  --type article \
  --author "Max Mustermann" \
  --keywords "seo, tipps, 2025, google" \
  --output article_meta.html
```

### Beispiel 3: Batch-Verarbeitung
```bash
python3 seo_meta_gen.py \
  --batch sample_urls.txt \
  --output-dir ./meta-output/
```

### Beispiel 4: Interaktiver Modus
```bash
python3 seo_meta_gen.py --interactive
```

---

## 📖 Verwendung

### Einzelseite

```bash
python3 seo_meta_gen.py \
  --title "Der Seitentitel" \
  --site "Seitenname" \
  --url "https://example.com" \
  --desc "Kurze Beschreibung der Seite" \
  --keywords "keyword1, keyword2, keyword3" \
  --type website \
  --image "https://example.com/og-image.jpg" \
  --author "Autorenname" \
  --org "Organisationsname" \
  --locale de_DE \
  --twitter "meinHandle" \
  --output meta.html
```

**Parameter:**

| Parameter | Erforderlich | Default | Beschreibung |
|-----------|-------------|---------|-------------|
| `--title`, `-t` | ✅ Ja | — | Seitentitel |
| `--site`, `-s` | Nein | — | Seitenname / Markenname |
| `--url`, `-u` | Nein | — | URL der Seite |
| `--desc`, `-d` | Nein | — | Beschreibungshinweis |
| `--keywords`, `-k` | Nein | — | Keywords (kommagetrennt) |
| `--type` | Nein | website | Seitentype |
| `--image`, `-i` | Nein | — | OG-Bild-URL |
| `--author`, `-a` | Nein | — | Autorenname |
| `--org`, `-o` | Nein | — | Organisationsname |
| `--locale`, `-l` | Nein | de_DE | Locale |
| `--twitter` | Nein | — | Twitter Handle |
| `--no-index` | Nein | False | noindex setzen |
| `--no-follow` | Nein | False | nofollow setzen |
| `--output`, `-O` | Nein | stdout | Ausgabedatei |
| `--format`, `-f` | Nein | html | Format: html/json/txt |
| `--full-html` | Nein | False | Vollständige HTML-Seite |

### Batch-Verarbeitung

Erstelle eine Datei mit URLs (eine pro Zeile):
```text
# mein-projekt-urls.txt
https://example-praxis.de
https://muster-kanzlei.at
https://test-shop.ch
```

Verarbeite alle URLs:
```bash
python3 seo_meta_gen.py \
  --batch mein-projekt-urls.txt \
  --output-dir ./meta-output/
```

### JSON-Konfiguration

Erstelle eine JSON-Datei mit allen Seiten:
```json
[
  {
    "title": "Homepage",
    "url": "https://example.de",
    "site_name": "Example GmbH",
    "description_hint": "Willkommen bei Example GmbH",
    "keywords": ["digital marketing", "seo"],
    "type": "website",
    "image_url": "https://example.de/og.jpg",
    "org_name": "Example GmbH",
    "locale": "de_DE"
  }
]
```

Verarbeite die Konfiguration:
```bash
python3 seo_meta_gen.py \
  --config mein-projekt.json \
  --output-dir ./meta-output/
```

### Interaktiver Modus

```bash
python3 seo_meta_gen.py --interactive
```

Der interaktive Modus fragt dich Schritt für Schritt nach allen notwendigen Informationen.

---

## 📄 Ausgabeformate

### HTML (default)
```bash
python3 seo_meta_gen.py --title "Mein Titel" --format html --output meta.html
```

### JSON
```bash
python3 seo_meta_gen.py --title "Mein Titel" --format json --output meta.json
```

### Plaintext
```bash
python3 seo_meta_gen.py --title "Mein Titel" --format txt --output meta.txt
```

### Vollständige HTML-Seite
```bash
python3 seo_meta_gen.py --title "Mein Titel" --full-html --output page.html
```

---

## 🐍 API / Python-Modul

Das Tool kann auch als Python-Modul verwendet werden:

```python
from seo_meta_gen import MetaGenerator, HTMLGenerator

# Generator konfigurieren
gen = MetaGenerator({
    "brand_name": "Meine Agentur",
    "twitter_handle": "@meineAgentur",
    "organization_name": "Meine Agentur GmbH",
})

# Meta-Tags generieren
title = gen.generate_title("Webdesign für KMU", "Meine Agentur")
desc = gen.generate_description("Professionelle Webdesign-Agentur für KMU.", "Webdesign")
keywords = gen.generate_keywords("Webdesign KMU", ["webdesign", "kmu"])
og = gen.generate_og_tags(title, desc, "https://example.de", "Meine Agentur")
twitter = gen.generate_twitter_cards(title, desc)
json_ld = gen.generate_json_ld("website", title, desc, "https://example.de")

# HTML generieren
html = HTMLGenerator.render_meta_tags(
    title, desc, keywords,
    gen.generate_robots(),
    gen.generate_canonical("https://example.de"),
    og, twitter, json_ld
)

print(html)
```

---

## 📝 Beispiele

### Beispiel: Komplette Praxis-Website

```bash
# 1. URLs-Datei erstellen
cat > praxis_urls.txt << EOF
https://zahnarzt-muenchen.de
https://zahnarzt-muenchen.de/leistungen
https://zahnarzt-muenchen.de/ueber-uns
https://zahnarzt-muenchen.de/kontakt
EOF

# 2. Batch-Verarbeitung
python3 seo_meta_gen.py \
  --batch praxis_urls.txt \
  --output-dir ./praxis-meta/

# 3. Ergebnis
ls praxis-meta/
# zahnarzt-muenchen.de_meta.html
# leistungen_meta.html
# ueber-uns_meta.html
# kontakt_meta.html
```

### Beispiel: E-Commerce Produktseite

```bash
python3 seo_meta_gen.py \
  --title "Premium Lederhandtasche 'Milano'" \
  --site "Lederwaren Schmidt" \
  --url "https://lederwaren-schmidt.de/produkte/milano" \
  --desc "Handgefertigte Lederhandtasche aus italienischem Leder. In 5 Farben erhältlich." \
  --type product \
  --keywords "lederhandtasche, premium, italienisch, handgefertigt" \
  --image "https://lederwaren-schmidt.de/bilder/milano-og.jpg" \
  --org "Lederwaren Schmidt GmbH" \
  --output produkt_meta.html
```

---

## ⚙️ Konfiguration

Die Standardkonfiguration kann über den `MetaGenerator`-Konstruktor angepasst werden:

```python
from seo_meta_gen import MetaGenerator

gen = MetaGenerator({
    "title_max_length": 60,          # Max. Zeichen für Title
    "description_max_length": 160,   # Max. Zeichen für Description
    "default_locale": "de_DE",       # Standard-Locale
    "default_type": "website",       # Standard-Seitentype
    "brand_name": "Meine Marke",     # Standard-Markenname
    "twitter_handle": "@meinHandle", # Standard-Twitter-Handle
    "default_image": "https://...",  # Standard-OG-Bild
    "organization_name": "Meine GmbH",
    "organization_logo": "https://.../logo.png",
})
```

---

## 📜 Lizenz & Support

**Lizenz:** Commercial License (siehe LICENSE.txt)

Dieses Produkt ist ein digitaler Download. Nach dem Kauf erhältst du:

- ✅ Vollständiger Quellcode
- ✅ README & Dokumentation (DE)
- ✅ Beispiel-Dateien
- ✅ 6 Monate E-Mail-Support

**Support:** Bei Fragen antworte du einfach auf die Bestellbestätigungs-E-Mail.

---

**Erstellt mit ❤️ von OWL Digital Factory**
**Version 1.0.0 | Juni 2026**
