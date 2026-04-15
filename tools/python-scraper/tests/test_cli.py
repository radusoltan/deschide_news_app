"""Tests for CLI entry point and models."""

import json
import subprocess
import sys
from datetime import datetime, timezone

import pytest

from deschide_scraper.models import ScrapedItem, ScraperOutput


class TestModels:
    def test_scraped_item_defaults(self):
        item = ScrapedItem(
            title="Test",
            content="Content",
            source_url="https://example.com",
            source_name="example.com",
        )
        assert item.language == "ro"
        assert item.attachments == []
        assert item.published_at is None
        assert item.excerpt is None

    def test_scraped_item_full(self):
        now = datetime.now(timezone.utc)
        item = ScrapedItem(
            title="Ședința Guvernului",
            content="Textul complet",
            source_url="https://gov.md/article/1",
            source_name="gov.md",
            published_at=now,
            language="ro",
            attachments=["https://gov.md/file.pdf"],
            excerpt="Rezumat",
        )
        assert item.title == "Ședința Guvernului"
        assert len(item.attachments) == 1

    def test_scraper_output_serialization(self):
        output = ScraperOutput(
            source_type="gov-rss",
            items=[
                ScrapedItem(
                    title="Test",
                    content="Content",
                    source_url="https://example.com",
                    source_name="example.com",
                )
            ],
            errors=[],
            scraped_at=datetime(2026, 4, 15, 12, 0, 0, tzinfo=timezone.utc),
        )

        json_str = output.model_dump_json()
        parsed = json.loads(json_str)

        assert parsed["source_type"] == "gov-rss"
        assert len(parsed["items"]) == 1
        assert parsed["items"][0]["title"] == "Test"
        assert parsed["errors"] == []

    def test_scraper_output_with_errors(self):
        output = ScraperOutput(
            source_type="dom-scraper",
            items=[],
            errors=["Network timeout", "Parse error"],
            scraped_at=datetime.now(timezone.utc),
        )

        json_str = output.model_dump_json()
        parsed = json.loads(json_str)

        assert len(parsed["errors"]) == 2
        assert parsed["items"] == []


class TestCliEntryPoint:
    def test_missing_arguments_fails(self):
        result = subprocess.run(
            [sys.executable, "-m", "deschide_scraper", "fetch"],
            capture_output=True,
            text=True,
            cwd="/var/www/deschide_news_app/tools/python-scraper",
        )
        assert result.returncode != 0

    def test_invalid_type_fails(self):
        result = subprocess.run(
            [
                sys.executable, "-m", "deschide_scraper", "fetch",
                "--url=https://example.com",
                "--type=invalid-type",
            ],
            capture_output=True,
            text=True,
            cwd="/var/www/deschide_news_app/tools/python-scraper",
        )
        assert result.returncode != 0

    def test_help_flag(self):
        result = subprocess.run(
            [sys.executable, "-m", "deschide_scraper", "--help"],
            capture_output=True,
            text=True,
            cwd="/var/www/deschide_news_app/tools/python-scraper",
        )
        assert result.returncode == 0
        assert "deschide-scraper" in result.stdout or "fetch" in result.stdout
