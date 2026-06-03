#!/usr/bin/env python3
"""
SEO Meta Generator Pro v1.0
============================
Ein CLI-Tool zur automatischen Generierung von SEO-optimierten
Meta-Titles, Descriptions, Open-Graph-Tags und Schema.org JSON-LD.

Autor: OWL Digital Factory
Lizenz: Commercial License (siehe LICENSE.txt)
Version: 1.0.0

Usage:
    python3 seo_meta_gen.py --url "https://example.com" --output meta.html
    python3 seo_meta_gen.py --title "Mein Titel" --desc "Meine Beschreibung" --type article
    python3 seo_meta_gen.py --batch urls.txt --output-dir ./meta-output/
    python3 seo_meta_gen.py --interactive
"""

import argparse
import json
import os
import re
import sys
import textwrap
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional
from urllib.parse import urlparse

__version__ = "1.0.0"


# ═══════════════════════════════════════════════════════════
# KONFIGURATION
# ═══════════════════════════════════════════════════════════

DEFAULT_CONFIG = {
    "title_max_length": 60,
    "description_max_length": 160,
    "og_image_min_width": 1200,
    "og_image_min_height": 630,
    "default_locale": "de_DE",
    "default_type": "website",
    "supported_types": ["website", "article", "product", "profile", "blog"],
    "supported_locales": ["de_DE", "de_AT", "de_CH", "en_US", "en_GB", "fr_FR"],
    "brand_name": "",
    "twitter_handle": "",
    "default_image": "",
    "organization_name": "",
    "organization_logo": "",
}


# ═══════════════════════════════════════════════════════════
# UTILITY FUNCTIONS
# ═══════════════════════════════════════════════════════════

def truncate(text: str, max_length: int, suffix: str = "…") -> str:
    """Kürze Text auf max_length, am Wortende."""
    if len(text) <= max_length:
        return text
    truncated = textwrap.wrap(text, max_length - len(suffix))
    return truncated[0] + suffix if truncated else text[:max_length]


def slugify(text: str) -> str:
    """Erstelle URL-freundlichen Slug."""
    text = text.lower().strip()
    text = re.sub(r"[äöüß]", lambda m: {"ä": "ae", "ö": "oe", "ü": "ue", "ß": "ss"}[m.group()], text)
    text = re.sub(r"[^a-z0-9\s-]", "", text)
    text = re.sub(r"[\s_]+", "-", text)
    text = re.sub(r"-+", "-", text)
    return text.strip("-")


def validate_url(url: str) -> bool:
    """Einfache URL-Validierung."""
    try:
        result = urlparse(url)
        return all([result.scheme in ("http", "https"), result.netloc])
    except Exception:
        return False


def get_domain(url: str) -> str:
    """Extrahiere Domain aus URL."""
    try:
        return urlparse(url).netloc
    except Exception:
        return ""


def get_site_name(url: str) -> str:
    """Generiere Site-Name aus Domain."""
    domain = get_domain(url)
    if domain:
        name = domain.replace("www.", "").split(".")[0]
        return name.capitalize()
    return "Website"


# ═══════════════════════════════════════════════════════════
# META GENERATORS
# ═══════════════════════════════════════════════════════════

