"""Gov.md RSS extractor — parses RSS feeds from *.gov.md domains."""

import logging
import xml.etree.ElementTree as ET
from datetime import datetime, timezone
from email.utils import parsedate_to_datetime
from typing import Optional
from urllib.parse import urlparse

from bs4 import BeautifulSoup

from deschide_scraper.extractors.base import BaseExtractor
from deschide_scraper.models import ScrapedItem
from deschide_scraper.utils.http_client import fetch_url

logger = logging.getLogger(__name__)

# Namespace used by some gov.md feeds for <content:encoded>
CONTENT_NS = "http://purl.org/rss/1.0/modules/content/"


class GovRssExtractor(BaseExtractor):
    """Extract news items from gov.md RSS feeds."""

    @property
    def source_type(self) -> str:
        return "gov-rss"

    def extract(self, url: str, limit: int = 20) -> list[ScrapedItem]:
        logger.info("Fetching RSS feed: %s", url)

        response = fetch_url(url)
        response.encoding = response.apparent_encoding or "utf-8"
        xml_text = response.text

        try:
            root = ET.fromstring(xml_text)
        except ET.ParseError as e:
            logger.error("Failed to parse RSS XML from %s: %s", url, e)
            raise ValueError(f"Invalid RSS XML: {e}") from e

        # Derive source name from domain
        source_name = self._domain_to_source_name(url)

        items: list[ScrapedItem] = []
        for item_el in root.iter("item"):
            if len(items) >= limit:
                break

            parsed = self._parse_item(item_el, source_name)
            if parsed is not None:
                items.append(parsed)

        logger.info("Parsed %d items from %s", len(items), url)
        return items

    def _parse_item(
        self, item_el: ET.Element, source_name: str
    ) -> Optional[ScrapedItem]:
        title = self._text(item_el, "title")
        if not title:
            return None

        link = self._text(item_el, "link") or ""

        # Prefer <content:encoded> over <description>
        content = self._text(item_el, f"{{{CONTENT_NS}}}encoded")
        description = self._text(item_el, "description")

        raw_content = content or description or ""
        clean_content = self._clean_html(raw_content)

        # Build excerpt from description if we have full content
        excerpt = None
        if content and description:
            excerpt = self._clean_html(description)[:300]

        published_at = self._parse_date(self._text(item_el, "pubDate"))

        return ScrapedItem(
            title=title.strip(),
            content=clean_content,
            source_url=link.strip(),
            source_name=source_name,
            published_at=published_at,
            language="ro",
            excerpt=excerpt,
        )

    @staticmethod
    def _text(parent: ET.Element, tag: str) -> Optional[str]:
        el = parent.find(tag)
        return el.text if el is not None and el.text else None

    @staticmethod
    def _clean_html(html: str) -> str:
        """Strip HTML tags but keep text content."""
        soup = BeautifulSoup(html, "lxml")
        return soup.get_text(separator="\n", strip=True)

    @staticmethod
    def _parse_date(date_str: Optional[str]) -> Optional[datetime]:
        if not date_str:
            return None
        try:
            return parsedate_to_datetime(date_str.strip())
        except (ValueError, TypeError):
            pass

        # gov.md sometimes omits timezone — try common formats
        for fmt in (
            "%Y-%m-%dT%H:%M:%S",
            "%Y-%m-%d %H:%M:%S",
            "%d.%m.%Y %H:%M",
            "%d/%m/%Y %H:%M:%S",
        ):
            try:
                dt = datetime.strptime(date_str.strip(), fmt)
                return dt.replace(tzinfo=timezone.utc)
            except ValueError:
                continue

        logger.warning("Could not parse date: %s", date_str)
        return None

    @staticmethod
    def _domain_to_source_name(url: str) -> str:
        parsed = urlparse(url)
        host = parsed.netloc.lower()
        # Remove www. prefix
        if host.startswith("www."):
            host = host[4:]
        return host
