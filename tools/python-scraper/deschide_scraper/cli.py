#!/usr/bin/env python3
"""
deschide-scraper CLI — Extracts content from web sources.

Usage:
    python -m deschide_scraper fetch --url=URL --type=TYPE [--limit=N]

Types: gov-rss, dom-scraper, pdf-extractor

Output: JSON on stdout (ScraperOutput schema)
Errors/logs: stderr only
"""

import argparse
import json
import logging
import sys
from datetime import datetime, timezone

from deschide_scraper.extractors import EXTRACTORS
from deschide_scraper.models import ScraperOutput

# All logging goes to stderr — stdout is reserved for JSON output
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
    stream=sys.stderr,
)
logger = logging.getLogger("deschide_scraper")


def main() -> None:
    parser = argparse.ArgumentParser(
        prog="deschide-scraper",
        description="Extract content from web sources as structured JSON.",
    )
    subparsers = parser.add_subparsers(dest="command", required=True)

    fetch_parser = subparsers.add_parser("fetch", help="Fetch content from a source")
    fetch_parser.add_argument(
        "--url", required=True, help="Source URL to scrape"
    )
    fetch_parser.add_argument(
        "--type",
        required=True,
        choices=list(EXTRACTORS.keys()),
        help="Extractor type",
    )
    fetch_parser.add_argument(
        "--limit", type=int, default=20, help="Max items to return (default: 20)"
    )

    args = parser.parse_args()

    if args.command == "fetch":
        _handle_fetch(args.url, args.type, args.limit)


def _handle_fetch(url: str, extractor_type: str, limit: int) -> None:
    errors: list[str] = []
    items = []

    try:
        extractor_cls = EXTRACTORS[extractor_type]
        extractor = extractor_cls()
        items = extractor.extract(url, limit=limit)
    except PermissionError as e:
        logger.error("Blocked by robots.txt: %s", e)
        errors.append(f"robots.txt: {e}")
    except Exception as e:
        logger.error("Extraction failed: %s", e, exc_info=True)
        errors.append(str(e))

    output = ScraperOutput(
        source_type=extractor_type,
        items=items,
        errors=errors,
        scraped_at=datetime.now(timezone.utc),
    )

    # Emit valid JSON on stdout
    print(output.model_dump_json(indent=None))

    # Exit code: 0 if we produced output (even empty items), 1 only for fatal errors
    if errors and not items:
        sys.exit(1)


if __name__ == "__main__":
    main()