class MetaGenerator:
    """Hauptklasse für die SEO-Meta-Generierung."""

    def __init__(self, config: Optional[dict] = None):
        self.config = {**DEFAULT_CONFIG, **(config or {})}

    def generate_title(
        self,
        page_title: str,
        site_name: str = "",
        separator: str = " | ",
    ) -> str:
        """
        Generiere SEO-optimierten Title-Tag.
        
        Format: [Seitentitel] | [Seitenname]
        Max. 60 Zeichen (empfohlen von Google).
        """
        site = site_name or self.config.get("brand_name", "")
        if site:
            full_title = f"{page_title}{separator}{site}"
        else:
            full_title = page_title

        return truncate(full_title, self.config["title_max_length"])

    def generate_description(
        self,
        content_hint: str = "",
        page_title: str = "",
        keywords: Optional[list] = None,
    ) -> str:
        """
        Generiere SEO-optimierte Meta-Description.
        
        Max. 160 Zeichen, mit Call-to-Action.
        """
        if content_hint:
            base = content_hint
        elif page_title:
            base = f"Erfahre mehr über {page_title}. "
        else:
            base = "Entdecke wertvolle Informationen und Angebote auf unserer Seite."

        # Keywords einfließen lassen
        if keywords:
            kw_text = ", ".join(keywords[:3])
            base = f"{base} Themen: {kw_text}."

        # Call-to-Action hinzufügen
        ctas = [
            "Jetzt entdecken!",
            "Mehr erfahren →",
            "Jetzt starten!",
            "Kostenlos testen!",
            "Direkt anfragen!",
        ]
        for cta in ctas:
            candidate = f"{base} {cta}"
            if len(candidate) <= self.config["description_max_length"]:
                return candidate

        return truncate(base, self.config["description_max_length"])

    def generate_keywords(self, page_title: str, extra_keywords: Optional[list] = None) -> str:
        """Generiere Meta-Keywords (für Legacy-Support)."""
        words = set()
        # Aus Titel extrahieren
        for word in page_title.lower().split():
            clean = re.sub(r"[^a-zäöüß0-9]", "", word)
            if len(clean) > 3:
                words.add(clean)

        if extra_keywords:
            words.update(k.lower() for k in extra_keywords)

        return ", ".join(sorted(words)[:15])

    def generate_og_tags(
        self,
        title: str,
        description: str,
        url: str,
        site_name: str = "",
        image_url: str = "",
        locale: str = "de_DE",
        page_type: str = "website",
    ) -> dict:
        """Generiere alle Open-Graph-Tags."""
        og = {
            "og:title": title,
            "og:description": description,
            "og:url": url,
            "og:type": page_type,
            "og:locale": locale,
        }

        site = site_name or self.config.get("brand_name", "") or get_site_name(url)
        if site:
            og["og:site_name"] = site

        img = image_url or self.config.get("default_image", "")
        if img:
            og["og:image"] = img
            og["og:image:width"] = str(self.config["og_image_min_width"])
            og["og:image:height"] = str(self.config["og_image_min_height"])
            og["og:image:alt"] = title

        return og

    def generate_twitter_cards(
        self,
        title: str,
        description: str,
        image_url: str = "",
        card_type: str = "summary_large_image",
    ) -> dict:
        """Generiere Twitter Card Tags."""
        twitter = {
            "twitter:card": card_type,
            "twitter:title": truncate(title, 70),
            "twitter:description": truncate(description, 200),
        }

        handle = self.config.get("twitter_handle", "")
        if handle:
            twitter["twitter:site"] = handle if handle.startswith("@") else f"@{handle}"

        img = image_url or self.config.get("default_image", "")
        if img:
            twitter["twitter:image"] = img
            twitter["twitter:image:alt"] = title

        return twitter

    def generate_json_ld(
        self,
        page_type: str,
        title: str,
        description: str,
        url: str,
        image_url: str = "",
        author_name: str = "",
        date_published: str = "",
        date_modified: str = "",
        org_name: str = "",
        org_logo: str = "",
    ) -> dict:
        """Generiere Schema.org JSON-LD."""
        now = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S+00:00")

        if page_type == "article":
            schema = {
                "@context": "https://schema.org",
                "@type": "Article",
                "headline": title,
                "description": description,
                "url": url,
                "datePublished": date_published or now,
                "dateModified": date_modified or now,
            }
            if author_name:
                schema["author"] = {
                    "@type": "Person",
                    "name": author_name,
                }
            if image_url:
                schema["image"] = image_url

        elif page_type == "product":
            schema = {
                "@context": "https://schema.org",
                "@type": "Product",
                "name": title,
                "description": description,
                "url": url,
            }
            if image_url:
                schema["image"] = image_url

        elif page_type == "profile":
            schema = {
                "@context": "https://schema.org",
                "@type": "ProfilePage",
                "name": title,
                "description": description,
                "url": url,
            }
            if author_name:
                schema["mainEntity"] = {
                    "@type": "Person",
                    "name": author_name,
                }

        else:  # website
            schema = {
                "@context": "https://schema.org",
                "@type": "WebSite",
                "name": title,
                "description": description,
                "url": url,
            }

        # Organization hinzufügen
        org = org_name or self.config.get("organization_name", "")
        logo = org_logo or self.config.get("organization_logo", "")
        if org:
            publisher = {
                "@type": "Organization",
                "name": org,
                "url": url,
            }
            if logo:
                publisher["logo"] = {
                    "@type": "ImageObject",
                    "url": logo,
                }
            schema["publisher"] = publisher

        return schema

    def generate_robots(self, index: bool = True, follow: bool = True) -> str:
        """Generiere Robots-Meta-Tag."""
        idx = "index" if index else "noindex"
        flw = "follow" if follow else "nofollow"
        return f"{idx}, {flw}"

    def generate_canonical(self, url: str) -> str:
        """Generiere Canonical-URL."""
        return url

    def generate_hreflang(self, url: str, locales: Optional[list] = None) -> list:
        """Generiere Hreflang-Tags für mehrsprachige Seiten."""
        locales = locales or ["de", "en"]
        tags = []
        for locale in locales:
            lang_map = {
                "de": "de-DE", "de_DE": "de-DE", "de_AT": "de-AT", "de_CH": "de-CH",
                "en": "en-US", "en_US": "en-US", "en_GB": "en-GB",
                "fr": "fr-FR", "fr_FR": "fr-FR",
            }
            lang = lang_map.get(locale, locale)
            tags.append({"lang": lang, "url": url})
        return tags


