#!/usr/bin/env python3
"""
Google Analytics Report for Deschide.md
Property ID: 385150985
"""

from datetime import datetime
from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange,
    Dimension,
    Metric,
    RunReportRequest,
    OrderBy,
)
from google.oauth2 import service_account

# Configuration
CREDENTIALS_PATH = "/var/www/deschide_news_app/deschide-md--830-4ed32c88e641_new.json"
PROPERTY_ID = "385150985"

def get_credentials():
    """Load service account credentials"""
    return service_account.Credentials.from_service_account_file(
        CREDENTIALS_PATH,
        scopes=["https://www.googleapis.com/auth/analytics.readonly"]
    )

def run_report(client, property_id, dimensions, metrics, date_range="30daysAgo", limit=20, order_by_metric=None):
    """Generic report runner"""
    dim_list = [Dimension(name=d) for d in dimensions] if dimensions else []
    metric_list = [Metric(name=m) for m in metrics]

    order_bys = []
    if order_by_metric:
        order_bys = [OrderBy(metric=OrderBy.MetricOrderBy(metric_name=order_by_metric), desc=True)]

    request = RunReportRequest(
        property=f"properties/{property_id}",
        date_ranges=[DateRange(start_date=date_range, end_date="today")],
        dimensions=dim_list,
        metrics=metric_list,
        order_bys=order_bys,
        limit=limit,
    )
    return client.run_report(request)

