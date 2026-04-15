"""Output schema for deschide-scraper CLI.

The JSON contract between Python and Symfony PHP.
PythonScraperService.php parses ScraperOutput from stdout.
"""

from datetime import datetime
from typing import Optional

from pydantic import BaseModel, Field


class ScrapedItem(BaseModel):
    """Single scraped content item — maps to PressRelease creation."""

    title: str
    content: str  # Clean HTML or plain text
    source_url: str  # Canonical URL of the article/item
    source_name: str  # e.g. "gov.md", "meteo.md", "procuratura.md"
    published_at: Optional[datetime] = None
    language: str = "ro"
    attachments: list[str] = Field(default_factory=list)  # URLs to PDFs etc.
    excerpt: Optional[str] = None  # Short summary if available


class ScraperOutput(BaseModel):
    """CLI output wrapper — always emitted as a single JSON object on stdout."""

    source_type: str  # "gov-rss", "dom-scraper", "pdf-extractor"
    items: list[ScrapedItem]
    errors: list[str] = Field(default_factory=list)
    scraped_at: datetime
