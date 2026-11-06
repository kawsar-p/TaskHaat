# TaskHaat backup and restore

## Create a backup

Run from the TaskHaat project root:

```cmd
C:\xampp\php\php.exe database\backup.php
```

The script is CLI-only. It writes a timestamped folder under `storage/backups/` containing:

- `database.sql` — table definitions and a consistent data snapshot from InnoDB tables.
- `media/` — copies of private Worker evidence, private Publisher deposit proofs and public campaign images.

The backup folder is denied by the `storage/.htaccess` rule and its own `.htaccess`. For the closest database/media alignment, run the backup during a quiet period or briefly put the site into maintenance mode so uploads are not changing while the media folders are copied. Keep a second copy outside the laptop/project directory. Backups contain personal data and financial records; protect them accordingly.

## Restore the database

1. Stop the TaskHaat PHP/Apache process to prevent writes during restoration.
2. Create or select the target MySQL database in phpMyAdmin.
3. Import the backup's `database.sql` into that database. The dump contains `DROP TABLE` statements and replaces tables in the selected database.
4. If restoring to a database with a different name, edit the `USE` statement at the top of the dump before import.
5. Copy the backup's `media/storage/submissions` contents back to `storage/submissions`.
6. Copy `media/storage/deposits` contents back to `storage/deposits`.
7. Copy `media/public/uploads/campaigns` contents back to `public/uploads/campaigns`.
8. Keep the same `APP_KEY` used when the backup was created. Changing it can make encrypted payout references unreadable.
9. Run `database/verify.php` and `database/preflight.php` after restore.

## Important

- A SQL-only export does not include uploaded files. Use `database/backup.php` for both the database and media folders.
- Do not store backups inside `public/` or a publicly accessible folder.
- Test a restore periodically. A backup that has never been restored is not a verified recovery plan.
