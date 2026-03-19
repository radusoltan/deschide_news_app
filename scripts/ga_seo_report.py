#!/usr/bin/env python3
"""
Google Analytics & SEO Report Generator for Deschide.md
"""

import json
import os
from datetime import datetime, timedelta
from google.analytics.admin import AnalyticsAdminServiceClient
from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange,
    Dimension,
    Metric,
    RunReportRequest,
    OrderBy,
)
from google.oauth2 import service_account

# Path to credentials
CREDENTIALS_PATH = "/var/www/deschide_news_app/deschide-md--830-4ed32c88e641_new.json"

def get_credentials():
    """Load service account credentials"""
    return service_account.Credentials.from_service_account_file(
        CREDENTIALS_PATH,
        scopes=[
            "https://www.googleapis.com/auth/analytics.readonly",
            "https://www.googleapis.com/auth/analytics.edit",
        ]
    )

def list_ga4_properties():
    """List all GA4 properties accessible by the service account"""
    credentials = get_credentials()
    client = AnalyticsAdminServiceClient(credentials=credentials)

    print("=" * 60)
    print("SEARCHING FOR GA4 PROPERTIES...")
    print("=" * 60)

    try:
        # List account summaries
        accounts = client.list_account_summaries()

        properties_found = []
        for account_summary in accounts:
            print(f"\n📊 Account: {account_summary.display_name}")
            print(f"   Account ID: {account_summary.account}")

            for property_summary in account_summary.property_summaries:
                print(f"\n   🏠 Property: {property_summary.display_name}")
                print(f"      Property ID: {property_summary.property}")

                # Extract numeric property ID
                property_id = property_summary.property.split("/")[-1]
                properties_found.append({
                    "name": property_summary.display_name,
                    "property_id": property_id,
                    "full_name": property_summary.property
                })

        return properties_found

    except Exception as e:
        print(f"❌ Error listing properties: {e}")
        return []

