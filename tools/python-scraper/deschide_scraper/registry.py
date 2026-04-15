"""
Extractor registry — loads sources.yaml, routes to correct extractor by type.
"""

import logging
from pathlib import Path
from typing import Any

import yaml

from deschide_scraper.extractors import EXTRACTORS
from deschide_scraper.extractors.base import BaseExtractor

logger = logging.getLogger(__name__)

DEFAULT_CONFIG_PATH = Path(__file__).parent.parent / "config" / "sources.yaml"


def load_config(config_path: Path | str | None = None) -> dict[str, dict[str, Any]]:
    """Load sources.yaml and return the 'sources' dict.

    Args:
        config_path: Path to sources.yaml. Uses default if None.

    Returns:
        Dict of source_key → source_config.

    Raises:
        FileNotFoundError: if config file does not exist.
        ValueError: if YAML is malformed or missing 'sources' key.
    """
    path = Path(config_path) if config_path else DEFAULT_CONFIG_PATH

    if not path.exists():
        raise FileNotFoundError(f"Config file not found: {path}")

    with open(path) as f:
        raw = yaml.safe_load(f)

    if not isinstance(raw, dict) or "sources" not in raw:
        raise ValueError(f"Invalid config: expected 'sources' key in {path}")

    sources = raw["sources"]
    if not isinstance(sources, dict):
        raise ValueError(f"Invalid config: 'sources' must be a mapping in {path}")

    return sources


def get_extractor(source_type: str) -> BaseExtractor:
    """Get an extractor instance for the given source type.

    Args:
        source_type: One of 'gov-rss', 'dom-scraper', 'pdf-extractor'.

    Returns:
        Instantiated BaseExtractor subclass.

    Raises:
        ValueError: if source_type is not registered.
    """
    if source_type not in EXTRACTORS:
        valid = ", ".join(sorted(EXTRACTORS.keys()))
        raise ValueError(
            f"Unknown extractor type '{source_type}'. Valid types: {valid}"
        )

    return EXTRACTORS[source_type]()


def get_enabled_sources(
    config_path: Path | str | None = None,
    source_type: str | None = None,
) -> dict[str, dict[str, Any]]:
    """Return enabled sources, optionally filtered by type.

    Args:
        config_path: Path to sources.yaml. Uses default if None.
        source_type: If set, only return sources matching this type.

    Returns:
        Dict of source_key → source_config for enabled sources.
    """
    sources = load_config(config_path)

    result = {}
    for key, cfg in sources.items():
        if not cfg.get("enabled", False):
            continue
        if source_type and cfg.get("type") != source_type:
            continue
        result[key] = cfg

    return result
