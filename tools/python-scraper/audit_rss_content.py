#!/usr/bin/env python3
"""Audit RSS feed content richness for all enabled gov.md sources."""

import json
import requests
import xml.etree.ElementTree as ET
from bs4 import BeautifulSoup
import warnings

warnings.filterwarnings("ignore", message="Unverified HTTPS request")

CONTENT_NS = "http://purl.org/rss/1.0/modules/content/"

SOURCES = [
    ("gov_md", "gov.md", "https://gov.md/ro/rss.xml"),
    ("mai", "mai.gov.md", "https://mai.gov.md/ro/rss.xml"),
    ("mf", "mf.gov.md", "https://mf.gov.md/ro/rss.xml"),
    ("justice", "justice.gov.md", "https://justice.gov.md/ro/rss.xml"),
    ("mec", "mec.gov.md", "https://mec.gov.md/ro/rss.xml"),
    ("mecc", "mecc.gov.md", "https://mecc.gov.md/ro/rss.xml"),
    ("madrm", "madrm.gov.md", "https://madrm.gov.md/ro/rss.xml"),
    ("maia", "maia.gov.md", "https://maia.gov.md/ro/rss.xml"),
    ("politia", "politia.md", "https://politia.md/ro/rss.xml"),
    ("igsu", "igsu.gov.md", "https://igsu.gov.md/ro/rss.xml"),
    ("bnm", "bnm.md", "https://www.bnm.md/ro/rss.xml"),
    ("cna", "cna.md", "https://cna.md/ro/rss.xml"),
    ("sis", "sis.md", "https://sis.md/ro/rss.xml"),
    ("agepi", "agepi.gov.md", "https://agepi.gov.md/ro/rss.xml"),
    ("mc", "mc.gov.md", "https://mc.gov.md/ro/rss.xml"),
    ("energie", "energie.gov.md", "https://energie.gov.md/ro/rss.xml"),
    ("mediu", "mediu.gov.md", "https://mediu.gov.md/ro/rss.xml"),
    ("carabinier", "carabinier.gov.md", "https://carabinier.gov.md/ro/rss.xml"),
    ("cnas", "cnas.gov.md", "https://cnas.gov.md/ro/rss.xml"),
    ("anofm", "anofm.md", "https://anofm.md/ro/rss.xml"),
    ("ism", "ism.gov.md", "https://ism.gov.md/ro/rss.xml"),
    ("am", "am.gov.md", "https://am.gov.md/ro/rss.xml"),
    ("caa", "caa.md", "https://www.caa.md/ro/rss.xml"),
    ("asp", "asp.gov.md", "https://asp.gov.md/ro/rss.xml"),
]


def analyze_source(key, name, url):
    result = {
        "key": key,
        "domain": name,
        "url": url,
        "status": "error",
        "item_count": 0,
        "avg_desc_len": 0,
        "desc_lengths": [],
        "has_content_encoded": False,
        "has_enclosure": False,
        "sample_links": [],
        "sample_desc": "",
    }

    try:
        r = requests.get(
            url, timeout=15,
            headers={"User-Agent": "DeschideScraper/1.0"},
            verify=False,
        )
        r.raise_for_status()
        r.encoding = r.apparent_encoding or "utf-8"
        root = ET.fromstring(r.text)
    except Exception as e:
        result["error"] = str(e)[:120]
        return result

    items = list(root.iter("item"))[:5]
    result["item_count"] = len(items)

    desc_lens = []
    for item in items:
        # Description
        desc_el = item.find("description")
        desc_text = desc_el.text if desc_el is not None and desc_el.text else ""
        clean = BeautifulSoup(desc_text, "html.parser").get_text().strip()
        desc_lens.append(len(clean))

        # content:encoded
        content_el = item.find(f"{{{CONTENT_NS}}}encoded")
        if content_el is not None and content_el.text and len(content_el.text.strip()) > 10:
            result["has_content_encoded"] = True

        # enclosure (image)
        encl = item.find("enclosure")
        if encl is not None:
            result["has_enclosure"] = True

        # link
        link_el = item.find("link")
        if link_el is not None and link_el.text:
            result["sample_links"].append(link_el.text.strip())

        # Keep first short desc as sample
        if not result["sample_desc"] and clean:
            result["sample_desc"] = clean[:200]

    result["desc_lengths"] = desc_lens
    result["avg_desc_len"] = int(sum(desc_lens) / len(desc_lens)) if desc_lens else 0

    # Classify
    if result["has_content_encoded"]:
        result["status"] = "OK_FULL"
    elif result["avg_desc_len"] >= 500:
        result["status"] = "OK_DESC"
    elif result["avg_desc_len"] >= 200:
        result["status"] = "MODERATE"
    else:
        result["status"] = "THIN"

    return result


def main():
    results = []
    thin_sources = []

    print("=" * 100)
    print(f"{'KEY':<15} {'DOMAIN':<25} {'#':>3} {'AVG_DESC':>8} {'CONTENT:ENC':>11} {'ENCL':>5} {'STATUS':>10}")
    print("-" * 100)

    for key, name, url in SOURCES:
        r = analyze_source(key, name, url)
        results.append(r)

        if r["status"] == "error":
            print(f"{key:<15} {name:<25} {'ERR':>3} {'':>8} {'':>11} {'':>5} {'ERROR':>10}")
            print(f"  >> {r.get('error', 'unknown')}")
        else:
            print(
                f"{key:<15} {name:<25} {r['item_count']:>3} "
                f"{r['avg_desc_len']:>8} {str(r['has_content_encoded']):>11} "
                f"{str(r['has_enclosure']):>5} {r['status']:>10}"
            )

        if r["status"] == "THIN":
            thin_sources.append(r)
            if r["sample_links"]:
                print(f"  >> LINK: {r['sample_links'][0]}")
            if r["sample_desc"]:
                print(f"  >> DESC: {r['sample_desc'][:120]}...")

    print("=" * 100)
    print(f"\nSUMMARY: {len(thin_sources)} THIN sources out of {len(results)} total")
    print("\nTHIN SOURCES (need article page enrichment):")
    for t in thin_sources:
        print(f"  - {t['key']} ({t['domain']}) — avg {t['avg_desc_len']} chars")
        for link in t["sample_links"][:2]:
            print(f"    {link}")

    # Save full results to JSON
    with open("/tmp/rss_audit_results.json", "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2, ensure_ascii=False, default=str)
    print("\nFull results saved to /tmp/rss_audit_results.json")


if __name__ == "__main__":
    main()
