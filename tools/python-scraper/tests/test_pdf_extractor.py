"""Tests for the PDF extractor."""

from unittest.mock import MagicMock, patch

import pytest

from deschide_scraper.extractors.pdf_extractor import PdfExtractor
from deschide_scraper.models import ScrapedItem

HTML_WITH_PDF_LINKS = """
<html>
<body>
<main>
    <article>
        <h2><a href="/press/comunicat-2026-04-10">Comunicat de presă privind investigația</a></h2>
        <p>Procuratura Generală informează despre rezultatele investigației.</p>
        <a href="/files/comunicat-2026-04-10.pdf">Descarcă PDF</a>
    </article>
    <article>
        <h2><a href="/press/comunicat-2026-04-09">Al doilea comunicat de presă</a></h2>
        <p>Informații suplimentare.</p>
    </article>
</main>
</body>
</html>
"""

HTML_WITH_DIRECT_PDFS = """
<html>
<body>
<div class="content">
    <a href="/reports/raport-anual-2025.pdf">Raport anual 2025</a>
    <a href="/reports/statistica.pdf?v=2">Statistică</a>
    <a href="/page/about">Despre noi</a>
</div>
</body>
</html>
"""

HTML_ARTICLE_PAGE = """
<html>
<body>
<article class="article-content">
    <h1>Comunicat de presă</h1>
    <p>Procuratura Generală a finalizat cercetarea penală în dosarul de fraudă bancară.</p>
    <p>Ancheta a durat 18 luni și a implicat cooperare internațională.</p>
    <a href="/files/decizie-finala.pdf">Decizia completă (PDF)</a>
</article>
</body>
</html>
"""

HTML_NO_CONTENT = """
<html><body><div>Nothing useful here.</div></body></html>
"""


class TestPdfExtractor:
    def setup_method(self):
        self.extractor = PdfExtractor()

    def test_source_type(self):
        assert self.extractor.source_type == "pdf-extractor"

    @patch("deschide_scraper.extractors.pdf_extractor.fetch_url")
    def test_finds_press_links(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = HTML_WITH_PDF_LINKS
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        from bs4 import BeautifulSoup
        soup = BeautifulSoup(HTML_WITH_PDF_LINKS, "lxml")
        links = self.extractor._find_press_links(soup, "https://procuratura.md")

        assert len(links) >= 1
        urls = [url for url, _ in links]
        assert any("comunicat-2026-04-10" in u for u in urls)

    def test_find_pdf_links(self):
        from bs4 import BeautifulSoup
        soup = BeautifulSoup(HTML_WITH_DIRECT_PDFS, "lxml")
        pdfs = PdfExtractor._find_pdf_links(soup, "https://procuratura.md")

        assert len(pdfs) == 2
        assert any("raport-anual-2025.pdf" in p for p in pdfs)
        assert any("statistica.pdf" in p for p in pdfs)

    def test_find_pdf_links_none(self):
        from bs4 import BeautifulSoup
        soup = BeautifulSoup(HTML_NO_CONTENT, "lxml")
        pdfs = PdfExtractor._find_pdf_links(soup, "https://example.com")

        assert pdfs == []

    @patch("deschide_scraper.extractors.pdf_extractor.fetch_url")
    def test_process_article_page_with_text(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = HTML_ARTICLE_PAGE
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        # Patch _extract_pdf_text to avoid actual PDF download
        with patch.object(self.extractor, "_extract_pdf_text", return_value="PDF content here"):
            item = self.extractor._process_article_page(
                "https://procuratura.md/press/comunicat",
                "Comunicat de presă",
                "procuratura.md",
            )

        assert item is not None
        assert item.title == "Comunicat de presă"
        assert "fraudă bancară" in item.content
        assert len(item.attachments) == 1
        assert "decizie-finala.pdf" in item.attachments[0]

    @patch("deschide_scraper.extractors.pdf_extractor.fetch_url")
    def test_process_article_skips_short_content(self, mock_fetch):
        mock_response = MagicMock()
        mock_response.text = HTML_NO_CONTENT
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        item = self.extractor._process_article_page(
            "https://procuratura.md/empty",
            "Empty page",
            "procuratura.md",
        )

        assert item is None

    @patch("deschide_scraper.extractors.pdf_extractor.fetch_bytes")
    def test_extract_pdf_text_size_limit(self, mock_fetch_bytes):
        mock_fetch_bytes.side_effect = ValueError("Content too large")

        result = PdfExtractor._extract_pdf_text("https://example.com/huge.pdf")
        assert result is None

    def test_domain_to_source_name(self):
        assert PdfExtractor._domain_to_source_name("https://www.procuratura.md/press") == "procuratura.md"
        assert PdfExtractor._domain_to_source_name("https://procuratura.md") == "procuratura.md"

    @patch("deschide_scraper.extractors.pdf_extractor.fetch_url")
    def test_respects_limit(self, mock_fetch):
        """Ensure the extractor stops after reaching the limit."""
        mock_response = MagicMock()
        mock_response.text = HTML_WITH_PDF_LINKS
        mock_response.apparent_encoding = "utf-8"
        mock_fetch.return_value = mock_response

        # Patch _process_article_page to return a valid item
        with patch.object(
            self.extractor,
            "_process_article_page",
            return_value=ScrapedItem(
                title="Test",
                content="A" * 100,
                source_url="https://example.com",
                source_name="test",
            ),
        ):
            items = self.extractor.extract("https://procuratura.md", limit=1)

        assert len(items) <= 1