# ═══════════════════════════════════════════════════════════
# HTML OUTPUT GENERATOR
# ═══════════════════════════════════════════════════════════

class HTMLGenerator:
    """Generiere HTML-Output aus Meta-Daten."""

    @staticmethod
    def render_meta_tags(
        title: str,
        description: str,
        keywords: str,
        robots: str,
        canonical: str,
        og_tags: dict,
        twitter_tags: dict,
        json_ld: dict,
        hreflang: Optional[list] = None,
        extra_head: str = "",
    ) -> str:
        """Generiere kompletten HTML-Head-Block mit allen Meta-Tags."""
        lines = []

        # Kommentar
        lines.append("<!--")
        lines.append("  SEO Meta Tags — Generiert mit SEO Meta Generator Pro")
        lines.append(f"  Erstellt am: {datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M UTC')}")
        lines.append("  https://github.com/owl-digital-factory/seo-meta-generator-pro")
        lines.append("-->")
        lines.append("")

        # Basis-Meta
        lines.append(f'<meta charset="UTF-8">')
        lines.append(f'<meta name="viewport" content="width=device-width, initial-scale=1.0">')
        lines.append(f"<title>{title}</title>")
        lines.append(f'<meta name="description" content="{description}">')
        if keywords:
            lines.append(f'<meta name="keywords" content="{keywords}">')
        lines.append(f'<meta name="robots" content="{robots}">')
        lines.append("")

        # Canonical
        lines.append(f'<link rel="canonical" href="{canonical}">')
        lines.append("")

        # Hreflang
        if hreflang:
            for tag in hreflang:
                lines.append(f'<link rel="alternate" hreflang="{tag["lang"]}" href="{tag["url"]}">')
            lines.append("")

        # Open Graph
        lines.append("<!-- Open Graph / Facebook -->")
        for key, value in og_tags.items():
            lines.append(f'<meta property="{key}" content="{value}">')
        lines.append("")

        # Twitter Cards
        lines.append("<!-- Twitter Cards -->")
        for key, value in twitter_tags.items():
            lines.append(f'<meta name="{key}" content="{value}">')
        lines.append("")

        # JSON-LD
        lines.append("<!-- Schema.org JSON-LD -->")
        lines.append(f'<script type="application/ld+json">')
        lines.append(json.dumps(json_ld, indent=2, ensure_ascii=False))
        lines.append(f"</script>")
        lines.append("")

        # Extra Head
        if extra_head:
            lines.append("<!-- Zusätzliche Head-Elemente -->")
            lines.append(extra_head)
            lines.append("")

        return "\n".join(lines)

    @staticmethod
    def render_full_html(
        meta_block: str,
        body_content: str = "<!-- Dein Seiteninhalt hier -->",
        lang: str = "de",
    ) -> str:
        """Generiere eine vollständige HTML-Seite mit Meta-Tags."""
        return f"""<!DOCTYPE html>
<html lang="{lang}">
<head>
{meta_block}
</head>
<body>
{body_content}
</body>
</html>"""


