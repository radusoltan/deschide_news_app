#!/usr/bin/env python3
"""
Fetch article pages from thin/moderate sources and analyze their HTML structure
using Gemini CLI. Output: JSON with CSS selectors for each source.
"""
import json
import os
import requests
import subprocess
import sys
import warnings
from bs4 import BeautifulSoup

warnings.filterwarnings("ignore", message="Unverified HTTPS request")

HEADERS = {"User-Agent": "DeschideScraper/1.0 (https://deschide.md; bot)"}


def fetch_page(url):
    """Fetch article page HTML."""
    try:
        r = requests.get(url, timeout=20, headers=HEADERS, verify=False)
        r.raise_for_status()
        r.encoding = r.apparent_encoding or "utf-8"
        return r.text
    except Exception as e:
        return f"ERROR: {e}"


def clean_html_for_analysis(html_text, max_chars=8000):
    """Remove noise from HTML, keep main content area."""
    soup = BeautifulSoup(html_text, "html.parser")

    # Remove noise
    for tag in soup.find_all(["script", "style", "nav", "header", "footer", "aside", "iframe", "noscript"]):
        tag.decompose()

    # Try common main content wrappers
    main = (
        soup.find("main")
        or soup.find("article")
        or soup.find("div", class_="field--name-body")
        or soup.find("div", class_="content")
        or soup.find("div", id="content")
        or soup.find("div", class_="node__content")
        or soup.find("body")
        or soup
    )
    return str(main)[:max_chars]


def analyze_with_gemini(source_key, source_name, url, html_snippet):
    """Send HTML structure to Gemini CLI for CSS selector analysis."""

    prompt = (
        f"Analyze this HTML from a Moldovan government website article page "
        f"(source: {source_name}, URL: {url}).\n\n"
        f"Identify the CSS selectors for:\n"
        f"1. article_title - the main article headline (h1 or similar)\n"
        f"2. article_body - the main article text content (div/section with paragraphs)\n"
        f"3. article_image - the main/featured image (img tag, og:image meta, or figure)\n"
        f"4. article_date - the publication date\n"
        f"5. article_author - the author name (if present)\n\n"
        f"Respond ONLY with a JSON object, no explanation, no markdown fences:\n"
        f'{{"source_key": "{source_key}", "source_name": "{source_name}", '
        f'"selectors": {{"title": "CSS selector", "body": "CSS selector", '
        f'"image": "CSS selector or meta[property=og:image]", '
        f'"date": "CSS selector or null", "author": "CSS selector or null"}}, '
        f'"notes": "any observations"}}\n\n'
        f"HTML:\n{html_snippet}"
    )

    # Write prompt to temp file to avoid shell escaping issues
    prompt_file = f"/tmp/gemini_prompt_{source_key}.txt"
    with open(prompt_file, "w", encoding="utf-8") as f:
        f.write(prompt)

    try:
        process = subprocess.run(
            ["gemini", "-m", "gemini-2.5-flash", "-"],
            stdin=open(prompt_file, "r", encoding="utf-8"),
            capture_output=True,
            text=True,
            timeout=90,
        )

        if process.returncode != 0:
            return {
                "source_key": source_key,
                "source_name": source_name,
                "error": f"Gemini exit code {process.returncode}: {process.stderr[:200]}",
            }

        output = process.stdout.strip()
        # Strip markdown fences if present
        if output.startswith("```"):
            lines = output.split("\n")
            output = "\n".join(
                l for l in lines if not l.strip().startswith("```")
            ).strip()

        return json.loads(output)

    except json.JSONDecodeError as e:
        return {
            "source_key": source_key,
            "source_name": source_name,
            "error": f"JSON parse error: {e}",
            "raw_output": process.stdout[:500] if process else "",
        }
    except subprocess.TimeoutExpired:
        return {
            "source_key": source_key,
            "source_name": source_name,
            "error": "Gemini timeout (90s)",
        }
    except Exception as e:
        return {
            "source_key": source_key,
            "source_name": source_name,
            "error": str(e),
        }
    finally:
        if os.path.exists(prompt_file):
            os.unlink(prompt_file)


def main():
    # THIN sources (avg desc < 200 chars) — MUST enrich
    thin_targets = [
        ("mf", "mf.gov.md",
         "https://mf.gov.md/ro/content/comunicat-privind-rezultatele-licitita%C5%A3iei-din-14042026"),
        ("bnm", "bnm.md",
         "http://www.bnm.md/ro/content/la-washington-dc-inceput-seria-intrevederilor-bilaterale-cadrul-reuniunilor-de-primavara-ale"),
        ("energie", "energie.gov.md",
         "https://energie.gov.md/ro/content/energie-electrica-1"),
        ("mediu", "mediu.gov.md",
         "https://mediu.gov.md/ro/comunicate-de-presa/sedinta-grupurilor-de-lucru-pentru-protectia-mediului-cu-participarea"),
    ]

    # MODERATE sources (200-500 chars) — optional but useful
    moderate_targets = [
        ("gov_md", "gov.md",
         "https://gov.md/ro/content/tara-noastra-pus-bazele-unei-economii-care-creste-sustenabil"),
        ("politia", "politia.md",
         "https://politia.md/ro/content/indreptar-pentru-cetateni-activitatea-igp"),
        ("igsu", "igsu.gov.md",
         "https://igsu.gov.md/ro/content/activitatile-desfasurate-la-nivel-national-pe-parcursul-saptamanii"),
    ]

    all_targets = thin_targets + moderate_targets
    results = []

    for source_key, source_name, article_url in all_targets:
        print(f"[{source_key}] Fetching {article_url}...", file=sys.stderr)

        html = fetch_page(article_url)
        if html.startswith("ERROR"):
            results.append({
                "source_key": source_key,
                "source_name": source_name,
                "error": html,
            })
            print(f"  FETCH ERROR: {html}", file=sys.stderr)
            continue

        cleaned = clean_html_for_analysis(html)
        print(f"  HTML size: {len(html)} -> cleaned: {len(cleaned)}", file=sys.stderr)

        print(f"  Sending to Gemini...", file=sys.stderr)
        analysis = analyze_with_gemini(source_key, source_name, article_url, cleaned)
        results.append(analysis)

        if "error" in analysis:
            print(f"  GEMINI ERROR: {analysis['error']}", file=sys.stderr)
        else:
            selectors = analysis.get("selectors", {})
            print(f"  OK: title={selectors.get('title', 'N/A')}, body={selectors.get('body', 'N/A')}", file=sys.stderr)

    # Output final JSON
    print(json.dumps(results, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
