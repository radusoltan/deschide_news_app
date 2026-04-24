"""Tests for ExtractorRegistry — config loading and extractor routing."""

import json
import tempfile
from pathlib import Path

import pytest

from deschide_scraper.extractors.base import BaseExtractor
from deschide_scraper.extractors.dom_scraper import DomScraperExtractor
from deschide_scraper.extractors.gov_rss import GovRssExtractor
from deschide_scraper.extractors.pdf_extractor import PdfExtractor
from deschide_scraper.registry import (
    get_enabled_sources,
    get_extractor,
    load_config,
)

SAMPLE_CONFIG = """\
sources:
  source_a:
    name: "Source A"
    url: "https://example.com/rss.xml"
    type: "gov-rss"
    frequency: 30
    language: "ro"
    credibility: 0.85
    enabled: true

  source_b:
    name: "Source B"
    url: "http://example.com/"
    type: "dom-scraper"
    frequency: 15
    language: "ro"
    credibility: 0.90
    enabled: true

  source_c:
    name: "Source C (disabled)"
    url: "https://example.com/press"
    type: "pdf-extractor"
    frequency: 120
    language: "ro"
    credibility: 0.80
    enabled: false
"""


@pytest.fixture()
def config_file(tmp_path: Path) -> Path:
    """Write sample config to a temp file."""
    path = tmp_path / "sources.yaml"
    path.write_text(SAMPLE_CONFIG)
    return path


class TestLoadConfig:
    def test_loads_all_sources(self, config_file: Path) -> None:
        sources = load_config(config_file)
        assert len(sources) == 3
        assert "source_a" in sources
        assert "source_b" in sources
        assert "source_c" in sources

    def test_source_fields(self, config_file: Path) -> None:
        sources = load_config(config_file)
        a = sources["source_a"]
        assert a["name"] == "Source A"
        assert a["url"] == "https://example.com/rss.xml"
        assert a["type"] == "gov-rss"
        assert a["frequency"] == 30
        assert a["language"] == "ro"
        assert a["credibility"] == 0.85
        assert a["enabled"] is True

    def test_missing_file_raises(self) -> None:
        with pytest.raises(FileNotFoundError):
            load_config("/nonexistent/path/sources.yaml")

    def test_malformed_yaml_raises(self, tmp_path: Path) -> None:
        bad_file = tmp_path / "bad.yaml"
        bad_file.write_text("just_a_string")
        with pytest.raises(ValueError, match="expected 'sources' key"):
            load_config(bad_file)

    def test_missing_sources_key_raises(self, tmp_path: Path) -> None:
        bad_file = tmp_path / "no_sources.yaml"
        bad_file.write_text("other_key:\n  foo: bar\n")
        with pytest.raises(ValueError, match="expected 'sources' key"):
            load_config(bad_file)

    def test_uses_default_path(self) -> None:
        """Default config file should exist in the project."""
        sources = load_config()
        assert isinstance(sources, dict)
        assert len(sources) > 0


class TestGetExtractor:
    def test_gov_rss(self) -> None:
        ext = get_extractor("gov-rss")
        assert isinstance(ext, GovRssExtractor)
        assert isinstance(ext, BaseExtractor)

    def test_dom_scraper(self) -> None:
        ext = get_extractor("dom-scraper")
        assert isinstance(ext, DomScraperExtractor)
        assert isinstance(ext, BaseExtractor)

    def test_pdf_extractor(self) -> None:
        ext = get_extractor("pdf-extractor")
        assert isinstance(ext, PdfExtractor)
        assert isinstance(ext, BaseExtractor)

    def test_invalid_type_raises(self) -> None:
        with pytest.raises(ValueError, match="Unknown extractor type"):
            get_extractor("nonexistent-type")


class TestGetEnabledSources:
    def test_returns_only_enabled(self, config_file: Path) -> None:
        enabled = get_enabled_sources(config_file)
        assert len(enabled) == 2
        assert "source_a" in enabled
        assert "source_b" in enabled
        assert "source_c" not in enabled

    def test_filters_by_type(self, config_file: Path) -> None:
        rss_only = get_enabled_sources(config_file, source_type="gov-rss")
        assert len(rss_only) == 1
        assert "source_a" in rss_only

    def test_no_match_returns_empty(self, config_file: Path) -> None:
        result = get_enabled_sources(config_file, source_type="nonexistent")
        assert result == {}

    def test_disabled_type_not_returned(self, config_file: Path) -> None:
        pdf_only = get_enabled_sources(config_file, source_type="pdf-extractor")
        assert len(pdf_only) == 0  # source_c is disabled