def run_analytics_report(property_id: str, start_date: str = "30daysAgo", end_date: str = "today"):
    """Run analytics report for the given property"""
    credentials = get_credentials()
    client = BetaAnalyticsDataClient(credentials=credentials)

    print("\n" + "=" * 60)
    print(f"ANALYTICS REPORT FOR PROPERTY: {property_id}")
    print(f"Date Range: {start_date} to {end_date}")
    print("=" * 60)

    # ========================================
    # 1. TRAFFIC OVERVIEW
    # ========================================
    print("\n📈 TRAFFIC OVERVIEW")
    print("-" * 40)

    try:
        request = RunReportRequest(
            property=f"properties/{property_id}",
            date_ranges=[DateRange(start_date=start_date, end_date=end_date)],
            metrics=[
                Metric(name="activeUsers"),
                Metric(name="newUsers"),
                Metric(name="sessions"),
                Metric(name="screenPageViews"),
                Metric(name="averageSessionDuration"),
                Metric(name="bounceRate"),
                Metric(name="engagementRate"),
            ],
        )
        response = client.run_report(request)

        for row in response.rows:
            print(f"   👥 Active Users: {int(float(row.metric_values[0].value)):,}")
            print(f"   🆕 New Users: {int(float(row.metric_values[1].value)):,}")
            print(f"   📊 Sessions: {int(float(row.metric_values[2].value)):,}")
            print(f"   📄 Page Views: {int(float(row.metric_values[3].value)):,}")
            avg_duration = float(row.metric_values[4].value)
            print(f"   ⏱️  Avg Session Duration: {int(avg_duration // 60)}m {int(avg_duration % 60)}s")
            bounce_rate = float(row.metric_values[5].value) * 100
            print(f"   📉 Bounce Rate: {bounce_rate:.1f}%")
            engagement_rate = float(row.metric_values[6].value) * 100
            print(f"   💡 Engagement Rate: {engagement_rate:.1f}%")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 2. TOP PAGES
    # ========================================
    print("\n📄 TOP 20 PAGES")
    print("-" * 40)

    try:
        request = RunReportRequest(
            property=f"properties/{property_id}",
            date_ranges=[DateRange(start_date=start_date, end_date=end_date)],
            dimensions=[Dimension(name="pagePath")],
            metrics=[
                Metric(name="screenPageViews"),
                Metric(name="activeUsers"),
                Metric(name="averageSessionDuration"),
            ],
            order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="screenPageViews"), desc=True)],
            limit=20,
        )
        response = client.run_report(request)

        print(f"   {'Page Path':<50} {'Views':>10} {'Users':>10} {'Avg Time':>10}")
        print("   " + "-" * 82)
        for row in response.rows:
            path = row.dimension_values[0].value[:48]
            views = int(float(row.metric_values[0].value))
            users = int(float(row.metric_values[1].value))
            duration = float(row.metric_values[2].value)
            time_str = f"{int(duration // 60)}:{int(duration % 60):02d}"
            print(f"   {path:<50} {views:>10,} {users:>10,} {time_str:>10}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 3. TRAFFIC SOURCES
    # ========================================
    print("\n🌐 TRAFFIC SOURCES")
    print("-" * 40)

    try:
        request = RunReportRequest(
            property=f"properties/{property_id}",
            date_ranges=[DateRange(start_date=start_date, end_date=end_date)],
            dimensions=[Dimension(name="sessionSource"), Dimension(name="sessionMedium")],
            metrics=[
                Metric(name="sessions"),
                Metric(name="activeUsers"),
                Metric(name="bounceRate"),
            ],
            order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
            limit=15,
        )
        response = client.run_report(request)

        print(f"   {'Source / Medium':<35} {'Sessions':>12} {'Users':>10} {'Bounce':>10}")
        print("   " + "-" * 69)
        for row in response.rows:
            source = row.dimension_values[0].value
            medium = row.dimension_values[1].value
            source_medium = f"{source} / {medium}"[:33]
            sessions = int(float(row.metric_values[0].value))
            users = int(float(row.metric_values[1].value))
            bounce = float(row.metric_values[2].value) * 100
            print(f"   {source_medium:<35} {sessions:>12,} {users:>10,} {bounce:>9.1f}%")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 4. DEVICE BREAKDOWN
    # ========================================
    print("\n📱 DEVICE BREAKDOWN")
    print("-" * 40)

    try:
        request = RunReportRequest(
            property=f"properties/{property_id}",
            date_ranges=[DateRange(start_date=start_date, end_date=end_date)],
            dimensions=[Dimension(name="deviceCategory")],
            metrics=[
                Metric(name="sessions"),
                Metric(name="activeUsers"),
                Metric(name="screenPageViews"),
            ],
            order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
        )
        response = client.run_report(request)

        total_sessions = sum(int(float(row.metric_values[0].value)) for row in response.rows)

        print(f"   {'Device':<15} {'Sessions':>12} {'%':>8} {'Users':>10} {'Page Views':>12}")
        print("   " + "-" * 59)
        for row in response.rows:
            device = row.dimension_values[0].value.capitalize()
            sessions = int(float(row.metric_values[0].value))
            percentage = (sessions / total_sessions * 100) if total_sessions > 0 else 0
            users = int(float(row.metric_values[1].value))
            pageviews = int(float(row.metric_values[2].value))
            print(f"   {device:<15} {sessions:>12,} {percentage:>7.1f}% {users:>10,} {pageviews:>12,}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 5. GEOGRAPHIC DISTRIBUTION
    # ========================================
    print("\n🌍 TOP COUNTRIES")
    print("-" * 40)

    try:
        request = RunReportRequest(
            property=f"properties/{property_id}",
            date_ranges=[DateRange(start_date=start_date, end_date=end_date)],
            dimensions=[Dimension(name="country")],
            metrics=[
                Metric(name="sessions"),
                Metric(name="activeUsers"),
            ],
            order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
            limit=15,
        )
        response = client.run_report(request)

        total_sessions = sum(int(float(row.metric_values[0].value)) for row in response.rows)

        print(f"   {'Country':<25} {'Sessions':>12} {'%':>8} {'Users':>10}")
        print("   " + "-" * 57)
        for row in response.rows:
            country = row.dimension_values[0].value[:23]
            sessions = int(float(row.metric_values[0].value))
            percentage = (sessions / total_sessions * 100) if total_sessions > 0 else 0
            users = int(float(row.metric_values[1].value))
            print(f"   {country:<25} {sessions:>12,} {percentage:>7.1f}% {users:>10,}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 6. LANDING PAGES (SEO)
    # ========================================
    print("\n🎯 TOP LANDING PAGES (SEO)")
    print("-" * 40)

    try:
        request = RunReportRequest(
            property=f"properties/{property_id}",
            date_ranges=[DateRange(start_date=start_date, end_date=end_date)],
            dimensions=[Dimension(name="landingPage")],
            metrics=[
                Metric(name="sessions"),
                Metric(name="bounceRate"),
                Metric(name="averageSessionDuration"),
            ],
            order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
            limit=15,
        )
        response = client.run_report(request)

        print(f"   {'Landing Page':<45} {'Sessions':>10} {'Bounce':>10} {'Avg Time':>10}")
        print("   " + "-" * 77)
        for row in response.rows:
            page = row.dimension_values[0].value[:43]
            sessions = int(float(row.metric_values[0].value))
            bounce = float(row.metric_values[1].value) * 100
            duration = float(row.metric_values[2].value)
            time_str = f"{int(duration // 60)}:{int(duration % 60):02d}"
            print(f"   {page:<45} {sessions:>10,} {bounce:>9.1f}% {time_str:>10}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 7. SEARCH QUERIES (if available)
    # ========================================
    print("\n🔍 ORGANIC SEARCH TERMS (if available)")
    print("-" * 40)

    try:
        request = RunReportRequest(
            property=f"properties/{property_id}",
            date_ranges=[DateRange(start_date=start_date, end_date=end_date)],
            dimensions=[Dimension(name="sessionGoogleAdsQuery")],
            metrics=[
                Metric(name="sessions"),
            ],
            order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
            limit=20,
        )
        response = client.run_report(request)

        if response.rows:
            for row in response.rows:
                query = row.dimension_values[0].value
                sessions = int(float(row.metric_values[0].value))
                if query and query != "(not set)":
                    print(f"   {query[:50]:<50} {sessions:>10,}")
        else:
            print("   No search query data available")

    except Exception as e:
        print(f"   ℹ️  Search query data not available (requires Search Console integration)")

    # ========================================
    # 8. DAILY TREND (Last 7 days)
    # ========================================
    print("\n📅 DAILY TREND (Last 7 days)")
    print("-" * 40)

    try:
        request = RunReportRequest(
            property=f"properties/{property_id}",
            date_ranges=[DateRange(start_date="7daysAgo", end_date="today")],
            dimensions=[Dimension(name="date")],
            metrics=[
                Metric(name="activeUsers"),
                Metric(name="sessions"),
                Metric(name="screenPageViews"),
            ],
            order_bys=[OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="date"), desc=False)],
        )
        response = client.run_report(request)

        print(f"   {'Date':<12} {'Users':>10} {'Sessions':>12} {'Page Views':>12}")
        print("   " + "-" * 48)
        for row in response.rows:
            date_str = row.dimension_values[0].value
            date_formatted = f"{date_str[0:4]}-{date_str[4:6]}-{date_str[6:8]}"
            users = int(float(row.metric_values[0].value))
            sessions = int(float(row.metric_values[1].value))
            pageviews = int(float(row.metric_values[2].value))
            print(f"   {date_formatted:<12} {users:>10,} {sessions:>12,} {pageviews:>12,}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

    # ========================================
    # 9. BROWSER BREAKDOWN
    # ========================================
    print("\n🌐 BROWSER BREAKDOWN")
    print("-" * 40)

    try:
        request = RunReportRequest(
            property=f"properties/{property_id}",
            date_ranges=[DateRange(start_date=start_date, end_date=end_date)],
            dimensions=[Dimension(name="browser")],
            metrics=[
                Metric(name="sessions"),
                Metric(name="activeUsers"),
            ],
            order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
            limit=10,
        )
        response = client.run_report(request)

        total_sessions = sum(int(float(row.metric_values[0].value)) for row in response.rows)

        print(f"   {'Browser':<20} {'Sessions':>12} {'%':>8} {'Users':>10}")
        print("   " + "-" * 52)
        for row in response.rows:
            browser = row.dimension_values[0].value[:18]
            sessions = int(float(row.metric_values[0].value))
            percentage = (sessions / total_sessions * 100) if total_sessions > 0 else 0
            users = int(float(row.metric_values[1].value))
            print(f"   {browser:<20} {sessions:>12,} {percentage:>7.1f}% {users:>10,}")

    except Exception as e:
        print(f"   ❌ Error: {e}")

def main():
    """Main function"""
    print("\n" + "=" * 60)
    print("🔍 DESCHIDE.MD - SEO & ANALYTICS REPORT")
    print(f"📅 Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("=" * 60)

    # First, list available properties
    properties = list_ga4_properties()

    if not properties:
        print("\n❌ No GA4 properties found accessible by this service account.")
        print("\n📝 To fix this:")
        print("   1. Go to Google Analytics Admin")
        print("   2. Open your GA4 property")
        print("   3. Go to Account Access Management")
        print("   4. Add: analytics-reporter@deschide-md--830.iam.gserviceaccount.com")
        print("   5. Grant 'Viewer' role")
        return

    # Run report for each property found
    for prop in properties:
        run_analytics_report(prop["property_id"])

    print("\n" + "=" * 60)
    print("📊 REPORT COMPLETE")
    print("=" * 60)

if __name__ == "__main__":
    main()
