"""DOM scraper for meteo.md — extracts weather alerts and bulletins."""

import logging
import re
from datetime import datetime, timezone
from typing import Optional
from urllib.parse import urljoin, urlparse

from bs4 import BeautifulSoup, Tag

from deschide_scraper.extractors.base import BaseExtractor
from deschide_scraper.models import ScrapedItem
from deschide_scraper.utils.http_client import fetch_url

logger = logging.getLogger(__name__)


class DomScraperExtractor(BaseExtractor):
    """Extract weather alerts and news from meteo.md and similar DOM-based sites."""

    @property
    def source_type(self) -> str:
        return "dom-scraper"

    def extract(self, url: str, limit: int = 20) -> list[ScrapedItem]:
        logger.info("Fetching DOM page: %s", url)

        response = fetch_url(url)
        response.encoding = response.apparent_encoding or "utf-8"
        html = response.text

        soup = BeautifulSoup(html, "lxml")
        source_name = self._domain_to_source_name(url)

        items: list[ScrapedItem] = []

        # Strategy 1: Look for weather alert blocks (meteo.md specific)
        items.extend(self._extract_alerts(soup, url, source_name))

        # Strategy 2: Look for news/article listings (generic)
        if not items:
            items.extend(self._extract_article_links(soup, url, source_name))

        items = items[:limit]
        logger.info("Extracted %d items from %s", len(items), url)
        return items

    def _extract_alerts(
        self, soup: BeautifulSoup, base_url: str, source_name: str
    ) -> list[ScrapedItem]:
        """Extract weather alert blocks — cod galben/portocaliu/roșu."""
        items: list[ScrapedItem] = []

        # meteo.md uses various patterns for alerts
        # Look for elements with alert-related classes or content
        alert_patterns = [
            # Class-based selectors
            soup.select(".alert, .warning, .meteo-alert, .avertizare"),
            # Look for colored alert blocks
            soup.select('[class*="cod-"], [class*="alert-"], [class*="warning-"]'),
            # Look for specific text patterns in divs
        ]

        seen_texts: set[str] = set()

        for alert_group in alert_patterns:
            for el in alert_group:
                text = el.get_text(separator=" ", strip=True)
                if not text or len(text) < 20:
                    continue
                # Dedup by content
                text_key = text[:100]
                if text_key in seen_texts:
                    continue
                seen_texts.add(text_key)

                title = self._extract_alert_title(text)
                items.append(
                    ScrapedItem(
                        title=title,
                        content=text,
                        source_url=base_url,
                        source_name=source_name,
                        published_at=datetime.now(timezone.utc),
                        language="ro",
                        excerpt=text[:200] if len(text) > 200 else None,
                    )
                )

        # Also look for alert images or special sections
        for section in soup.select("table, .content, #content, main, .main"):
            if not isinstance(section, Tag):
                continue
            text = section.get_text(separator=" ", strip=True)
            # Look for Romanian weather alert keywords
            alert_keywords = [
                "cod galben",
                "cod portocaliu",
                "cod roșu",
                "cod rosu",
                "avertizare",
                "atenționare",
                "atenționare meteorologică",
            ]
            lower_text = text.lower()
            if any(kw in lower_text for kw in alert_keywords):
                text_key = text[:100]
                if text_key in seen_texts:
                    continue
                seen_texts.add(text_key)

                title = self._extract_alert_title(text)
                items.append(
                    ScrapedItem(
                        title=title,
                        content=text,
                        source_url=base_url,
                        source_name=source_name,
                        published_at=datetime.now(timezone.utc),
                        language="ro",
                    )
                )

        return items

    def _extract_article_links(
        self, soup: BeautifulSoup, base_url: str, source_name: str
    ) -> list[ScrapedItem]:
        """Fallback: extract news article links from any news listing page."""
        items: list[ScrapedItem] = []

        # Look for article-like elements
        for article in soup.select(
            "article, .news-item, .article-item, .post, .entry"
        ):
            title_el = article.select_one("h1, h2, h3, h4, a")
            if title_el is None:
                continue

            title = title_el.get_text(strip=True)
            if not title or len(title) < 10:
                continue

            # Find link
            link_el = article.select_one("a[href]")
            link = ""
            if link_el and link_el.get("href"):
                link = urljoin(base_url, str(link_el["href"]))

            # Get content/excerpt
            content = article.get_text(separator="\n", strip=True)

            items.append(
                ScrapedItem(
                    title=title,
                    content=content,
                    source_url=link or base_url,
                    source_name=source_name,
                    language="ro",
                )
            )

        return items

    @staticmethod
    def _extract_alert_title(text: str) -> str:
        """Extract a meaningful title from alert text."""
        # Look for "Cod galben/portocaliu/roșu" pattern
        match = re.search(
            r"(cod\s+(?:galben|portocaliu|roșu|rosu)[^.!]*)",
            text,
            re.IGNORECASE,
        )
        if match:
            title = match.group(1).strip()
            return title[:200]

        # Look for "Avertizare" pattern
        match = re.search(
            r"(avertizare[^.!]*)", text, re.IGNORECASE
        )
        if match:
            return match.group(1).strip()[:200]

        # Fallback: first sentence or first 100 chars
        first_sentence = text.split(".")[0].strip()
        if len(first_sentence) > 200:
            return first_sentence[:197] + "..."
        return first_sentence or text[:100]

    @staticmethod
    def _domain_to_source_name(url: str) -> str:
        parsed = urlparse(url)
        host = parsed.netloc.lower()
        if host.startswith("www."):
            host = host[4:]
        return host
