"""Shared HTTP client with retry, timeout, and robots.txt checking."""

import logging
import time
from urllib.parse import urljoin, urlparse

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

logger = logging.getLogger(__name__)

USER_AGENT = "DeschideScraper/1.0 (https://deschide.md; bot)"
DEFAULT_TIMEOUT = 30
MAX_RETRIES = 2

# Cache parsed robots.txt per domain for the lifetime of the process
_robots_cache: dict[str, set[str]] = {}


def _get_session() -> requests.Session:
    session = requests.Session()
    session.headers.update({"User-Agent": USER_AGENT})

    retry_strategy = Retry(
        total=MAX_RETRIES,
        backoff_factor=1,
        status_forcelist=[429, 500, 502, 503, 504],
        allowed_methods=["GET", "HEAD"],
    )
    adapter = HTTPAdapter(max_retries=retry_strategy)
    session.mount("http://", adapter)
    session.mount("https://", adapter)

    return session


_session: requests.Session | None = None


def get_session() -> requests.Session:
    global _session
    if _session is None:
        _session = _get_session()
    return _session


def _fetch_robots_disallowed(base_url: str) -> set[str]:
    """Fetch and parse robots.txt, returning set of disallowed paths for *."""
    parsed = urlparse(base_url)
    robots_url = f"{parsed.scheme}://{parsed.netloc}/robots.txt"

    try:
        resp = get_session().get(robots_url, timeout=10)
        if resp.status_code != 200:
            return set()
    except requests.RequestException:
        return set()

    disallowed: set[str] = set()
    current_agent = None

    for line in resp.text.splitlines():
        line = line.strip()
        if line.lower().startswith("user-agent:"):
            agent = line.split(":", 1)[1].strip()
            current_agent = agent
        elif line.lower().startswith("disallow:") and current_agent == "*":
            path = line.split(":", 1)[1].strip()
            if path:
                disallowed.add(path)

    return disallowed


def is_allowed_by_robots(url: str) -> bool:
    """Check if URL is allowed by robots.txt rules for wildcard user-agent."""
    parsed = urlparse(url)
    domain = f"{parsed.scheme}://{parsed.netloc}"

    if domain not in _robots_cache:
        _robots_cache[domain] = _fetch_robots_disallowed(domain)

    disallowed = _robots_cache[domain]
    path = parsed.path or "/"

    for rule in disallowed:
        if path.startswith(rule):
            return False

    return True


def fetch_url(url: str, timeout: int = DEFAULT_TIMEOUT) -> requests.Response:
    """Fetch a URL with retries, timeout, and robots.txt check.

    Raises:
        PermissionError: if blocked by robots.txt
        requests.RequestException: on network errors after retries
    """
    if not is_allowed_by_robots(url):
        raise PermissionError(f"Blocked by robots.txt: {url}")

    response = get_session().get(url, timeout=timeout)
    response.raise_for_status()
    return response


def fetch_bytes(url: str, timeout: int = DEFAULT_TIMEOUT, max_size: int = 0) -> bytes:
    """Fetch URL content as bytes, optionally enforcing a size limit.

    Args:
        url: URL to fetch
        timeout: request timeout in seconds
        max_size: max bytes to download (0 = no limit)

    Raises:
        ValueError: if content exceeds max_size
    """
    if not is_allowed_by_robots(url):
        raise PermissionError(f"Blocked by robots.txt: {url}")

    response = get_session().get(url, timeout=timeout, stream=max_size > 0)

    if max_size > 0:
        content_length = response.headers.get("Content-Length")
        if content_length and int(content_length) > max_size:
            response.close()
            raise ValueError(
                f"Content too large: {content_length} bytes (max {max_size})"
            )

        chunks = []
        downloaded = 0
        for chunk in response.iter_content(chunk_size=8192):
            downloaded += len(chunk)
            if downloaded > max_size:
                response.close()
                raise ValueError(
                    f"Content too large: >{downloaded} bytes (max {max_size})"
                )
            chunks.append(chunk)
        return b"".join(chunks)

    response.raise_for_status()
    return response.content
