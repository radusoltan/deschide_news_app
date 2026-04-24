"""Abstract base extractor — all extractors implement this interface."""

from abc import ABC, abstractmethod

from deschide_scraper.models import ScrapedItem


class BaseExtractor(ABC):
    """Base class for content extractors."""

    @abstractmethod
    def extract(self, url: str, limit: int = 20) -> list[ScrapedItem]:
        """Extract content items from the given URL.

        Args:
            url: Source URL to scrape
            limit: Maximum number of items to return

        Returns:
            List of ScrapedItem instances
        """

    @property
    @abstractmethod
    def source_type(self) -> str:
        """Identifier for this extractor type (e.g. 'gov-rss')."""
