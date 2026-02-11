#!/usr/bin/env python3
"""
Bing Webmaster Tools API Report Generator
Extracts search performance data for deschide.md
"""

import requests
import json
from datetime import datetime, timedelta

# Configuration
API_KEY = "4618fc76b5904d97a50f5ec5e3a9c458"
SITE_URL = "https://www.deschide.md/"
BASE_URL = "https://ssl.bing.com/webmaster/api.svc/json"

def make_request(method, params=None):
    """Make API request to Bing Webmaster Tools"""
    url = f"{BASE_URL}/{method}?apikey={API_KEY}"
    if params:
        url += "&" + "&".join([f"{k}={v}" for k, v in params.items()])

    try:
        response = requests.get(url, timeout=30)
        response.raise_for_status()
        return response.json()
    except requests.exceptions.RequestException as e:
        print(f"Error making request to {method}: {e}")
        return None

def get_sites():
    """Get list of verified sites"""
    print("\n" + "="*60)
    print("SITE-URI VERIFICATE")
    print("="*60)

    result = make_request("GetUserSites")
    if result:
        print(json.dumps(result, indent=2))
        return result
    return []

def get_query_stats():
    """Get search query statistics"""
    print("\n" + "="*60)
    print("STATISTICI INTEROGARI DE CAUTARE (Query Stats)")
    print("="*60)

    result = make_request("GetQueryStats", {"siteUrl": SITE_URL})
    if result:
        if isinstance(result, list) and len(result) > 0:
            print(f"\nTotal interogari: {len(result)}")
            print("\nTop 20 cuvinte cheie:")
            print("-"*80)
            print(f"{'Query':<40} {'Clicks':>10} {'Impressions':>12} {'CTR':>8} {'Pos':>6}")
            print("-"*80)

            # Sort by clicks descending
            sorted_queries = sorted(result, key=lambda x: x.get('Clicks', 0), reverse=True)[:20]

            for q in sorted_queries:
                query = q.get('Query', 'N/A')[:38]
                clicks = q.get('Clicks', 0)
                impressions = q.get('Impressions', 0)
                ctr = (clicks / impressions * 100) if impressions > 0 else 0
                position = q.get('AvgClickPosition', 0)
                print(f"{query:<40} {clicks:>10} {impressions:>12} {ctr:>7.2f}% {position:>6.1f}")

            # Calculate totals
            total_clicks = sum(q.get('Clicks', 0) for q in result)
            total_impressions = sum(q.get('Impressions', 0) for q in result)
            avg_ctr = (total_clicks / total_impressions * 100) if total_impressions > 0 else 0

            print("-"*80)
            print(f"{'TOTAL':<40} {total_clicks:>10} {total_impressions:>12} {avg_ctr:>7.2f}%")

            return result
        else:
            print("Nu s-au gasit date pentru interogari.")
    return []

def get_page_stats():
    """Get page statistics"""
    print("\n" + "="*60)
    print("STATISTICI PAGINI (Page Stats)")
    print("="*60)

    result = make_request("GetPageStats", {"siteUrl": SITE_URL})
    if result:
        if isinstance(result, list) and len(result) > 0:
            print(f"\nTotal pagini: {len(result)}")
            print("\nTop 20 pagini performante:")
            print("-"*100)
            print(f"{'URL':<60} {'Clicks':>10} {'Impressions':>12}")
            print("-"*100)

            # Sort by clicks descending
            sorted_pages = sorted(result, key=lambda x: x.get('Clicks', 0), reverse=True)[:20]

            for p in sorted_pages:
                url = p.get('Query', 'N/A')
                # Truncate URL for display
                if len(url) > 58:
                    url = url[:55] + "..."
                clicks = p.get('Clicks', 0)
                impressions = p.get('Impressions', 0)
                print(f"{url:<60} {clicks:>10} {impressions:>12}")

            return result
        else:
            print("Nu s-au gasit date pentru pagini.")
    return []

def get_traffic_stats():
    """Get traffic statistics"""
    print("\n" + "="*60)
    print("STATISTICI TRAFIC (Traffic Stats)")
    print("="*60)

    result = make_request("GetRankAndTrafficStats", {"siteUrl": SITE_URL})
    if result:
        if isinstance(result, list) and len(result) > 0:
            print(f"\nDate trafic pe zile (ultimele 30):")
            print("-"*60)
            print(f"{'Data':<15} {'Clicks':>10} {'Impressions':>15}")
            print("-"*60)

            for day in result[-30:]:
                date = day.get('Date', 'N/A')
                if date and date != 'N/A':
                    # Parse the date if it's in timestamp format
                    try:
                        if '/Date(' in str(date):
                            timestamp = int(date.replace('/Date(', '').replace(')/', '')) / 1000
                            date = datetime.fromtimestamp(timestamp).strftime('%Y-%m-%d')
                    except:
                        pass
                clicks = day.get('Clicks', 0)
                impressions = day.get('Impressions', 0)
                print(f"{str(date):<15} {clicks:>10} {impressions:>15}")

            return result
        else:
            print("Nu s-au gasit date pentru trafic.")
    return []