def main():
    credentials = get_credentials()
    client = BetaAnalyticsDataClient(credentials=credentials)

    print("\n" + "=" * 80)
    print("🔍 DESCHIDE.MD - SEO & ANALYTICS REPORT")
    print(f"📅 Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"🏠 Property ID: {PROPERTY_ID}")
    print(f"📆 Date Range: Last 30 days")
    print("=" * 80)

    # ========================================
    # 1. TRAFFIC OVERVIEW
    # ========================================
    print("\n" + "=" * 80)
    print("📈 TRAFFIC OVERVIEW (Last 30 Days)")
    print("=" * 80)

    try:
        response = run_report(
            client, PROPERTY_ID,
            dimensions=[],
            metrics=["activeUsers", "newUsers", "sessions", "screenPageViews",
                    "averageSessionDuration", "bounceRate", "engagementRate",
                    "sessionsPerUser", "screenPageViewsPerSession"]
        )

        for row in response.rows:
            active_users = int(float(row.metric_values[0].value))
            new_users = int(float(row.metric_values[1].value))
            sessions = int(float(row.metric_values[2].value))
            pageviews = int(float(row.metric_values[3].value))
            avg_duration = float(row.metric_values[4].value)
            bounce_rate = float(row.metric_values[5].value) * 100
            engagement_rate = float(row.metric_values[6].value) * 100
            sessions_per_user = float(row.metric_values[7].value)
            pages_per_session = float(row.metric_values[8].value)

            print(f"\n   👥 Active Users:           {active_users:>15,}")
            print(f"   🆕 New Users:              {new_users:>15,}")
            print(f"   📊 Total Sessions:         {sessions:>15,}")
            print(f"   📄 Total Page Views:       {pageviews:>15,}")
            print(f"   ⏱️  Avg Session Duration:   {int(avg_duration // 60):>10}m {int(avg_duration % 60):02d}s")
            print(f"   📉 Bounce Rate:            {bounce_rate:>14.1f}%")
            print(f"   💡 Engagement Rate:        {engagement_rate:>14.1f}%")
            print(f"   🔄 Sessions per User:      {sessions_per_user:>15.2f}")
            print(f"   📑 Pages per Session:      {pages_per_session:>15.2f}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 2. DAILY TREND (Last 14 days)
    # ========================================
    print("\n" + "=" * 80)
    print("📅 DAILY TRAFFIC TREND (Last 14 Days)")
    print("=" * 80)

    try:
        request = RunReportRequest(
            property=f"properties/{PROPERTY_ID}",
            date_ranges=[DateRange(start_date="14daysAgo", end_date="today")],
            dimensions=[Dimension(name="date")],
            metrics=[
                Metric(name="activeUsers"),
                Metric(name="sessions"),
                Metric(name="screenPageViews"),
            ],
            order_bys=[OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="date"), desc=False)],
        )
        response = client.run_report(request)

        print(f"\n   {'Date':<12} {'Users':>10} {'Sessions':>12} {'Page Views':>14}")
        print("   " + "-" * 50)
        for row in response.rows:
            date_str = row.dimension_values[0].value
            date_formatted = f"{date_str[0:4]}-{date_str[4:6]}-{date_str[6:8]}"
            users = int(float(row.metric_values[0].value))
            sessions = int(float(row.metric_values[1].value))
            pageviews = int(float(row.metric_values[2].value))
            print(f"   {date_formatted:<12} {users:>10,} {sessions:>12,} {pageviews:>14,}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 3. TOP PAGES
    # ========================================
    print("\n" + "=" * 80)
    print("📄 TOP 25 PAGES BY VIEWS")
    print("=" * 80)

    try:
        response = run_report(
            client, PROPERTY_ID,
            dimensions=["pagePath"],
            metrics=["screenPageViews", "activeUsers", "averageSessionDuration", "bounceRate"],
            order_by_metric="screenPageViews",
            limit=25
        )

        print(f"\n   {'#':<3} {'Page Path':<50} {'Views':>10} {'Users':>8} {'Time':>8} {'Bounce':>8}")
        print("   " + "-" * 90)
        for i, row in enumerate(response.rows, 1):
            path = row.dimension_values[0].value[:48]
            views = int(float(row.metric_values[0].value))
            users = int(float(row.metric_values[1].value))
            duration = float(row.metric_values[2].value)
            bounce = float(row.metric_values[3].value) * 100
            time_str = f"{int(duration // 60)}:{int(duration % 60):02d}"
            print(f"   {i:<3} {path:<50} {views:>10,} {users:>8,} {time_str:>8} {bounce:>7.1f}%")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 4. TRAFFIC SOURCES
    # ========================================
    print("\n" + "=" * 80)
    print("🌐 TRAFFIC SOURCES")
    print("=" * 80)

    try:
        response = run_report(
            client, PROPERTY_ID,
            dimensions=["sessionSource", "sessionMedium"],
            metrics=["sessions", "activeUsers", "bounceRate", "averageSessionDuration"],
            order_by_metric="sessions",
            limit=20
        )

        print(f"\n   {'Source / Medium':<40} {'Sessions':>10} {'Users':>8} {'Bounce':>8} {'Time':>8}")
        print("   " + "-" * 78)
        for row in response.rows:
            source = row.dimension_values[0].value
            medium = row.dimension_values[1].value
            source_medium = f"{source} / {medium}"[:38]
            sessions = int(float(row.metric_values[0].value))
            users = int(float(row.metric_values[1].value))
            bounce = float(row.metric_values[2].value) * 100
            duration = float(row.metric_values[3].value)
            time_str = f"{int(duration // 60)}:{int(duration % 60):02d}"
            print(f"   {source_medium:<40} {sessions:>10,} {users:>8,} {bounce:>7.1f}% {time_str:>8}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 5. DEVICE BREAKDOWN
    # ========================================
    print("\n" + "=" * 80)
    print("📱 DEVICE BREAKDOWN")
    print("=" * 80)

    try:
        response = run_report(
            client, PROPERTY_ID,
            dimensions=["deviceCategory"],
            metrics=["sessions", "activeUsers", "screenPageViews", "bounceRate"],
            order_by_metric="sessions"
        )

        total_sessions = sum(int(float(row.metric_values[0].value)) for row in response.rows)

        print(f"\n   {'Device':<15} {'Sessions':>12} {'%':>8} {'Users':>10} {'Views':>12} {'Bounce':>8}")
        print("   " + "-" * 68)
        for row in response.rows:
            device = row.dimension_values[0].value.capitalize()
            sessions = int(float(row.metric_values[0].value))
            percentage = (sessions / total_sessions * 100) if total_sessions > 0 else 0
            users = int(float(row.metric_values[1].value))
            pageviews = int(float(row.metric_values[2].value))
            bounce = float(row.metric_values[3].value) * 100
            print(f"   {device:<15} {sessions:>12,} {percentage:>7.1f}% {users:>10,} {pageviews:>12,} {bounce:>7.1f}%")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 6. GEOGRAPHIC DISTRIBUTION
    # ========================================
    print("\n" + "=" * 80)
    print("🌍 TOP 15 COUNTRIES")
    print("=" * 80)

    try:
        response = run_report(
            client, PROPERTY_ID,
            dimensions=["country"],
            metrics=["sessions", "activeUsers", "screenPageViews"],
            order_by_metric="sessions",
            limit=15
        )

        total_sessions = sum(int(float(row.metric_values[0].value)) for row in response.rows)

        print(f"\n   {'Country':<25} {'Sessions':>12} {'%':>8} {'Users':>10} {'Views':>12}")
        print("   " + "-" * 70)
        for row in response.rows:
            country = row.dimension_values[0].value[:23]
            sessions = int(float(row.metric_values[0].value))
            percentage = (sessions / total_sessions * 100) if total_sessions > 0 else 0
            users = int(float(row.metric_values[1].value))
            pageviews = int(float(row.metric_values[2].value))
            print(f"   {country:<25} {sessions:>12,} {percentage:>7.1f}% {users:>10,} {pageviews:>12,}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 7. TOP CITIES
    # ========================================
    print("\n" + "=" * 80)
    print("🏙️ TOP 15 CITIES")
    print("=" * 80)

    try:
        response = run_report(
            client, PROPERTY_ID,
            dimensions=["city"],
            metrics=["sessions", "activeUsers"],
            order_by_metric="sessions",
            limit=15
        )

        print(f"\n   {'City':<30} {'Sessions':>12} {'Users':>10}")
        print("   " + "-" * 54)
        for row in response.rows:
            city = row.dimension_values[0].value[:28]
            sessions = int(float(row.metric_values[0].value))
            users = int(float(row.metric_values[1].value))
            print(f"   {city:<30} {sessions:>12,} {users:>10,}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 8. LANDING PAGES (SEO Performance)
    # ========================================
    print("\n" + "=" * 80)
    print("🎯 TOP 20 LANDING PAGES (SEO Entry Points)")
    print("=" * 80)

    try:
        response = run_report(
            client, PROPERTY_ID,
            dimensions=["landingPage"],
            metrics=["sessions", "activeUsers", "bounceRate", "averageSessionDuration"],
            order_by_metric="sessions",
            limit=20
        )

        print(f"\n   {'Landing Page':<50} {'Sessions':>10} {'Users':>8} {'Bounce':>8} {'Time':>8}")
        print("   " + "-" * 88)
        for row in response.rows:
            page = row.dimension_values[0].value[:48]
            sessions = int(float(row.metric_values[0].value))
            users = int(float(row.metric_values[1].value))
            bounce = float(row.metric_values[2].value) * 100
            duration = float(row.metric_values[3].value)
            time_str = f"{int(duration // 60)}:{int(duration % 60):02d}"
            print(f"   {page:<50} {sessions:>10,} {users:>8,} {bounce:>7.1f}% {time_str:>8}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 9. BROWSER BREAKDOWN
    # ========================================
    print("\n" + "=" * 80)
    print("🌐 BROWSER BREAKDOWN")
    print("=" * 80)

    try:
        response = run_report(
            client, PROPERTY_ID,
            dimensions=["browser"],
            metrics=["sessions", "activeUsers"],
            order_by_metric="sessions",
            limit=10
        )

        total_sessions = sum(int(float(row.metric_values[0].value)) for row in response.rows)

        print(f"\n   {'Browser':<25} {'Sessions':>12} {'%':>8} {'Users':>10}")
        print("   " + "-" * 58)
        for row in response.rows:
            browser = row.dimension_values[0].value[:23]
            sessions = int(float(row.metric_values[0].value))
            percentage = (sessions / total_sessions * 100) if total_sessions > 0 else 0
            users = int(float(row.metric_values[1].value))
            print(f"   {browser:<25} {sessions:>12,} {percentage:>7.1f}% {users:>10,}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 10. OPERATING SYSTEM
    # ========================================
    print("\n" + "=" * 80)
    print("💻 OPERATING SYSTEM")
    print("=" * 80)

    try:
        response = run_report(
            client, PROPERTY_ID,
            dimensions=["operatingSystem"],
            metrics=["sessions", "activeUsers"],
            order_by_metric="sessions",
            limit=10
        )

        total_sessions = sum(int(float(row.metric_values[0].value)) for row in response.rows)

        print(f"\n   {'OS':<20} {'Sessions':>12} {'%':>8} {'Users':>10}")
        print("   " + "-" * 53)
        for row in response.rows:
            os = row.dimension_values[0].value[:18]
            sessions = int(float(row.metric_values[0].value))
            percentage = (sessions / total_sessions * 100) if total_sessions > 0 else 0
            users = int(float(row.metric_values[1].value))
            print(f"   {os:<20} {sessions:>12,} {percentage:>7.1f}% {users:>10,}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 11. PAGE TITLES (Content Performance)
    # ========================================
    print("\n" + "=" * 80)
    print("📰 TOP 20 PAGE TITLES (Content Performance)")
    print("=" * 80)

    try:
        response = run_report(
            client, PROPERTY_ID,
            dimensions=["pageTitle"],
            metrics=["screenPageViews", "activeUsers", "averageSessionDuration"],
            order_by_metric="screenPageViews",
            limit=20
        )

        print(f"\n   {'Page Title':<60} {'Views':>10} {'Users':>8}")
        print("   " + "-" * 80)
        for row in response.rows:
            title = row.dimension_values[0].value[:58]
            views = int(float(row.metric_values[0].value))
            users = int(float(row.metric_values[1].value))
            print(f"   {title:<60} {views:>10,} {users:>8,}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 12. HOURLY DISTRIBUTION
    # ========================================
    print("\n" + "=" * 80)
    print("🕐 TRAFFIC BY HOUR OF DAY (Last 7 days)")
    print("=" * 80)

    try:
        request = RunReportRequest(
            property=f"properties/{PROPERTY_ID}",
            date_ranges=[DateRange(start_date="7daysAgo", end_date="today")],
            dimensions=[Dimension(name="hour")],
            metrics=[Metric(name="sessions"), Metric(name="activeUsers")],
            order_bys=[OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="hour"), desc=False)],
        )
        response = client.run_report(request)

        print(f"\n   {'Hour':<8} {'Sessions':>12} {'Users':>10} {'Graph'}")
        print("   " + "-" * 60)
        max_sessions = max(int(float(row.metric_values[0].value)) for row in response.rows) if response.rows else 1
        for row in response.rows:
            hour = int(row.dimension_values[0].value)
            sessions = int(float(row.metric_values[0].value))
            users = int(float(row.metric_values[1].value))
            bar_length = int((sessions / max_sessions) * 30)
            bar = "█" * bar_length
            print(f"   {hour:02d}:00   {sessions:>12,} {users:>10,} {bar}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # SUMMARY
    # ========================================
    print("\n" + "=" * 80)
    print("📊 REPORT COMPLETE")
    print("=" * 80)
    print(f"\n   Report generated at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("   Data source: Google Analytics 4")
    print(f"   Property ID: {PROPERTY_ID}")
    print("\n" + "=" * 80)

if __name__ == "__main__":
    main()
