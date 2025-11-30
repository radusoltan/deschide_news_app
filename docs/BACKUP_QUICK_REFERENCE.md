# PostgreSQL Backup - Quick Reference

## Quick Commands

### Create Backup

```bash
cd /var/www/deschide_news_app
./scripts/backup-db.sh
```

### Verify Backup

```bash
ls -lh /var/www/deschide_news_app/backups/postgresql/daily/
```

### Restore Backup (Test)

```bash
export PGPASSWORD=YOUR_DB_PASSWORD
psql -h 127.0.0.1 -U deschide_admin -d postgres -c "CREATE DATABASE deschide_restore_test;"
pg_restore -h 127.0.0.1 -U deschide_admin -d deschide_restore_test /path/to/backup.dump
psql -h 127.0.0.1 -U deschide_admin -d deschide_restore_test -c "SELECT COUNT(*) FROM articles;"
```

---

## Directory Structure

```
/var/www/deschide_news_app/
├── backups/
│   └── postgresql/
│       ├── daily/      # 7 days retention
│       ├── weekly/     # 30 days retention
│       └── monthly/    # 365 days retention
└── scripts/
    └── backup-db.sh    # Backup script
```

---

## Configuration

**Database**: `deschide`
**User**: `deschide_admin`
**Password**: See `.env.local`
**Format**: PostgreSQL Custom Format (compressed)

---

## Cron Job (Production)

```bash
# Add to crontab (runs daily at 2:00 AM)
0 2 * * * PGPASSWORD=YOUR_PASSWORD /var/www/deschide_news_app/scripts/backup-db.sh >> /var/log/deschide_backup.log 2>&1
```

---

## Test Results

- ✅ Backup Size: 432 KB (compressed)
- ✅ Backup Time: ~1 second
- ✅ Restore Time: ~2 seconds
- ✅ Data Integrity: 100% verified
  - 81 articles
  - 9 categories
  - 14 authors

---

For detailed documentation, see: `/var/www/deschide_news_app/docs/DATABASE_BACKUP_GUIDE.md`