# ═══════════════════════════════════════════════════════════
# BATCH PROCESSOR
# ═══════════════════════════════════════════════════════════

class BatchProcessor:
    """Verarbeite mehrere URLs oder Konfigurationen."""

    def __init__(self, generator: MetaGenerator):
        self.generator = generator
        self.results = []

    def process_urls_file(self, filepath: str, output_dir: str) -> list:
        """Verarbeite eine Datei mit URLs (eine URL pro Zeile)."""
        path = Path(filepath)
        if not path.exists():
            print(f"❌ Fehler: Datei nicht gefunden: {filepath}")
            return []

        urls = [line.strip() for line in path.read_text().splitlines() if line.strip() and not line.startswith("#")]
        output_path = Path(output_dir)
        output_path.mkdir(parents=True, exist_ok=True)

        results = []
        for i, url in enumerate(urls, 1):
            if not validate_url(url):
                print(f"  ⚠ Überspringe ungültige URL: {url}")
                continue

            print(f"  [{i}/{len(urls)}] Verarbeite: {url}")
            site_name = get_site_name(url)
            title = self.generator.generate_title(site_name, site_name)
            desc = self.generator.generate_description("", title)
            keywords = self.generator.generate_keywords(title)
            robots = self.generator.generate_robots()
            canonical = self.generator.generate_canonical(url)
            og = self.generator.generate_og_tags(title, desc, url, site_name)
            twitter = self.generator.generate_twitter_cards(title, desc)
            json_ld = self.generator.generate_json_ld("website", title, desc, url)

            meta_block = HTMLGenerator.render_meta_tags(
                title, desc, keywords, robots, canonical, og, twitter, json_ld
            )

            slug = slugify(site_name) or f"page-{i}"
            out_file = output_path / f"{slug}_meta.html"
            out_file.write_text(meta_block, encoding="utf-8")
            results.append({"url": url, "file": str(out_file)})

        self.results = results
        return results

    def process_config_file(self, filepath: str, output_dir: str) -> list:
        """Verarbeite eine JSON-Konfigurationsdatei."""
        path = Path(filepath)
        if not path.exists():
            print(f"❌ Fehler: Datei nicht gefunden: {filepath}")
            return []

        configs = json.loads(path.read_text(encoding="utf-8"))
        if isinstance(configs, dict):
            configs = [configs]

        output_path = Path(output_dir)
        output_path.mkdir(parents=True, exist_ok=True)

        results = []
        for i, cfg in enumerate(configs, 1):
            print(f"  [{i}/{len(configs)}] Verarbeite: {cfg.get('title', 'Ohne Titel')}")
            result = self._process_single(cfg)
            results.append(result)

            slug = slugify(cfg.get("title", f"page-{i}"))
            out_file = output_path / f"{slug}_meta.html"
            out_file.write_text(result["html"], encoding="utf-8")

        self.results = results
        return results

    def _process_single(self, cfg: dict) -> dict:
        """Verarbeite eine einzelne Konfiguration."""
        title = self.generator.generate_title(
            cfg.get("title", ""),
            cfg.get("site_name", ""),
            cfg.get("separator", " | "),
        )
        desc = self.generator.generate_description(
            cfg.get("description_hint", ""),
            cfg.get("title", ""),
            cfg.get("keywords", []),
        )
        keywords = self.generator.generate_keywords(
            cfg.get("title", ""),
            cfg.get("keywords", []),
        )
        robots = self.generator.generate_robots(
            cfg.get("index", True),
            cfg.get("follow", True),
        )
        canonical = self.generator.generate_canonical(cfg.get("url", ""))
        og = self.generator.generate_og_tags(
            title, desc,
            cfg.get("url", ""),
            cfg.get("site_name", ""),
            cfg.get("image_url", ""),
            cfg.get("locale", "de_DE"),
            cfg.get("type", "website"),
        )
        twitter = self.generator.generate_twitter_cards(
            title, desc,
            cfg.get("image_url", ""),
            cfg.get("twitter_card_type", "summary_large_image"),
        )
        json_ld = self.generator.generate_json_ld(
            cfg.get("type", "website"),
            title, desc,
            cfg.get("url", ""),
            cfg.get("image_url", ""),
            cfg.get("author_name", ""),
            cfg.get("date_published", ""),
            cfg.get("date_modified", ""),
            cfg.get("org_name", ""),
            cfg.get("org_logo", ""),
        )
        hreflang = None
        if cfg.get("locales"):
            hreflang = self.generator.generate_hreflang(cfg.get("url", ""), cfg["locales"])

        meta_block = HTMLGenerator.render_meta_tags(
            title, desc, keywords, robots, canonical,
            og, twitter, json_ld, hreflang,
            cfg.get("extra_head", ""),
        )

        return {
            "title": title,
            "description": desc,
            "html": meta_block,
        }


