#!/usr/bin/env python3
"""
V2: Re-analyze sources that failed or got incomplete results,
plus moderate sources with fresh URLs.
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
    try:
        r = requests.get(url, timeout=20, headers=HEADERS, verify=False)
        r.raise_for_status()
        r.encoding = r.apparent_encoding or "utf-8"
        return r.text
    except Exception as e:
        return f"ERROR: {e}"


def clean_html_for_analysis(html_text, max_chars=8000):
    soup = BeautifulSoup(html_text, "html.parser")

    for tag in soup.find_all(["script", "style", "nav", "iframe", "noscript"]):
        tag.decompose()

    # For BNM specifically, keep the body but remove obvious noise
    body = soup.find("body")
    if body:
        # Remove header/footer but keep main
        for tag in body.find_all(["header", "footer"], recursive=False):
            tag.decompose()

    main = (
        soup.find("main")
        or soup.find("article")
        or soup.find("div", {"role": "main"})
        or soup.find("div", id="main-content")
        or soup.find("div", id="content")
        or soup.find("div", class_="region-content")
        or body
        or soup
    )
    return str(main)[:max_chars]


def analyze_with_gemini(source_key, source_name, url, html_snippet):
    prompt = (
        f"You are analyzing the HTML structure of a Moldovan government website "
        f"article page to extract CSS selectors for automated scraping.\n\n"
        f"Source: {source_name}\n"
        f"URL: {url}\n\n"
        f"Find the BEST CSS selectors for these elements:\n"
        f"1. title - the main article headline (usually h1 or h2)\n"
        f"2. body - the container with the full article text (usually a div with paragraphs)\n"
        f"3. image - the featured/main image (img or og:image meta tag)\n"
        f"4. date - publication date element\n"
        f"5. author - author name (null if not present)\n\n"
        f"IMPORTANT: Use simple, robust selectors. Prefer ID selectors > class selectors > tag selectors.\n"
        f"For images, if there's a meta og:image tag, prefer that.\n"
        f"If body content has multiple paragraphs inside a div, select the parent div.\n\n"
        f"Respond with ONLY a valid JSON object (no markdown, no explanation):\n"
        f'{{"source_key": "{source_key}", "source_name": "{source_name}", '
        f'"selectors": {{"title": "selector or null", "body": "selector or null", '
        f'"image": "selector or null", "date": "selector or null", "author": "selector or null"}}, '
        f'"body_extract_method": "text" or "innerHTML", '
        f'"notes": "brief observations"}}\n\n'
        f"HTML:\n{html_snippet}"
    )

    prompt_file = f"/tmp/gemini_prompt_{source_key}.txt"
    with open(prompt_file, "w", encoding="utf-8") as f:
        f.write(prompt)

    try:
        with open(prompt_file, "r", encoding="utf-8") as stdin_f:
            process = subprocess.run(
                ["gemini", "-m", "gemini-2.5-flash", "-"],
                stdin=stdin_f,
                capture_output=True,
                text=True,
                timeout=90,
            )

        if process.returncode != 0:
            return {
                "source_key": source_key,
                "source_name": source_name,
                "error": f"Gemini exit {process.returncode}: {process.stderr[:200]}",
            }

        output = process.stdout.strip()
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
            "error": f"JSON parse: {e}",
            "raw": process.stdout[:800] if process else "",
        }
    except subprocess.TimeoutExpired:
        return {"source_key": source_key, "error": "Gemini timeout"}
    except Exception as e:
        return {"source_key": source_key, "error": str(e)}
    finally:
        if os.path.exists(prompt_file):
            os.unlink(prompt_file)


def main():
    targets = [
        # THIN: re-analyze with better URLs
        ("mf", "mf.gov.md",
         "https://mf.gov.md/ro/content/delega%C8%9Bia-ministerului-finan%C8%9Belor-condus%C4%83-de-ministrul-andrian-gavrili%C8%9B%C4%83-particip%C4%83-la"),
        ("bnm", "bnm.md",
         "http://www.bnm.md/ro/content/guvernatoarea-bnm-doamna-anca-dragu-dialog-cu-lideri-ai-fondului-monetar-international-la"),
        # MODERATE: fresh URLs
        ("gov_md", "gov.md",
         "https://gov.md/ro/comunicate-de-presa/guvernul-accelereaza-dezvoltarea-infrastructurii-regionale-de-gestionare"),
        ("politia", "politia.md",
         "https://politia.md/ro/noutati/dezvoltarea-politiei-nationale-prim-planul-dialogului-dintre-igp-si-afd"),
        ("igsu", "igsu.gov.md",
         "https://igsu.gov.md/ro/comunicate-de-presa/salvatorii-si-pompierii-igsu-au-salvat-o-femeie-dintr-un-incendiu-din-capitala"),
    ]

    results = []

    for source_key, source_name, article_url in targets:
        print(f"[{source_key}] Fetching {article_url[:80]}...", file=sys.stderr)

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
        print(f"  HTML: {len(html)} -> cleaned: {len(cleaned)}", file=sys.stderr)
        print(f"  Gemini...", file=sys.stderr)

        analysis = analyze_with_gemini(source_key, source_name, article_url, cleaned)
        results.append(analysis)

        if "error" in analysis:
            print(f"  ERROR: {analysis['error']}", file=sys.stderr)
        else:
            sel = analysis.get("selectors", {})
            print(f"  OK: title={sel.get('title')}, body={sel.get('body')}", file=sys.stderr)

    print(json.dumps(results, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
