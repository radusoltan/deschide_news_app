"""Tests for the DOM scraper extractor."""

from unittest.mock import MagicMock, patch

import pytest

from deschide_scraper.extractors.dom_scraper import DomScraperExtractor
from deschide_scraper.models import ScrapedItem

HTML_WITH_ALERTS = """
<html>
<body>
<main>
    <div class="alert">
        <p>Cod galben de ninsori pentru 15 aprilie 2026. Ninsori abundente în nordul
        țării cu acumulare de 10-15 cm. Temperaturi de -5°C pe timp de noapte.</p>
    </div>
    <div class="warning">
        <p>Cod portocaliu de vânt puternic. Rafale de 20-25 m/s în toată țara.</p>
    </div>
    <div class="alert">
        <p>Short.</p>
    </div>
</main>
</body>
</html>
"""

HTML_WITH_TABLE_ALERTS = """
<html>
<body>
<table>
    <tr><td>
        Avertizare meteorologică: cod galben de îngheț în raioanele de nord.
        Temperaturi de -3..-1°C în orele nocturne.
    </td></tr>
</table>
</body>
</html>
"""

HTML_WITH_ARTICLES = """
<html>
<body>
<main>
    <article>
        <h2><a href="/news/article-1">Prognoza meteo pentru săptămâna viitoare</a></h2>
        <p>Temperaturi ridicate sunt așteptate în weekend.</p>
    </article>
    <article>
        <h2><a href="/news/article-2">Avertizare de caniculă pentru sud</a></h2>
        <p>Temperaturi de 35°C în sudul țării.</p>
    </article>
</main>
</body>
</html>
"""

HTML_EMPTY = """
<html>
<body>
<main><p>Nothing here.</p></main>
</body>
</html>
"""


class TestDomScraperExtractor:
    def setup_method(self):
        self.extractor = DomScraperExtractor()

    def test_source_type(self):
        assert self.extractor.source_type == "dom-scraper"

    @patch("deschide_scraper.extractors.dom_scraper.fetch_url")
    def test_extracts_alert_blocks(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = HTML_WITH_ALERTS
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        items = self.extractor.extract("http://www.meteo.md/", limit=10)

        assert len(items) >= 1
        # Should find at least one alert with "cod galben" or "cod portocaliu"
        titles_lower = [item.title.lower() for item in items]
        has_alert = any("cod" in t for t in titles_lower)
        assert has_alert, f"Expected alert in titles: {titles_lower}"

    @patch("deschide_scraper.extractors.dom_scraper.fetch_url")
    def test_extracts_alerts_from_table(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = HTML_WITH_TABLE_ALERTS
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        items = self.extractor.extract("http://www.meteo.md/", limit=10)

        assert len(items) >= 1
        assert "avertizare" in items[0].title.lower() or "cod galben" in items[0].title.lower()

    @patch("deschide_scraper.extractors.dom_scraper.fetch_url")
    def test_fallback_to_article_links(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = HTML_WITH_ARTICLES
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        items = self.extractor.extract("http://www.meteo.md/", limit=10)

        assert len(items) >= 1
        assert items[0].source_name == "meteo.md"

    @patch("deschide_scraper.extractors.dom_scraper.fetch_url")
    def test_empty_page_returns_empty_list(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = HTML_EMPTY
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        items = self.extractor.extract("http://www.meteo.md/", limit=10)

        assert items == []

    @patch("deschide_scraper.extractors.dom_scraper.fetch_url")
    def test_respects_limit(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = HTML_WITH_ARTICLES
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        items = self.extractor.extract("http://www.meteo.md/", limit=1)

        assert len(items) <= 1

    def test_extract_alert_title_cod_galben(self):
        text = "Cod galben de ninsori pentru nordul țării cu temperaturi scăzute"
        title = DomScraperExtractor._extract_alert_title(text)
        assert "cod galben" in title.lower()

    def test_extract_alert_title_avertizare(self):
        text = "Avertizare meteorologică emisă pentru 15 aprilie. Ninsori."
        title = DomScraperExtractor._extract_alert_title(text)
        assert "avertizare" in title.lower()

    def test_extract_alert_title_fallback(self):
        text = "Text without alert keywords but still important."
        title = DomScraperExtractor._extract_alert_title(text)
        assert title == "Text without alert keywords but still important"

    def test_domain_to_source_name(self):
        assert DomScraperExtractor._domain_to_source_name("http://www.meteo.md/") == "meteo.md"
        assert DomScraperExtractor._domain_to_source_name("https://meteo.md/page") == "meteo.md"