# ═══════════════════════════════════════════════════════════
# INTERACTIVE MODE
# ═══════════════════════════════════════════════════════════

def interactive_mode(generator: MetaGenerator):
    """Interaktiver Modus — fragt den User nach allen Infos."""
    print("\n" + "=" * 50)
    print("  SEO Meta Generator Pro — Interaktiver Modus")
    print("=" * 50 + "\n")

    cfg = {}

    cfg["title"] = input("Seitentitel: ").strip()
    if not cfg["title"]:
        print("❌ Titel ist erforderlich.")
        return

    cfg["url"] = input("URL (z.B. https://example.com): ").strip()
    if cfg["url"] and not validate_url(cfg["url"]):
        print("⚠ Ungültige URL — wird übersprungen.")
        cfg["url"] = ""

    cfg["site_name"] = input("Seitenname / Markenname: ").strip()
    cfg["description_hint"] = input("Beschreibungshinweis (optional): ").strip()

    kw_input = input("Keywords (kommagetrennt, optional): ").strip()
    if kw_input:
        cfg["keywords"] = [k.strip() for k in kw_input.split(",") if k.strip()]

    cfg["type"] = input("Seitentype (website/article/product/blog) [website]: ").strip() or "website"
    cfg["image_url"] = input("OG-Bild-URL (optional): ").strip()
    cfg["author_name"] = input("Autorenname (optional): ").strip()
    cfg["org_name"] = input("Organisationsname (optional): ").strip()
    cfg["locale"] = input("Locale (de_DE/en_US/de_AT) [de_DE]: ").strip() or "de_DE"

    # Generiere
    title = generator.generate_title(cfg["title"], cfg.get("site_name", ""))
    desc = generator.generate_description(
        cfg.get("description_hint", ""),
        cfg.get("title", ""),
        cfg.get("keywords"),
    )
    keywords = generator.generate_keywords(cfg.get("title", ""), cfg.get("keywords"))
    robots = generator.generate_robots()
    canonical = generator.generate_canonical(cfg.get("url", ""))
    og = generator.generate_og_tags(
        title, desc,
        cfg.get("url", ""),
        cfg.get("site_name", ""),
        cfg.get("image_url", ""),
        cfg.get("locale", "de_DE"),
        cfg.get("type", "website"),
    )
    twitter = generator.generate_twitter_cards(title, desc, cfg.get("image_url", ""))
    json_ld = generator.generate_json_ld(
        cfg.get("type", "website"),
        title, desc,
        cfg.get("url", ""),
        cfg.get("image_url", ""),
        cfg.get("author_name", ""),
        "", "",
        cfg.get("org_name", ""),
    )

    meta_block = HTMLGenerator.render_meta_tags(
        title, desc, keywords, robots, canonical,
        og, twitter, json_ld,
    )

    print("\n" + "=" * 50)
    print("  GENERIERTE META-TAGS")
    print("=" * 50 + "\n")
    print(meta_block)

    save = input("\nIn Datei speichern? (j/n) [n]: ").strip().lower()
    if save == "j":
        filename = input("Dateiname [meta_output.html]: ").strip() or "meta_output.html"
        Path(filename).write_text(meta_block, encoding="utf-8")
        print(f"✅ Gespeichert: {filename}")