def get_crawl_stats():
    """Get crawl statistics"""
    print("\n" + "="*60)
    print("STATISTICI CRAWL (Indexare)")
    print("="*60)

    result = make_request("GetCrawlStats", {"siteUrl": SITE_URL})
    if result:
        print(json.dumps(result, indent=2))
        return result
    return []

def get_keyword_stats():
    """Get keyword statistics"""
    print("\n" + "="*60)
    print("STATISTICI CUVINTE CHEIE (Keyword Stats)")
    print("="*60)

    result = make_request("GetKeywordStats", {"siteUrl": SITE_URL})
    if result:
        if isinstance(result, list) and len(result) > 0:
            print(f"\nCuvinte cheie principale:")
            print("-"*50)
            for kw in result[:30]:
                keyword = kw.get('Query', kw.get('Keyword', 'N/A'))
                impressions = kw.get('Impressions', kw.get('BroadImpressions', 0))
                print(f"  {keyword}: {impressions} impressions")
            return result
        else:
            print("Nu s-au gasit date pentru cuvinte cheie.")
    return []

def get_query_page_stats():
    """Get query and page combined stats"""
    print("\n" + "="*60)
    print("STATISTICI INTEROGARI + PAGINI")
    print("="*60)

    result = make_request("GetQueryPageStats", {"siteUrl": SITE_URL})
    if result:
        if isinstance(result, list) and len(result) > 0:
            print(f"\nTotal combinatii query-pagina: {len(result)}")
            print("\nTop 15 combinatii:")
            print("-"*110)
            print(f"{'Query':<35} {'URL':<45} {'Clicks':>8} {'Impr':>10}")
            print("-"*110)

            sorted_data = sorted(result, key=lambda x: x.get('Clicks', 0), reverse=True)[:15]
            for item in sorted_data:
                query = item.get('Query', 'N/A')[:33]
                url = item.get('Page', 'N/A')
                if len(url) > 43:
                    url = url[:40] + "..."
                clicks = item.get('Clicks', 0)
                impressions = item.get('Impressions', 0)
                print(f"{query:<35} {url:<45} {clicks:>8} {impressions:>10}")

            return result
        else:
            print("Nu s-au gasit date combinate.")
    return []

def generate_summary(queries, pages, traffic):
    """Generate summary report"""
    print("\n" + "="*60)
    print("REZUMAT PERFORMANTA BING - deschide.md")
    print("="*60)

    if queries:
        total_clicks = sum(q.get('Clicks', 0) for q in queries)
        total_impressions = sum(q.get('Impressions', 0) for q in queries)
        avg_ctr = (total_clicks / total_impressions * 100) if total_impressions > 0 else 0
        unique_queries = len(queries)

        print(f"\n{'Metrici Principale':}")
        print(f"  - Total Clicks: {total_clicks:,}")
        print(f"  - Total Impressions: {total_impressions:,}")
        print(f"  - CTR Mediu: {avg_ctr:.2f}%")
        print(f"  - Interogari Unice: {unique_queries:,}")

    if pages:
        total_pages = len(pages)
        top_page = max(pages, key=lambda x: x.get('Clicks', 0)) if pages else None
        print(f"\n{'Pagini':}")
        print(f"  - Total Pagini Indexate: {total_pages:,}")
        if top_page:
            print(f"  - Pagina Top: {top_page.get('Query', 'N/A')[:50]}")

def main():
    print("="*60)
    print("RAPORT BING WEBMASTER TOOLS")
    print(f"Site: {SITE_URL}")
    print(f"Data: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*60)

    # Get all available data
    sites = get_sites()
    queries = get_query_stats()
    pages = get_page_stats()
    traffic = get_traffic_stats()
    crawl = get_crawl_stats()
    keywords = get_keyword_stats()
    query_pages = get_query_page_stats()

    # Generate summary
    generate_summary(queries, pages, traffic)

    print("\n" + "="*60)
    print("RAPORT COMPLET")
    print("="*60)

if __name__ == "__main__":
    main()
