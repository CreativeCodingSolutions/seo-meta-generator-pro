#!/usr/bin/env python3
"""
SEO Meta Generator Pro — Tests & Examples
==========================================
Beispiel-Skripte zur Demonstration der Kernfunktionen.

Run: python3 examples.py
"""

import json
from seo_meta_gen import MetaGenerator, HTMLGenerator, BatchProcessor

def test_basic_generation():
    """Test 1: Einfache Meta-Generierung."""
    print("=" * 50)
    print("TEST 1: Einfache Meta-Generierung")
    print("=" * 50)

    gen = MetaGenerator({
        "brand_name": "WebAgentur Berlin",
        "twitter_handle": "@webagenturBerlin",
        "organization_name": "WebAgentur Berlin GmbH",
    })

    title = gen.generate_title("Webdesign für KMU", "WebAgentur Berlin")
    desc = gen.generate_description("Professionelle Webdesign-Agentur für kleine und mittlere Unternehmen. Wir erstellen responsive, SEO-optimierte Websites.")
    keywords = gen.generate_keywords("Webdesign für KMU Berlin", ["webdesign", "website erstellen", "kmu", "responsiv"])
    robots = gen.generate_robots()
    canonical = gen.generate_canonical("https://webagentur-berlin.de/leistungen/webdesign")
    og = gen.generate_og_tags(title, desc, "https://webagentur-berlin.de", "WebAgentur Berlin", "https://webagentur-berlin.de/og.jpg", "de_DE", "website")
    twitter = gen.generate_twitter_cards(title, desc, "https://webagentur-berlin.de/og.jpg")
    json_ld = gen.generate_json_ld("website", title, desc, "https://webagentur-berlin.de", "https://webagentur-berlin.de/og.jpg", "", "", "", "WebAgentur Berlin GmbH")

    print(f"Title:        {title}")
    print(f"Description:  {desc}")
    print(f"Keywords:     {keywords}")
    print(f"Robots:       {robots}")
    print(f"Canonical:    {canonical}")
    print()

    # HTML Output
    html = HTMLGenerator.render_meta_tags(title, desc, keywords, robots, canonical, og, twitter, json_ld)
    print("HTML Output:")
    print(html)
    print()


def test_article_generation():
    """Test 2: Artikel-Meta-Generierung."""
    print("=" * 50)
    print("TEST 2: Artikel-Meta-Generierung")
    print("=" * 50)

    gen = MetaGenerator()

    title = gen.generate_title("10 SEO-Tipps für 2025", "Digital Markier Blog")
    desc = gen.generate_description("Die wichtigsten SEO-Strategies für das Jahr 2025. Von Core Web Vitals bis KI-gestützte Content-Optimierung.", "10 SEO-Tipps für 2025")
    og = gen.generate_og_tags(title, desc, "https://blog.dein-homepage.de/seo-tipps-2025", "Digital Blog", "", "de_DE", "article")

    # JSON-LD für Article
    json_ld = gen.generate_json_ld(
        "article", title, desc,
        "https://blog.dein-homepage.de/seo-tipps-2025",
        "", "Max Mustermann", "2025-01-15T10:00:00+00:00", "2025-01-20T14:30:00+00:00"
    )

    print(f"Title: {title}")
    print(f"Desc:  {desc}")
    print(f"\nJSON-LD:")
    print(json.dumps(json_ld, indent=2, ensure_ascii=False))
    print()


def test_batch_processing():
    """Test 3: Batch-Verarbeitung."""
    print("=" * 50)
    print("TEST 3: Batch-Verarbeitung")
    print("=" * 50)

    gen = MetaGenerator()
    processor = BatchProcessor(gen)

    # Beispiel-URLs-Datei erstellen
    urls = """# SEO Meta Generator Pro — Beispiel-URLs
https://example-praxis.de
https://muster-kanzlei.at
https://test-shop.ch
"""
    from pathlib import Path
    urls_file = "/tmp/test_urls.txt"
    Path(urls_file).write_text(urls)

    results = processor.process_urls_file(urls_file, "/tmp/meta_output")
    print(f"\n{len(results)} Dateien generiert.")
    for r in results:
        print(f"  {r['url']} → {r['file']}")
        content = Path(r['file']).read_text()
        print(f"    (Inhalt: {len(content)} Zeichen)")
    print()


def test_json_config():
    """Test 4: JSON-Konfig verarbeiten."""
    print("=" * 50)
    print("TEST 4: JSON-Konfiguration")
    print("=" * 50)

    config = [
        {
            "title": "Homepage",
            "url": "https://example.de",
            "site_name": "Example GmbH",
            "description_hint": "Willkommen bei Example GmbH — Ihr Partner für Digitales Marketing",
            "keywords": ["digital marketing", "seo", "webdesign"],
            "type": "website",
            "image_url": "https://example.de/og.jpg",
            "org_name": "Example GmbH",
            "locale": "de_DE",
        },
        {
            "title": "Über uns",
            "url": "https://example.de/ueber-uns",
            "site_name": "Example GmbH",
            "description_hint": "Lernen Sie unser Team kennen — 10 Jahre Erfolgsgeschichte im Digital Marketing.",
            "type": "profile",
            "author_name": "Example Team",
        },
    ]

    from pathlib import Path
    config_file = "/tmp/test_config.json"
    Path(config_file).write_text(json.dumps(config, indent=2, ensure_ascii=False))

    gen = MetaGenerator()
    processor = BatchProcessor(gen)
    results = processor.process_config_file(config_file, "/tmp/meta_config_output")

    for r in results:
        print(f"\nTitle: {r['title']}")
        print(f"Desc:  {r['description']}")
        print(f"HTML:  {len(r['html'])} Zeichen")
    print()


if __name__ == "__main__":
    test_basic_generation()
    test_article_generation()
    test_batch_processing()
    test_json_config()

    print("\n✅ Alle Tests erfolgreich!")
