#!/usr/bin/env python3
"""
Microsoft Clarity - Data Export API
Generates analytics report for deschide.md
"""

import requests
import json
from datetime import datetime

# Configuration
API_TOKEN = "eyJhbGciOiJSUzI1NiIsImtpZCI6IjQ4M0FCMDhFNUYwRDMxNjdEOTRFMTQ3M0FEQTk2RTcyRDkwRUYwRkYiLCJ0eXAiOiJKV1QifQ.eyJqdGkiOiIzOWQxYzNmYS0wNTFjLTRkNzEtYWQ5YS02YTY2MzA2MWM4NDYiLCJzdWIiOiIyNDk4NDkwMDA0MTAzNjI1Iiwic2NvcGUiOiJEYXRhLkV4cG9ydCIsIm5iZiI6MTc2NTUzNzE0NSwiZXhwIjo0OTE5MTM3MTQ1LCJpYXQiOjE3NjU1MzcxNDUsImlzcyI6ImNsYXJpdHkiLCJhdWQiOiJjbGFyaXR5LmRhdGEtZXhwb3J0ZXIifQ.f73SDuK_eG0hQCfkGuNKmuXVgPFS2NC1EqQK7p2uLYbnuX8E3rn6U7Sw6hmK1f1L7nnYs7ULeQghaXyEWvfg4qjuzJ5tF-yGhUF40Fk8lzhseTaIbIFucreKpAciXVZ4RYnk__l8F7r56XB2mRrVCtKAPaGnaktvdxa172vxppIxWPRS4HtJ_4LpflL-UuwUoG3Z2gtNwvko4d9FOSt60IuxG8oQBWmNXXWosXu86bKwdnaA0wCFHxitSxirtwcnz5ysmCG0C0qb1lQ4_nhFOwHxtgjzIgrMu8O4g9d7O8ic-jbZGdHIDV4U9F8qyRWfbwnFRJqTH9Pa2l_qP0DFpA"
PROJECT_ID = "oln2gnccll"
BASE_URL = "https://www.clarity.ms/export-data/api/v1/project-live-insights"

def make_request(num_days=3, dimension1=None, dimension2=None, dimension3=None):
    """Make API request to Clarity"""
    headers = {
        "Authorization": f"Bearer {API_TOKEN}",
        "Content-Type": "application/json"
    }

    params = {"numOfDays": str(num_days)}

    if dimension1:
        params["dimension1"] = dimension1
    if dimension2:
        params["dimension2"] = dimension2
    if dimension3:
        params["dimension3"] = dimension3

    try:
        response = requests.get(BASE_URL, headers=headers, params=params, timeout=30)
        response.raise_for_status()
        return response.json()
    except requests.exceptions.HTTPError as e:
        if response.status_code == 401:
            print("Error 401: Token invalid sau expirat")
        elif response.status_code == 403:
            print("Error 403: Token nu are permisiuni")
        elif response.status_code == 429:
            print("Error 429: Limita zilnica de 10 request-uri depasita")
        else:
            print(f"HTTP Error: {e}")
        return None
    except Exception as e:
        print(f"Error: {e}")
        return None

def format_number(n):
    """Format number with thousands separator"""
    try:
        return f"{int(float(n)):,}"
    except:
        return str(n)

def print_metric(data, metric_name):
    """Print a specific metric from the response"""
    for item in data:
        if item.get('metricName') == metric_name:
            print(f"\n--- {metric_name} ---")
            info = item.get('information', [])
            if isinstance(info, list):
                for entry in info[:10]:  # Top 10
                    # Print all available fields
                    fields = []
                    for key, value in entry.items():
                        if key not in ['metricName']:
                            if isinstance(value, (int, float)) or (isinstance(value, str) and value.replace('.','',1).isdigit()):
                                fields.append(f"{key}: {format_number(value)}")
                            else:
                                fields.append(f"{key}: {value}")
                    if fields:
                        print("  " + " | ".join(fields))
            return info
    return []

