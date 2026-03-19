#!/usr/bin/env python3
"""
Bing Webmaster Tools - Analiza Crawl Stats
Genereaza raport detaliat pentru deschide.md
"""

import requests
import json
from datetime import datetime

API_KEY = "4618fc76b5904d97a50f5ec5e3a9c458"
SITE_URL = "https://www.deschide.md/"
BASE_URL = "https://ssl.bing.com/webmaster/api.svc/json"

def make_request(method, params=None):
    url = f"{BASE_URL}/{method}?apikey={API_KEY}"
    if params:
        url += "&" + "&".join([f"{k}={v}" for k, v in params.items()])
    try:
        response = requests.get(url, timeout=30)
        response.raise_for_status()
        return response.json()
    except Exception as e:
        print(f"Error: {e}")
        return None

def parse_date(date_str):
    """Parse Bing date format"""
    if date_str and '/Date(' in str(date_str):
        try:
            timestamp = int(date_str.replace('/Date(', '').replace(')/', '').split('-')[0]) / 1000
            return datetime.fromtimestamp(timestamp)
        except:
            pass
    return None

def analyze_crawl_stats():
    """Analyze crawl statistics"""
    result = make_request("GetCrawlStats", {"siteUrl": SITE_URL})

    if not result or 'd' not in result:
        print("Nu s-au putut obtine date.")
        return

    data = result['d']

    print("=" * 70)
    print("RAPORT BING WEBMASTER TOOLS - deschide.md")
    print("=" * 70)
    print(f"Data raport: {datetime.now().strftime('%Y-%m-%d %H:%M')}")
    print(f"Site: {SITE_URL}")
    print("=" * 70)

    # Get latest stats
    latest = data[-1] if data else None
    oldest = data[0] if data else None

    if latest:
        print("\n" + "=" * 70)
        print("SUMAR INDEXARE BING")
        print("=" * 70)

        latest_date = parse_date(latest.get('Date'))
        oldest_date = parse_date(oldest.get('Date'))

        print(f"\nPerioada analizata: {oldest_date.strftime('%Y-%m-%d') if oldest_date else 'N/A'} - {latest_date.strftime('%Y-%m-%d') if latest_date else 'N/A'}")
        print(f"Total zile: {len(data)}")

        print("\n" + "-" * 50)
        print("METRICI PRINCIPALE (ultima zi)")
        print("-" * 50)
        print(f"  Pagini in Index:       {latest.get('InIndex', 0):,}")
        print(f"  Backlink-uri (InLinks): {latest.get('InLinks', 0):,}")
        print(f"  Pagini Crawled/zi:     {latest.get('CrawledPages', 0):,}")

        print("\n" + "-" * 50)
        print("HTTP STATUS CODES (cumulative)")
        print("-" * 50)
        print(f"  2xx (Success):         {latest.get('Code2xx', 0):,}")
        print(f"  301 (Redirect):        {latest.get('Code301', 0):,}")
        print(f"  4xx (Client Error):    {latest.get('Code4xx', 0):,}")
        print(f"  5xx (Server Error):    {latest.get('Code5xx', 0):,}")
        print(f"  Other Codes:           {latest.get('AllOtherCodes', 0):,}")

        print("\n" + "-" * 50)
        print("PROBLEME DETECTATE")
        print("-" * 50)
        print(f"  Crawl Errors:          {latest.get('CrawlErrors', 0):,}")
        print(f"  Blocked by robots.txt: {latest.get('BlockedByRobotsTxt', 0):,}")
        print(f"  DNS Failures:          {latest.get('DnsFailures', 0):,}")
        print(f"  Connection Timeout:    {latest.get('ConnectionTimeout', 0):,}")
        print(f"  Contains Malware:      {latest.get('ContainsMalware', 0):,}")

        # Calculate trends
        if len(data) >= 7:
            week_ago = data[-7]

            index_change = latest.get('InIndex', 0) - week_ago.get('InIndex', 0)
            links_change = latest.get('InLinks', 0) - week_ago.get('InLinks', 0)
            errors_change = latest.get('CrawlErrors', 0) - week_ago.get('CrawlErrors', 0)

            print("\n" + "-" * 50)
            print("TENDINTE (ultimele 7 zile)")
            print("-" * 50)
            print(f"  Pagini indexate:  {'+' if index_change >= 0 else ''}{index_change:,}")
            print(f"  Backlink-uri:     {'+' if links_change >= 0 else ''}{links_change:,}")
            print(f"  Erori crawl:      {'+' if errors_change >= 0 else ''}{errors_change:,}")

        # Average crawl rate
        total_crawled = sum(d.get('CrawledPages', 0) for d in data)
        avg_crawl = total_crawled / len(data) if data else 0

        print("\n" + "-" * 50)
        print("RATA DE CRAWL")
        print("-" * 50)
        print(f"  Media zilnica:         {avg_crawl:,.0f} pagini/zi")
        print(f"  Total pagini crawled:  {total_crawled:,}")

        # Health Score
        total_responses = (latest.get('Code2xx', 0) + latest.get('Code301', 0) +
                         latest.get('Code4xx', 0) + latest.get('Code5xx', 0) +
                         latest.get('AllOtherCodes', 0))

        if total_responses > 0:
            success_rate = (latest.get('Code2xx', 0) / total_responses) * 100
            redirect_rate = (latest.get('Code301', 0) / total_responses) * 100
            error_rate = ((latest.get('Code4xx', 0) + latest.get('Code5xx', 0)) / total_responses) * 100

            print("\n" + "-" * 50)
            print("HEALTH SCORE")
            print("-" * 50)
            print(f"  Success Rate (2xx):    {success_rate:.1f}%")
            print(f"  Redirect Rate (301):   {redirect_rate:.1f}%")
            print(f"  Error Rate (4xx+5xx):  {error_rate:.1f}%")

            # Overall health
            health = "EXCELLENT" if error_rate < 2 else "BUN" if error_rate < 5 else "NECESITA ATENTIE"
            print(f"\n  Status General:        {health}")

    # Daily breakdown (last 14 days)
    print("\n" + "=" * 70)
    print("EVOLUTIE ZILNICA (ultimele 14 zile)")
    print("=" * 70)
    print(f"{'Data':<12} {'Index':>10} {'InLinks':>10} {'Crawled':>10} {'Errors':>10}")
    print("-" * 52)

    for day in data[-14:]:
        date = parse_date(day.get('Date'))
        date_str = date.strftime('%Y-%m-%d') if date else 'N/A'
        print(f"{date_str:<12} {day.get('InIndex', 0):>10,} {day.get('InLinks', 0):>10,} {day.get('CrawledPages', 0):>10,} {day.get('CrawlErrors', 0):>10,}")

    # Recommendations
    print("\n" + "=" * 70)
    print("RECOMANDARI SEO PENTRU BING")
    print("=" * 70)

    recommendations = []

    if latest.get('Code4xx', 0) > 1000:
        recommendations.append("- URGENT: Exista multe erori 4xx. Verifica paginile 404 si seteaza redirects.")

    if latest.get('Code5xx', 0) > 100:
        recommendations.append("- ATENTIE: Erori server 5xx detectate. Verifica stabilitatea serverului.")

    if latest.get('Code301', 0) > latest.get('Code2xx', 0):
        recommendations.append("- INFO: Multe redirect-uri 301. Considera actualizarea link-urilor interne.")

    if latest.get('CrawlErrors', 0) > 500:
        recommendations.append("- ATENTIE: Multe erori de crawl. Verifica robots.txt si sitemap.xml.")

    if not recommendations:
        recommendations.append("- Site-ul este sanatos! Continua monitorizarea regulata.")

    for rec in recommendations:
        print(rec)

    print("\n" + "=" * 70)
    print("ACTIUNI RECOMANDATE")
    print("=" * 70)
    print("1. Submite sitemap.xml in Bing Webmaster Tools")
    print("2. Verifica si rezolva erorile 4xx din raportul de URL-uri")
    print("3. Optimizeaza meta tags pentru cuvintele cheie targetate")
    print("4. Asigura-te ca site-ul este mobile-friendly")
    print("5. Implementeaza structured data (JSON-LD) - DONE!")
    print("=" * 70)

if __name__ == "__main__":
    analyze_crawl_stats()