# ═══════════════════════════════════════════════════════════
# CLI INTERFACE
# ═══════════════════════════════════════════════════════════

def build_parser() -> argparse.ArgumentParser:
    """Erstelle den Argument-Parser."""
    parser = argparse.ArgumentParser(
        prog="seo_meta_gen",
        description="SEO Meta Generator Pro — Generiere SEO-optimierte Meta-Tags",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=textwrap.dedent("""\
            Beispiele:
              %(prog)s --url "https://example.com" --title "Meine Seite" --site "Example"
              %(prog)s --title "Blog Post" --desc "Ein interessanter Artikel" --type article
              %(prog)s --batch urls.txt --output-dir ./meta/
              %(prog)s --config pages.json --output-dir ./meta/
              %(prog)s --interactive
        """),
    )

    parser.add_argument("--version", action="version", version=f"SEO Meta Generator Pro v{__version__}")

    # Einzelne Seite
    single = parser.add_argument_group("Einzelseite")
    single.add_argument("--url", "-u", help="URL der Seite")
    single.add_argument("--title", "-t", help="Seitentitel")
    single.add_argument("--desc", "-d", help="Beschreibungshinweis")
    single.add_argument("--site", "-s", help="Seitenname / Markenname")
    single.add_argument("--keywords", "-k", help="Keywords (kommagetrennt)")
    single.add_argument("--type", default="website", choices=DEFAULT_CONFIG["supported_types"],
                        help="Seitentype (default: website)")
    single.add_argument("--image", "-i", help="OG-Bild-URL")
    single.add_argument("--author", "-a", help="Autorenname")
    single.add_argument("--org", "-o", help="Organisationsname")
    single.add_argument("--locale", "-l", default="de_DE", help="Locale (default: de_DE)")
    single.add_argument("--twitter", help="Twitter Handle (ohne @)")
    single.add_argument("--no-index", action="store_true", help="noindex setzen")
    single.add_argument("--no-follow", action="store_true", help="nofollow setzen")

    # Output
    output = parser.add_argument_group("Output")
    output.add_argument("--output", "-O", help="Ausgabedatei (HTML)")
    output.add_argument("--format", "-f", choices=["html", "json", "txt"], default="html",
                        help="Ausgabeformat (default: html)")
    output.add_argument("--full-html", action="store_true", help="Vollständige HTML-Seite generieren")

    # Batch
    batch = parser.add_argument_group("Batch-Verarbeitung")
    batch.add_argument("--batch", "-b", help="Datei mit URLs (eine pro Zeile)")
    batch.add_argument("--config", "-c", help="JSON-Konfigurationsdatei")
    batch.add_argument("--output-dir", help="Ausgabeverzeichnis für Batch")

    # Interaktiv
    parser.add_argument("--interactive", action="store_true", help="Interaktiver Modus")

    return parser