def analyze_traffic(data):
    """Analyze traffic metrics"""
    print("\n" + "=" * 70)
    print("ANALIZA TRAFIC")
    print("=" * 70)

    for item in data:
        if item.get('metricName') == 'Traffic':
            info = item.get('information', [])

            total_sessions = 0
            total_bot_sessions = 0
            total_users = 0

            for entry in info:
                sessions = int(float(entry.get('totalSessionCount', 0)))
                bots = int(float(entry.get('totalBotSessionCount', 0)))
                users = int(float(entry.get('distantUserCount', 0)))

                total_sessions += sessions
                total_bot_sessions += bots
                total_users += users

            print(f"\n  Total Sesiuni:      {format_number(total_sessions)}")
            print(f"  Sesiuni Bot:        {format_number(total_bot_sessions)}")
            print(f"  Utilizatori Unici:  {format_number(total_users)}")

            if total_sessions > 0:
                bot_rate = (total_bot_sessions / total_sessions) * 100
                print(f"  Rata Boturi:        {bot_rate:.1f}%")

            return info
    return []

def analyze_devices(data):
    """Analyze device distribution"""
    print("\n" + "=" * 70)
    print("DISTRIBUTIE DISPOZITIVE")
    print("=" * 70)

    for item in data:
        if item.get('metricName') == 'Traffic':
            info = item.get('information', [])

            # Group by device/OS
            devices = {}
            for entry in info:
                device = entry.get('Device', entry.get('OS', 'Unknown'))
                sessions = int(float(entry.get('totalSessionCount', 0)))
                devices[device] = devices.get(device, 0) + sessions

            # Sort by sessions
            sorted_devices = sorted(devices.items(), key=lambda x: x[1], reverse=True)

            total = sum(d[1] for d in sorted_devices)

            print("\n  Dispozitiv/OS         Sesiuni      Procent")
            print("  " + "-" * 45)

            for device, sessions in sorted_devices[:10]:
                pct = (sessions / total * 100) if total > 0 else 0
                print(f"  {device:<20} {sessions:>10,}   {pct:>6.1f}%")

            return sorted_devices
    return []

def analyze_ux_issues(data):
    """Analyze UX issues (rage clicks, dead clicks, etc.)"""
    print("\n" + "=" * 70)
    print("PROBLEME UX DETECTATE")
    print("=" * 70)

    issues = {
        'Dead Click Count': 0,
        'Rage Click Count': 0,
        'Quickback Click': 0,
        'Script Error Count': 0,
        'Error Click Count': 0,
        'Excessive Scroll': 0
    }

    for item in data:
        metric_name = item.get('metricName', '')
        if metric_name in issues:
            info = item.get('information', [])
            for entry in info:
                # Sum up counts
                for key, value in entry.items():
                    if 'count' in key.lower() or key == metric_name:
                        try:
                            issues[metric_name] += int(float(value))
                        except:
                            pass

    print("\n  Problema                  Count       Severitate")
    print("  " + "-" * 50)

    severity_thresholds = {
        'Dead Click Count': (100, 500),  # warning, critical
        'Rage Click Count': (50, 200),
        'Quickback Click': (100, 300),
        'Script Error Count': (10, 50),
        'Error Click Count': (50, 200),
        'Excessive Scroll': (100, 300)
    }

    for issue, count in issues.items():
        warn, crit = severity_thresholds.get(issue, (100, 500))
        if count >= crit:
            severity = "CRITICAL"
        elif count >= warn:
            severity = "WARNING"
        else:
            severity = "OK"

        print(f"  {issue:<25} {count:>8,}   {severity}")

    return issues

def analyze_scroll_depth(data):
    """Analyze scroll depth"""
    print("\n" + "=" * 70)
    print("SCROLL DEPTH (Adancime Derulare)")
    print("=" * 70)

    for item in data:
        if item.get('metricName') == 'Scroll Depth':
            info = item.get('information', [])

            print("\n  Procent Pagina      Utilizatori")
            print("  " + "-" * 35)

            for entry in info[:10]:
                for key, value in entry.items():
                    if key not in ['metricName']:
                        print(f"  {key:<20} {value}")

            return info
    return []

