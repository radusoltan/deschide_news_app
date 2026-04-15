# deschide-scraper

CLI tool that extracts content from web sources and returns structured JSON on stdout.
Invoked by Symfony via `PythonScraperService` (same pattern as Gemini/Claude CLI).

## Usage

```bash
python3 -m deschide_scraper fetch --url=URL --type=TYPE [--limit=N]
```

### Extractor types

| Type | Target | Description |
|------|--------|-------------|
| `gov-rss` | `*.gov.md/ro/rss.xml` | RSS feed parser for gov.md platform |
| `dom-scraper` | `meteo.md` | DOM scraper for weather alerts |
| `pdf-extractor` | `procuratura.md` | Press page + PDF text extraction |

### Examples

```bash
# Gov.md RSS
python3 -m deschide_scraper fetch --url=https://gov.md/ro/rss.xml --type=gov-rss --limit=5

# Meteo.md alerts
python3 -m deschide_scraper fetch --url=http://www.meteo.md/ --type=dom-scraper

# Procuratura.md press releases
python3 -m deschide_scraper fetch --url=https://procuratura.md --type=pdf-extractor --limit=3
```

## Output format

JSON on stdout (`ScraperOutput` schema). Logs go to stderr.

```json
{
  "source_type": "gov-rss",
  "items": [
    {
      "title": "...",
      "content": "...",
      "source_url": "...",
      "source_name": "gov.md",
      "published_at": "2026-04-15T12:00:00Z",
      "language": "ro",
      "attachments": [],
      "excerpt": null
    }
  ],
  "errors": [],
  "scraped_at": "2026-04-15T12:00:00Z"
}
```

## Symfony integration

```bash
# Via Symfony console
symfony console app:test:python-scraper --url=https://gov.md/ro/rss.xml --type=gov-rss --limit=3
symfony console app:test:python-scraper --url=https://gov.md/ro/rss.xml --type=gov-rss --raw
```

## Development

```bash
pip3 install -r requirements.txt --break-system-packages
python3 -m pytest tests/ -v
```

## Architecture

See ADR-012: CLI tool invoked by Symfony Process, NOT a microservice.
