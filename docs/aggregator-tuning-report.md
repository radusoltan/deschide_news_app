# Aggregator Tuning Report — Sprint 26

## Date: 2026-04-06

## Current Database State

| Metric | Value |
|--------|-------|
| Total press releases | 111 |
| From scraping (gov.md) | 86 |
| From email (Zoho) | 15 |
| From scraping (Agerpres) | 8 |
| From scraping (Ukrinform) | 2 |
| From aggregators | 0 (system not yet in production) |
| Total topics | 49 (all approved) |
| Topics pending review | 0 |
| Articles with relevance scores | 0 |

## Analysis

The aggregator system (Sprint 24-25) has been built but not yet deployed to production. All current press releases come from the scraping pipeline and email import. This means tuning is **preemptive** rather than data-driven, based on expected behavior patterns from the 11+ aggregator sources being added in Sprint 26.

### Key Observations

1. **No aggregator duplicates to analyze yet** — L1/L2/L3 distribution unknown
2. **49 active topics** provide a good foundation for trend queries
3. **Cross-language sources** (IT, DE, FR, EN, RU, RO) will increase false positive risk in ES similarity matching since translated articles about the same event may score in the 0.6-0.8 range

## Adjustments Made

### 1. Elasticsearch Similarity Thresholds (SemanticDeduplicatorService)

| Parameter | Previous | New | Rationale |
|-----------|----------|-----|-----------|
| ES_DUPLICATE_THRESHOLD | 0.80 | 0.82 | Reduce false positives from cross-language articles about same events |
| ES_REVIEW_THRESHOLD | 0.60 | 0.55 | Catch more near-duplicates at L2, reducing Gemini CLI calls for obvious non-matches |
| Gray zone width | 0.20 (0.6-0.8) | 0.27 (0.55-0.82) | Wider zone but with higher duplicate bar, so L3 Gemini handles more edge cases |
| GEMINI_CONFIDENCE_THRESHOLD | 0.70 | 0.70 (unchanged) | No data to suggest change |

### 2. Trend Scoring Decay (TrendScoringService)

| Parameter | Previous | New | Rationale |
|-----------|----------|-----|-----------|
| Decay exponent | 1.5 | 1.6 | Stronger recency bias — with 11+ sources, topics will accumulate articles faster; higher decay prevents stale topics from dominating |

### 3. Source Weights (No changes)

Current weights remain appropriate:
- Reuters/AP: 3.0 (wire agencies)
- Agerpres/IPN/Moldpres/Gov.md: 2.0 (official sources)
- Aggregator: 1.0 (general)
- Default: 0.5

Telegram channels and portal scrapers will use default weight (0.5) initially. After production data collection, specific weights should be assigned to high-quality channels (e.g., ZDG, NewsMaker).

## Recommendations for Sprint 27+

1. **Monitor L2/L3 distribution** — After 2 weeks of production aggregation, check:
   - If >30% of articles reach L3 (Gemini), narrow the gray zone
   - If <5% reach L3, widen it to save Gemini API calls
   - Query: `SELECT dedup_level, COUNT(*) FROM press_release_dedup_log GROUP BY dedup_level`

2. **Cross-language dedup** — Aggregated articles from ANSA, Le Monde, Guardian about the same event will have different text but similar topics. Consider adding a pre-L2 check that groups articles by topic+timewindow before ES similarity.

3. **Source weight calibration** — After 500+ aggregated articles, analyze which sources produce the most approved topics and adjust weights accordingly.

4. **Telegram channel quality** — Track which Telegram channels produce the most actionable news links vs. noise, and consider adding a per-channel relevance multiplier.

5. **Rate limit optimization** — Monitor which portal scrapers get blocked most frequently and adjust rate limits based on actual blocking patterns.
