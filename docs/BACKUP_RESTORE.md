# MySQL/MariaDB backup and restore runbook

Backups are only proven after a restore test. Use encrypted storage, restrict access, and never put database passwords in source control or shell history.

## cPanel procedure

1. Run `php artisan down --retry=60`.
2. In cPanel Backup or phpMyAdmin, export the application database in SQL format with structure, data, triggers, and routines where supported.
3. Archive `.env` separately in encrypted secret storage and back up user uploads under `storage/app` if present.
4. Record UTC time, application commit, migration status, database engine/version, row counts, archive size, and checksum.
5. Run `php artisan up`.

For hosts with SSH, use placeholders and a password prompt:

```bash
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 -h DB_HOST -u DB_USER -p DB_NAME > sellassist-backup.sql
sha256sum sellassist-backup.sql
```

## Restore drill

Restore into a new isolated database, never over production during a drill:

```bash
mysql -h DB_HOST -u DB_USER -p RESTORE_DATABASE < sellassist-backup.sql
```

Point a temporary staging instance at the restored database, then verify migration status, administrator login, representative domain records, order totals, stock, receipts, and authorization. Record elapsed restore time, checksum, row-count comparison, findings, and sign-off. Delete the temporary database securely after retaining evidence.
