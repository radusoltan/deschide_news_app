#!/usr/bin/env python3
"""
deschide-scraper CLI — Extracts content from web sources.

Usage:
    python -m deschide_scraper fetch --url=URL --type=TYPE [--limit=N]
    python -m deschide_scraper run --source=KEY | --type=TYPE | --all [--limit=N]
    python -m deschide_scraper list [--type=TYPE]

Types: gov-rss, dom-scraper, pdf-extractor

Output: JSON on stdout (ScraperOutput schema)
Errors/logs: stderr only
"""

import argparse
import json
import logging
import sys
from datetime import datetime, timezone
from pathlib import Path

from deschide_scraper.extractors import EXTRACTORS
from deschide_scraper.models import ScraperOutput
from deschide_scraper.registry import (
    get_enabled_sources,
    get_extractor,
    load_config,
)

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

    # --- fetch: direct URL invocation ---
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

    # --- run: config-based invocation ---
    run_parser = subparsers.add_parser(
        "run", help="Run sources from YAML config"
    )
    run_group = run_parser.add_mutually_exclusive_group(required=True)
    run_group.add_argument(
        "--source", help="Single source key from sources.yaml"
    )
    run_group.add_argument(
        "--type",
        dest="run_type",
        choices=list(EXTRACTORS.keys()),
        help="Run all enabled sources of this type",
    )
    run_group.add_argument(
        "--all", action="store_true", help="Run all enabled sources"
    )
    run_parser.add_argument(
        "--limit", type=int, default=20, help="Max items per source (default: 20)"
    )
    run_parser.add_argument(
        "--config", help="Path to sources.yaml (default: config/sources.yaml)"
    )

    # --- list: show configured sources ---
    list_parser = subparsers.add_parser(
        "list", help="List configured sources"
    )
    list_parser.add_argument(
        "--type",
        dest="list_type",
        choices=list(EXTRACTORS.keys()),
        help="Filter by source type",
    )
    list_parser.add_argument(
        "--config", help="Path to sources.yaml (default: config/sources.yaml)"
    )

    args = parser.parse_args()

    if args.command == "fetch":
        _handle_fetch(args.url, args.type, args.limit)
    elif args.command == "run":
        config_path = args.config or None
        if args.source:
            _handle_run_single(args.source, args.limit, config_path)
        elif args.run_type:
            _handle_run_by_type(args.run_type, args.limit, config_path)
        else:
            _handle_run_all(args.limit, config_path)
    elif args.command == "list":
        _handle_list(getattr(args, "list_type", None), getattr(args, "config", None))


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


def _run_source(
    key: str, cfg: dict, limit: int
) -> ScraperOutput:
    """Run a single source and return its ScraperOutput."""
    source_type = cfg["type"]
    url = cfg["url"]
    errors: list[str] = []
    items = []

    logger.info("Running source '%s' (%s): %s", key, source_type, url)

    try:
        extractor = get_extractor(source_type)
        items = extractor.extract(url, limit=limit)
    except PermissionError as e:
        logger.error("Blocked by robots.txt for %s: %s", key, e)
        errors.append(f"robots.txt: {e}")
    except Exception as e:
        logger.error("Extraction failed for %s: %s", key, e, exc_info=True)
        errors.append(f"{key}: {e}")

    return ScraperOutput(
        source_type=source_type,
        items=items,
        errors=errors,
        scraped_at=datetime.now(timezone.utc),
    )


def _handle_run_single(source_key: str, limit: int, config_path: str | None) -> None:
    sources = load_config(config_path)

    if source_key not in sources:
        valid = ", ".join(sorted(sources.keys()))
        logger.error("Unknown source key '%s'. Available: %s", source_key, valid)
        print(json.dumps({"error": f"Unknown source: {source_key}", "available": sorted(sources.keys())}))
        sys.exit(1)

    cfg = sources[source_key]
    if not cfg.get("enabled", False):
        logger.warning("Source '%s' is disabled in config", source_key)

    output = _run_source(source_key, cfg, limit)
    print(output.model_dump_json(indent=None))

    if output.errors and not output.items:
        sys.exit(1)


def _handle_run_by_type(source_type: str, limit: int, config_path: str | None) -> None:
    sources = get_enabled_sources(config_path, source_type=source_type)

    if not sources:
        logger.warning("No enabled sources found for type '%s'", source_type)
        print(json.dumps([]))
        return

    results = []
    for key, cfg in sources.items():
        output = _run_source(key, cfg, limit)
        results.append(output.model_dump())

    print(json.dumps(results))

    # Exit 1 only if ALL sources failed
    all_failed = all(r["errors"] and not r["items"] for r in results)
    if all_failed:
        sys.exit(1)


def _handle_run_all(limit: int, config_path: str | None) -> None:
    sources = get_enabled_sources(config_path)

    if not sources:
        logger.warning("No enabled sources found in config")
        print(json.dumps([]))
        return

    results = []
    for key, cfg in sources.items():
        output = _run_source(key, cfg, limit)
        results.append(output.model_dump())

    print(json.dumps(results))

    all_failed = all(r["errors"] and not r["items"] for r in results)
    if all_failed:
        sys.exit(1)


def _handle_list(source_type: str | None, config_path: str | None) -> None:
    sources = load_config(config_path)

    entries = []
    for key, cfg in sources.items():
        if source_type and cfg.get("type") != source_type:
            continue
        entries.append({
            "key": key,
            "name": cfg.get("name", ""),
            "url": cfg.get("url", ""),
            "type": cfg.get("type", ""),
            "frequency": cfg.get("frequency", 0),
            "language": cfg.get("language", "ro"),
            "credibility": cfg.get("credibility", 0),
            "enabled": cfg.get("enabled", False),
        })

    print(json.dumps(entries, indent=2))


if __name__ == "__main__":
    main()
