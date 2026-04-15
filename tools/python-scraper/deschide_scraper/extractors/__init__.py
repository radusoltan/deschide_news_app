from .base import BaseExtractor
from .gov_rss import GovRssExtractor
from .dom_scraper import DomScraperExtractor
from .pdf_extractor import PdfExtractor

EXTRACTORS: dict[str, type[BaseExtractor]] = {
    "gov-rss": GovRssExtractor,
    "dom-scraper": DomScraperExtractor,
    "pdf-extractor": PdfExtractor,
}

__all__ = ["EXTRACTORS", "BaseExtractor"]