def analyze_engagement(data):
    """Analyze engagement time"""
    print("\n" + "=" * 70)
    print("TIMP DE ENGAGEMENT")
    print("=" * 70)

    for item in data:
        if item.get('metricName') == 'Engagement Time':
            info = item.get('information', [])

            for entry in info:
                for key, value in entry.items():
                    if key not in ['metricName']:
                        print(f"  {key}: {value}")

            return info
    return []

def analyze_popular_pages(data):
    """Analyze popular pages"""
    print("\n" + "=" * 70)
    print("PAGINI POPULARE")
    print("=" * 70)

    for item in data:
        if item.get('metricName') == 'Popular Pages':
            info = item.get('information', [])

            print("\n  Top 10 Pagini:")
            print("  " + "-" * 60)

            for i, entry in enumerate(info[:10], 1):
                url = entry.get('URL', entry.get('url', 'N/A'))
                if len(url) > 55:
                    url = url[:52] + "..."
                views = entry.get('views', entry.get('pageViews', 'N/A'))
                print(f"  {i}. {url}")
                if views != 'N/A':
                    print(f"     Views: {views}")

            return info
    return []

def generate_recommendations(traffic_data, ux_issues, device_data):
    """Generate SEO/UX recommendations based on data"""
    print("\n" + "=" * 70)
    print("RECOMANDARI BAZATE PE DATE CLARITY")
    print("=" * 70)

    recommendations = []

    # Check bot traffic
    if traffic_data:
        total_sessions = sum(int(float(e.get('totalSessionCount', 0))) for e in traffic_data)
        bot_sessions = sum(int(float(e.get('totalBotSessionCount', 0))) for e in traffic_data)
        if total_sessions > 0:
            bot_rate = (bot_sessions / total_sessions) * 100
            if bot_rate > 30:
                recommendations.append(f"- ATENTIE: Rata trafic bot ridicata ({bot_rate:.1f}%). Verifica surse spam.")

    # Check UX issues
    if ux_issues.get('Rage Click Count', 0) > 100:
        recommendations.append("- UX PROBLEM: Multe rage clicks detectate. Utilizatorii se frustreaza cu elemente neresponsive.")

    if ux_issues.get('Dead Click Count', 0) > 200:
        recommendations.append("- UX PROBLEM: Multe dead clicks. Elementele par clickable dar nu sunt.")

    if ux_issues.get('Script Error Count', 0) > 20:
        recommendations.append("- TECHNICAL: Erori JavaScript detectate. Verifica consola browser pentru detalii.")

    if ux_issues.get('Quickback Click', 0) > 100:
        recommendations.append("- UX PROBLEM: Utilizatorii dau back rapid. Continutul nu corespunde asteptarilor.")

    # Check mobile
    if device_data:
        mobile_sessions = sum(s for d, s in device_data if d.lower() in ['android', 'ios', 'mobile'])
        total = sum(s for _, s in device_data)
        if total > 0:
            mobile_rate = (mobile_sessions / total) * 100
            if mobile_rate > 60:
                recommendations.append(f"- MOBILE: {mobile_rate:.0f}% trafic mobil. Asigura-te ca experienta mobile este optima.")

    if not recommendations:
        recommendations.append("- Site-ul are performanta UX buna bazat pe datele Clarity!")

    for rec in recommendations:
        print(rec)

