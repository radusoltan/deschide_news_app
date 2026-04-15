"""PDF content extractor — scrapes press pages and extracts PDF text content."""

import io
import logging
import re
from datetime import datetime, timezone
from typing import Optional
from urllib.parse import urljoin, urlparse

from bs4 import BeautifulSoup, Tag

from deschide_scraper.extractors.base import BaseExtractor
from deschide_scraper.models import ScrapedItem
from deschide_scraper.utils.http_client import fetch_bytes, fetch_url

logger = logging.getLogger(__name__)

MAX_PDF_SIZE = 5 * 1024 * 1024  # 5 MB


class PdfExtractor(BaseExtractor):
    """Extract content from press pages that include PDF attachments."""

    @property
    def source_type(self) -> str:
        return "pdf-extractor"

    def extract(self, url: str, limit: int = 20) -> list[ScrapedItem]:
        logger.info("Fetching press page: %s", url)

        response = fetch_url(url)
        response.encoding = response.apparent_encoding or "utf-8"
        html = response.text
        soup = BeautifulSoup(html, "lxml")
        source_name = self._domain_to_source_name(url)

        items: list[ScrapedItem] = []

        # Step 1: Find press/news listing links on the page
        article_links = self._find_press_links(soup, url)
        logger.info("Found %d press links on %s", len(article_links), url)

        # Step 2: Process each article page
        for article_url, article_title in article_links[:limit]:
            try:
                item = self._process_article_page(
                    article_url, article_title, source_name
                )
                if item is not None:
                    items.append(item)
                    if len(items) >= limit:
                        break
            except Exception as e:
                logger.warning(
                    "Failed to process article %s: %s", article_url, e
                )

        # If no article links found, try to extract PDFs directly from the main page
        if not items:
            pdf_links = self._find_pdf_links(soup, url)
            for pdf_url in pdf_links[:limit]:
                try:
                    item = self._extract_from_pdf(pdf_url, source_name)
                    if item is not None:
                        items.append(item)
                        if len(items) >= limit:
                            break
                except Exception as e:
                    logger.warning("Failed to extract PDF %s: %s", pdf_url, e)

        logger.info("Extracted %d items from %s", len(items), url)
        return items

    def _find_press_links(
        self, soup: BeautifulSoup, base_url: str
    ) -> list[tuple[str, str]]:
        """Find links to individual press releases / news articles."""
        links: list[tuple[str, str]] = []
        seen_urls: set[str] = set()

        # Common press/news section selectors
        selectors = [
            ".news-list a",
            ".press-release a",
            ".articles a",
            ".post-list a",
            "article a",
            ".news-item a",
            ".content a",
            "#content a",
            "main a",
            ".list-news a",
        ]

        for selector in selectors:
            for link_el in soup.select(selector):
                href = link_el.get("href")
                if not href or not isinstance(href, str):
                    continue

                full_url = urljoin(base_url, href)

                # Skip non-HTTP links, anchors, and same-page links
                if not full_url.startswith(("http://", "https://")):
                    continue
                if full_url in seen_urls:
                    continue

                # Skip obvious non-article links
                path = urlparse(full_url).path.lower()
                if any(
                    skip in path
                    for skip in [
                        "/tag/",
                        "/category/",
                        "/search",
                        "/login",
                        "/register",
                        ".css",
                        ".js",
                        ".jpg",
                        ".png",
                    ]
                ):
                    continue

                title = link_el.get_text(strip=True)
                if not title or len(title) < 10:
                    continue

                seen_urls.add(full_url)
                links.append((full_url, title))

        return links

    def _process_article_page(
        self, url: str, title: str, source_name: str
    ) -> Optional[ScrapedItem]:
        """Fetch an article page, extract text content and any PDF attachments."""
        response = fetch_url(url)
        response.encoding = response.apparent_encoding or "utf-8"
        soup = BeautifulSoup(response.text, "lxml")

        # Extract main text content
        content_el = soup.select_one(
            "article, .article-content, .post-content, .content, .entry-content, main"
        )
        if content_el is None:
            content_el = soup.select_one("body")

        text_content = ""
        if content_el is not None:
            text_content = content_el.get_text(separator="\n", strip=True)

        # Find PDF attachments
        pdf_links = self._find_pdf_links(soup, url)
        attachments: list[str] = []
        pdf_text_parts: list[str] = []

        for pdf_url in pdf_links[:3]:  # max 3 PDFs per article
            attachments.append(pdf_url)
            try:
                extracted = self._extract_pdf_text(pdf_url)
                if extracted:
                    pdf_text_parts.append(extracted)
            except Exception as e:
                logger.warning("Failed to extract PDF text from %s: %s", pdf_url, e)

        # Combine page text with PDF content
        full_content = text_content
        if pdf_text_parts:
            full_content += "\n\n---\n\n".join([""] + pdf_text_parts)

        if not full_content or len(full_content.strip()) < 50:
            return None

        return ScrapedItem(
            title=title[:255],
            content=full_content.strip(),
            source_url=url,
            source_name=source_name,
            language="ro",
            attachments=attachments,
            excerpt=text_content[:300] if text_content else None,
        )

    def _extract_from_pdf(
        self, pdf_url: str, source_name: str
    ) -> Optional[ScrapedItem]:
        """Extract content directly from a PDF URL."""
        text = self._extract_pdf_text(pdf_url)
        if not text or len(text.strip()) < 50:
            return None

        # Use first line or first 200 chars as title
        lines = text.strip().split("\n")
        title = lines[0][:255] if lines else pdf_url.split("/")[-1]

        return ScrapedItem(
            title=title,
            content=text.strip(),
            source_url=pdf_url,
            source_name=source_name,
            language="ro",
            attachments=[pdf_url],
        )

    @staticmethod
    def _find_pdf_links(soup: BeautifulSoup, base_url: str) -> list[str]:
        """Find all PDF links on a page."""
        pdf_urls: list[str] = []
        seen: set[str] = set()

        for link in soup.select('a[href$=".pdf"], a[href*=".pdf?"]'):
            href = link.get("href")
            if not href or not isinstance(href, str):
                continue
            full_url = urljoin(base_url, href)
            if full_url not in seen:
                seen.add(full_url)
                pdf_urls.append(full_url)

        return pdf_urls

    @staticmethod
    def _extract_pdf_text(pdf_url: str) -> Optional[str]:
        """Download a PDF and extract its text content using pdfplumber."""
        import pdfplumber

        logger.info("Downloading PDF: %s", pdf_url)

        try:
            pdf_bytes = fetch_bytes(pdf_url, max_size=MAX_PDF_SIZE)
        except ValueError as e:
            logger.warning("PDF too large, skipping: %s (%s)", pdf_url, e)
            return None

        pages_text: list[str] = []

        with pdfplumber.open(io.BytesIO(pdf_bytes)) as pdf:
            for page in pdf.pages:
                text = page.extract_text()
                if text:
                    pages_text.append(text)

        if not pages_text:
            return None

        return "\n\n".join(pages_text)

    @staticmethod
    def _domain_to_source_name(url: str) -> str:
        parsed = urlparse(url)
        host = parsed.netloc.lower()
        if host.startswith("www."):
            host = host[4:]
        return host
