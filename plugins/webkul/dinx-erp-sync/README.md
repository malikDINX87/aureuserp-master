# DINX ERP Sync Plugin (Internal Notes)

## One-time sqlite to MySQL migration command

This plugin includes a one-time command intended for production cutover from local sqlite:

```bash
php artisan dinx:migrate-local-sqlite /absolute/path/to/database.sqlite --target-connection=mysql
```

Optional dry run:

```bash
php artisan dinx:migrate-local-sqlite /absolute/path/to/database.sqlite --target-connection=mysql --dry-run
```

### Scope

- `partners_partners`
- `dinx_sync_mappings`
- `dinx_sync_logs`
- `settings` where `group = dinx_erp_sync`

### Behavior

- Uses transaction-per-table writes.
- Preserves source IDs where possible.
- Handles partner email collisions by remapping source partner IDs to existing target IDs.
- Prints source/target counts and migration summary.
- Resets AUTO_INCREMENT on migrated tables (MySQL only).

### Safety

- Run only after taking verified backups of code + sqlite.
- Run once per environment cutover.
- Keep rollback artifacts until post-cutover monitoring is complete.