def main():
    print("=" * 70)
    print("RAPORT MICROSOFT CLARITY - deschide.md")
    print("=" * 70)
    print(f"Data raport: {datetime.now().strftime('%Y-%m-%d %H:%M')}")
    print(f"Project ID: {PROJECT_ID}")
    print(f"Perioada: Ultimele 3 zile")
    print("=" * 70)

    # Note: Limited to 10 API calls per day
    # We'll make strategic requests to get the most data

    print("\n[1/4] Extragere date trafic si dispozitive...")
    data_devices = make_request(num_days=3, dimension1="Device")

    if not data_devices:
        print("\nERORE: Nu s-au putut obtine date de la API.")
        print("Posibile cauze:")
        print("  - Token invalid sau expirat")
        print("  - Limita de 10 requesturi/zi depasita")
        print("  - Proiectul nu are date suficiente")
        return

    # Save raw data for debugging
    with open('/var/www/deschide_news_app/scripts/clarity_raw_data.json', 'w') as f:
        json.dump(data_devices, f, indent=2)
    print("  [Saved raw data to clarity_raw_data.json]")

    # Analyze traffic
    traffic_info = analyze_traffic(data_devices)

    # Analyze devices
    device_info = analyze_devices(data_devices)

    print("\n[2/4] Extragere date OS...")
    data_os = make_request(num_days=3, dimension1="OS")
    if data_os:
        print("\n" + "=" * 70)
        print("DISTRIBUTIE SISTEME DE OPERARE")
        print("=" * 70)
        for item in data_os:
            if item.get('metricName') == 'Traffic':
                info = item.get('information', [])
                os_data = {}
                for entry in info:
                    os_name = entry.get('OS', 'Unknown')
                    sessions = int(float(entry.get('totalSessionCount', 0)))
                    os_data[os_name] = sessions

                sorted_os = sorted(os_data.items(), key=lambda x: x[1], reverse=True)
                total = sum(s for _, s in sorted_os)

                print("\n  Sistem Operare        Sesiuni      Procent")
                print("  " + "-" * 45)
                for os_name, sessions in sorted_os[:10]:
                    pct = (sessions / total * 100) if total > 0 else 0
                    print(f"  {os_name:<20} {sessions:>10,}   {pct:>6.1f}%")

    print("\n[3/4] Extragere date tari...")
    data_country = make_request(num_days=3, dimension1="Country")
    if data_country:
        print("\n" + "=" * 70)
        print("DISTRIBUTIE GEOGRAFICA")
        print("=" * 70)
        for item in data_country:
            if item.get('metricName') == 'Traffic':
                info = item.get('information', [])
                country_data = {}
                for entry in info:
                    country = entry.get('Country', entry.get('Country/Region', 'Unknown'))
                    sessions = int(float(entry.get('totalSessionCount', 0)))
                    country_data[country] = sessions

                sorted_countries = sorted(country_data.items(), key=lambda x: x[1], reverse=True)
                total = sum(s for _, s in sorted_countries)

                print("\n  Tara                   Sesiuni      Procent")
                print("  " + "-" * 45)
                for country, sessions in sorted_countries[:15]:
                    pct = (sessions / total * 100) if total > 0 else 0
                    print(f"  {country:<20} {sessions:>10,}   {pct:>6.1f}%")

    print("\n[4/4] Extragere date surse trafic...")
    data_source = make_request(num_days=3, dimension1="Source")
    if data_source:
        print("\n" + "=" * 70)
        print("SURSE DE TRAFIC")
        print("=" * 70)
        for item in data_source:
            if item.get('metricName') == 'Traffic':
                info = item.get('information', [])
                source_data = {}
                for entry in info:
                    source = entry.get('Source', 'Direct')
                    sessions = int(float(entry.get('totalSessionCount', 0)))
                    source_data[source] = sessions

                sorted_sources = sorted(source_data.items(), key=lambda x: x[1], reverse=True)
                total = sum(s for _, s in sorted_sources)

                print("\n  Sursa                  Sesiuni      Procent")
                print("  " + "-" * 45)
                for source, sessions in sorted_sources[:15]:
                    pct = (sessions / total * 100) if total > 0 else 0
                    source_display = source[:20] if source else "Direct"
                    print(f"  {source_display:<20} {sessions:>10,}   {pct:>6.1f}%")

    # Print all metrics available
    print("\n" + "=" * 70)
    print("TOATE METRICILE DISPONIBILE")
    print("=" * 70)
    if data_devices:
        for item in data_devices:
            metric_name = item.get('metricName', 'Unknown')
            info = item.get('information', [])
            print(f"\n  {metric_name}: {len(info)} entries")

    # Analyze UX issues from available data
    ux_issues = {}
    if data_devices:
        ux_issues = analyze_ux_issues(data_devices)

    # Generate recommendations
    generate_recommendations(
        traffic_info if traffic_info else [],
        ux_issues,
        device_info if device_info else []
    )

    print("\n" + "=" * 70)
    print("NOTA: Limitat la 10 API calls/zi. Folositi strategic.")
    print("=" * 70)

if __name__ == "__main__":
    main()
