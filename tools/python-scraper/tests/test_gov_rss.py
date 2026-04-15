"""Tests for the GovRss extractor."""

import json
from datetime import datetime, timezone
from unittest.mock import MagicMock, patch

import pytest

from deschide_scraper.extractors.gov_rss import GovRssExtractor
from deschide_scraper.models import ScrapedItem

SAMPLE_RSS = """<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title>Gov.md</title>
    <link>https://gov.md</link>
    <item>
      <title>Ședința Guvernului din 10 aprilie 2026</title>
      <link>https://gov.md/ro/content/sedinta-guvernului-10-aprilie</link>
      <description>&lt;p&gt;Guvernul a aprobat mai multe proiecte.&lt;/p&gt;</description>
      <pubDate>Thu, 10 Apr 2026 14:00:00 +0300</pubDate>
    </item>
    <item>
      <title>Declarație de presă</title>
      <link>https://gov.md/ro/content/declaratie-presa</link>
      <content:encoded>&lt;p&gt;Textul complet al declarației.&lt;/p&gt;</content:encoded>
      <description>&lt;p&gt;Rezumat scurt.&lt;/p&gt;</description>
      <pubDate>Wed, 09 Apr 2026 10:30:00 +0300</pubDate>
    </item>
    <item>
      <title></title>
      <link>https://gov.md/ro/content/empty</link>
      <description>Should be skipped — empty title</description>
    </item>
  </channel>
</rss>"""

SAMPLE_RSS_NO_DATES = """<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Gov.md</title>
    <item>
      <title>Articol fără dată</title>
      <link>https://gov.md/ro/content/fara-data</link>
      <description>Conținut simplu</description>
    </item>
  </channel>
</rss>"""


class TestGovRssExtractor:
    def setup_method(self):
        self.extractor = GovRssExtractor()

    def test_source_type(self):
        assert self.extractor.source_type == "gov-rss"

    @patch("deschide_scraper.extractors.gov_rss.fetch_url")
    def test_extracts_items_from_rss(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = SAMPLE_RSS
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        items = self.extractor.extract("https://gov.md/ro/rss.xml", limit=10)

        assert len(items) == 2  # Third item has empty title → skipped
        assert isinstance(items[0], ScrapedItem)
        assert items[0].title == "Ședința Guvernului din 10 aprilie 2026"
        assert items[0].source_url == "https://gov.md/ro/content/sedinta-guvernului-10-aprilie"
        assert items[0].source_name == "gov.md"
        assert items[0].language == "ro"
        assert "Guvernul a aprobat" in items[0].content

    @patch("deschide_scraper.extractors.gov_rss.fetch_url")
    def test_prefers_content_encoded_over_description(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = SAMPLE_RSS
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        items = self.extractor.extract("https://gov.md/ro/rss.xml", limit=10)

        # Second item has both content:encoded and description
        assert "Textul complet" in items[1].content
        assert items[1].excerpt is not None
        assert "Rezumat scurt" in items[1].excerpt

    @patch("deschide_scraper.extractors.gov_rss.fetch_url")
    def test_respects_limit(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = SAMPLE_RSS
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        items = self.extractor.extract("https://gov.md/ro/rss.xml", limit=1)

        assert len(items) == 1

    @patch("deschide_scraper.extractors.gov_rss.fetch_url")
    def test_handles_missing_dates(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = SAMPLE_RSS_NO_DATES
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        items = self.extractor.extract("https://gov.md/ro/rss.xml")

        assert len(items) == 1
        assert items[0].published_at is None

    @patch("deschide_scraper.extractors.gov_rss.fetch_url")
    def test_parses_pubdate_correctly(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = SAMPLE_RSS
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        items = self.extractor.extract("https://gov.md/ro/rss.xml")

        assert items[0].published_at is not None
        assert items[0].published_at.year == 2026
        assert items[0].published_at.month == 4
        assert items[0].published_at.day == 10

    @patch("deschide_scraper.extractors.gov_rss.fetch_url")
    def test_invalid_xml_raises_value_error(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = "<not valid xml"
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        with pytest.raises(ValueError, match="Invalid RSS XML"):
            self.extractor.extract("https://gov.md/ro/rss.xml")

    @patch("deschide_scraper.extractors.gov_rss.fetch_url")
    def test_source_name_from_subdomain(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = SAMPLE_RSS_NO_DATES
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        items = self.extractor.extract("https://mfa.gov.md/ro/rss.xml")

        assert items[0].source_name == "mfa.gov.md"

    def test_clean_html_strips_tags(self):
        result = GovRssExtractor._clean_html("<p>Hello <b>world</b></p>")
        assert "Hello" in result
        assert "world" in result
        assert "<" not in result

    def test_parse_date_various_formats(self):
        # RFC 2822
        dt = GovRssExtractor._parse_date("Thu, 10 Apr 2026 14:00:00 +0300")
        assert dt is not None
        assert dt.year == 2026

        # ISO-like without timezone
        dt = GovRssExtractor._parse_date("2026-04-10T14:00:00")
        assert dt is not None

        # Unparseable
        dt = GovRssExtractor._parse_date("not a date")
        assert dt is None

        # None
        dt = GovRssExtractor._parse_date(None)
        assert dt is None
