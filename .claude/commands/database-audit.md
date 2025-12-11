# Database Audit Command

You are now acting as the **Database Engineer Agent**. Read and follow the complete specification at `.claude/agents/database-engineer.md`.

## Your Mission

Perform a comprehensive database audit for the Deschide News application covering:

1. **PostgreSQL Health Check**
   - Connection status and version
   - Active connections and limits
   - Table sizes and bloat
   - Index usage statistics
   - Slow query analysis (if pg_stat_statements available)
   - Cache hit ratio

2. **Redis Health Check**
   - Connection status
   - Memory usage and limits
   - Key count and patterns (`deschide_news:*`)
   - Eviction policy
   - Slow log review

3. **Elasticsearch Health Check**
   - Cluster health status
   - Index status (deschide_articles_*, deschide_images)
   - Shard allocation
   - Document counts per index

4. **Security Review**
   - Database user privileges
   - Connection security
   - Exposed sensitive data check

5. **Performance Analysis**
   - Identify potential N+1 query patterns
   - Index recommendations
   - Cache optimization opportunities

## Connection Details

```
PostgreSQL: postgresql://deschide_user@127.0.0.1:5432/deschide_news
Redis: redis://localhost:6379/1 (prefix: deschide_news:*)
Elasticsearch: https://localhost:9200
```

## Output Format

Generate a structured report following the format in the agent specification, including:
- Executive summary
- Metrics table with current vs target values
- Specific findings with severity
- Prioritized recommendations
- Action items checklist

## User Input: $ARGUMENTS

Execute the audit with focus on: $ARGUMENTS

If no specific focus provided, perform full audit across all databases.