def main():
    """Hauptfunktion."""
    parser = build_parser()
    args = parser.parse_args()

    # Konfiguration
    config = {}
    if args.twitter:
        config["twitter_handle"] = args.twitter

    generator = MetaGenerator(config)
    html_gen = HTMLGenerator()

    # Interaktiver Modus
    if args.interactive:
        interactive_mode(generator)
        return

    # Batch-Modus
    if args.batch or args.config:
        processor = BatchProcessor(generator)
        output_dir = args.output_dir or "./meta_output"

        if args.batch:
            print(f"\n📦 Batch-Verarbeitung: {args.batch}")
            results = processor.process_urls_file(args.batch, output_dir)
        else:
            print(f"\n📦 Config-Verarbeitung: {args.config}")
            results = processor.process_config_file(args.config, output_dir)

        print(f"\n✅ {len(results)} Seiten verarbeitet → {output_dir}/")
        for r in results:
            file_path = r.get("file", "—")
            url = r.get("url", r.get("title", "—"))
            print(f"   {url} → {file_path}")
        return

    # Einzelseite
    if not args.title:
        parser.print_help()
        print("\n⚠  --title ist erforderlich (oder --interactive / --batch / --config)")
        sys.exit(1)

    title = generator.generate_title(args.title, args.site or "")
    desc = generator.generate_description(args.desc or "", args.title, 
                                           [k.strip() for k in args.keywords.split(",")] if args.keywords else None)
    keywords = generator.generate_keywords(args.title, 
                                            [k.strip() for k in args.keywords.split(",")] if args.keywords else None)
    robots = generator.generate_robots(index=not args.no_index, follow=not args.no_follow)
    canonical = generator.generate_canonical(args.url or "")
    og = generator.generate_og_tags(
        title, desc,
        args.url or "",
        args.site or "",
        args.image or "",
        args.locale,
        args.type,
    )
    twitter = generator.generate_twitter_cards(title, desc, args.image or "")
    json_ld = generator.generate_json_ld(
        args.type, title, desc,
        args.url or "",
        args.image or "",
        args.author or "",
        "", "",
        args.org or "",
    )

    # Output
    if args.format == "json":
        output = json.dumps({
            "title": title,
            "description": desc,
            "keywords": keywords,
            "robots": robots,
            "canonical": canonical,
            "og_tags": og,
            "twitter_cards": twitter,
            "json_ld": json_ld,
        }, indent=2, ensure_ascii=False)
    elif args.format == "txt":
        lines = [f"Title: {title}", f"Description: {desc}", f"Keywords: {keywords}",
                 f"Robots: {robots}", f"Canonical: {canonical}", "", "Open Graph:"]
        for k, v in og.items():
            lines.append(f"  {k}: {v}")
        lines.append("\nTwitter Cards:")
        for k, v in twitter.items():
            lines.append(f"  {k}: {v}")
        lines.append(f"\nJSON-LD:\n{json.dumps(json_ld, indent=2, ensure_ascii=False)}")
        output = "\n".join(lines)
    else:  # html
        meta_block = html_gen.render_meta_tags(
            title, desc, keywords, robots, canonical,
            og, twitter, json_ld,
        )
        if args.full_html:
            output = html_gen.render_full_html(meta_block, lang=args.locale.split("_")[0])
        else:
            output = meta_block

    # Ausgabe
    if args.output:
        Path(args.output).write_text(output, encoding="utf-8")
        print(f"✅ Gespeichert: {args.output}")
    else:
        print(output)


if __name__ == "__main__":
    main()
