<?php

namespace Webkul\DinxErpSync\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MigrateLocalSqliteCommand extends Command
{
    protected $signature = 'dinx:migrate-local-sqlite
        {sqlite_path? : Absolute path to the source sqlite file}
        {--target-connection= : Target database connection name}
        {--dry-run : Inspect migration actions without writing data}';

    protected $description = 'Migrate DINX sync and partner records from a local sqlite database into the current Aureus database';

    /**
     * Cache for foreign-key existence checks to avoid repeated lookups per row.
     *
     * @var array<string, bool>
     */
    protected array $foreignKeyExistsCache = [];

    public function handle(): int
    {
        $sqlitePath = $this->resolveSqlitePath();
        $targetConnectionName = $this->resolveTargetConnectionName();
        $dryRun = (bool) $this->option('dry-run');

        $this->line('Source sqlite file: '.$sqlitePath);
        $this->line('Target connection: '.$targetConnectionName);
        $this->line('Dry run: '.($dryRun ? 'yes' : 'no'));

        if ($dryRun) {
            $this->warn('Dry run mode is enabled. No target data will be modified.');
        }

        $target = DB::connection($targetConnectionName);
        $source = $this->openSqliteSource($sqlitePath);

        $partnerMigration = $this->migratePartners(
            source: $source,
            target: $target,
            dryRun: $dryRun
        );

        $mappingMigration = $this->migrateSyncMappings(
            source: $source,
            target: $target,
            partnerIdMap: $partnerMigration['partner_id_map'],
            dryRun: $dryRun
        );

        $logMigration = $this->migrateSyncLogs(
            source: $source,
            target: $target,
            partnerIdMap: $partnerMigration['partner_id_map'],
            dryRun: $dryRun
        );

        $settingsMigration = $this->migrateSettings(
            source: $source,
            target: $target,
            dryRun: $dryRun
        );

        if (! $dryRun) {
            $this->resetAutoIncrement($target, 'partners_partners');
            $this->resetAutoIncrement($target, 'dinx_sync_mappings');
            $this->resetAutoIncrement($target, 'dinx_sync_logs');
            $this->resetAutoIncrement($target, 'settings');
        }

        $this->newLine();
        $this->info('Migration summary');
        $this->table(
            ['Table', 'Source Rows', 'Inserted', 'Updated', 'Remapped', 'Target Total'],
            [
                [
                    'partners_partners',
                    $partnerMigration['source_count'],
                    $partnerMigration['inserted'],
                    $partnerMigration['updated'],
                    $partnerMigration['remapped'],
                    $this->countRows($target, 'partners_partners'),
                ],
                [
                    'dinx_sync_mappings',
                    $mappingMigration['source_count'],
                    $mappingMigration['inserted'],
                    $mappingMigration['updated'],
                    $mappingMigration['remapped'],
                    $this->countRows($target, 'dinx_sync_mappings'),
                ],
                [
                    'dinx_sync_logs',
                    $logMigration['source_count'],
                    $logMigration['inserted'],
                    $logMigration['updated'],
                    $logMigration['remapped'],
                    $this->countRows($target, 'dinx_sync_logs'),
                ],
                [
                    'settings (dinx_erp_sync)',
                    $settingsMigration['source_count'],
                    $settingsMigration['inserted'],
                    $settingsMigration['updated'],
                    0,
                    $this->countDinxSettingsRows($target),
                ],
            ]
        );

        $this->newLine();
        $this->line('Source vs target count check (table totals may be larger when target has pre-existing rows):');
        $this->line(sprintf(
            '- partners_partners: source=%d target=%d',
            $partnerMigration['source_count'],
            $this->countRows($target, 'partners_partners')
        ));
        $this->line(sprintf(
            '- dinx_sync_mappings: source=%d target=%d',
            $mappingMigration['source_count'],
            $this->countRows($target, 'dinx_sync_mappings')
        ));
        $this->line(sprintf(
            '- dinx_sync_logs: source=%d target=%d',
            $logMigration['source_count'],
            $this->countRows($target, 'dinx_sync_logs')
        ));
        $this->line(sprintf(
            '- settings(dinx_erp_sync): source=%d target=%d',
            $settingsMigration['source_count'],
            $this->countDinxSettingsRows($target)
        ));

        $this->newLine();
        $this->info($dryRun ? 'Dry run completed successfully.' : 'Migration completed successfully.');

        return self::SUCCESS;
    }

    protected function resolveSqlitePath(): string
    {
        $argumentPath = $this->argument('sqlite_path');
        $path = is_string($argumentPath) && trim($argumentPath) !== ''
            ? trim($argumentPath)
            : database_path('database.sqlite');

        $resolved = realpath($path);
        if ($resolved === false || ! is_file($resolved)) {
            throw new RuntimeException("Sqlite source file not found: {$path}");
        }

        return $resolved;
    }

    protected function resolveTargetConnectionName(): string
    {
        $option = $this->option('target-connection');
        $connectionName = is_string($option) && trim($option) !== ''
            ? trim($option)
            : (string) config('database.default');

        if (! config("database.connections.{$connectionName}")) {
            throw new RuntimeException("Target connection '{$connectionName}' is not configured.");
        }

        return $connectionName;
    }

    protected function openSqliteSource(string $sqlitePath): \PDO
    {
        $pdo = new \PDO('sqlite:'.$sqlitePath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        return $pdo;
    }

    protected function migratePartners(\PDO $source, ConnectionInterface $target, bool $dryRun): array
    {
        $rows = $this->fetchSqliteRows($source, 'SELECT * FROM partners_partners ORDER BY id ASC');
        $targetColumns = $this->getTargetColumns($target, 'partners_partners');
        $partnerIdMap = [];

        $inserted = 0;
        $updated = 0;
        $remapped = 0;

        $runner = function () use (
            $rows,
            $target,
            $targetColumns,
            &$partnerIdMap,
            &$inserted,
            &$updated,
            &$remapped,
            $dryRun
        ) {
            foreach ($rows as $row) {
                $payload = $this->filterToTargetColumns($row, $targetColumns);
                $sourceId = (int) ($payload['id'] ?? 0);
                if ($sourceId <= 0) {
                    continue;
                }

                $payload = $this->normalizePartnerPayload($payload, $target);
                $email = $payload['email'] ?? null;

                $existingById = $target->table('partners_partners')
                    ->where('id', $sourceId)
                    ->first();

                if ($existingById) {
                    if (! $dryRun) {
                        $target->table('partners_partners')
                            ->where('id', $sourceId)
                            ->update($this->withoutPrimaryKey($payload));
                    }

                    $partnerIdMap[$sourceId] = $sourceId;
                    $updated++;
                    continue;
                }

                if ($email) {
                    $existingByEmail = $target->table('partners_partners')
                        ->where('email', $email)
                        ->first();

                    if ($existingByEmail) {
                        $targetId = (int) $existingByEmail->id;
                        if (! $dryRun) {
                            $target->table('partners_partners')
                                ->where('id', $targetId)
                                ->update($this->withoutPrimaryKey($payload));
                        }

                        $partnerIdMap[$sourceId] = $targetId;
                        $updated++;
                        $remapped++;
                        continue;
                    }
                }

                if (! $dryRun) {
                    $target->table('partners_partners')->insert($payload);
                }

                $partnerIdMap[$sourceId] = $sourceId;
                $inserted++;
            }
        };

        if ($dryRun) {
            $runner();
        } else {
            $target->transaction($runner);
        }

        return [
            'source_count' => count($rows),
            'inserted' => $inserted,
            'updated' => $updated,
            'remapped' => $remapped,
            'partner_id_map' => $partnerIdMap,
        ];
    }

    protected function migrateSyncMappings(\PDO $source, ConnectionInterface $target, array $partnerIdMap, bool $dryRun): array
    {
        $rows = $this->fetchSqliteRows($source, 'SELECT * FROM dinx_sync_mappings ORDER BY id ASC');
        $targetColumns = $this->getTargetColumns($target, 'dinx_sync_mappings');

        $inserted = 0;
        $updated = 0;
        $remapped = 0;

        $runner = function () use (
            $rows,
            $target,
            $targetColumns,
            $partnerIdMap,
            &$inserted,
            &$updated,
            &$remapped,
            $dryRun
        ) {
            foreach ($rows as $row) {
                $payload = $this->filterToTargetColumns($row, $targetColumns);
                $sourceId = (int) ($payload['id'] ?? 0);
                $externalLeadId = trim((string) ($payload['external_lead_id'] ?? ''));
                if ($sourceId <= 0 || $externalLeadId === '') {
                    continue;
                }

                $payload['external_lead_id'] = $externalLeadId;
                $payload['partner_id'] = $this->resolvePartnerId($payload['partner_id'] ?? null, $partnerIdMap);
                $payload['metadata'] = $this->normalizeJsonColumn($payload['metadata'] ?? null);

                $existingByExternalLead = $target->table('dinx_sync_mappings')
                    ->where('external_lead_id', $externalLeadId)
                    ->first();

                if ($existingByExternalLead) {
                    $targetId = (int) $existingByExternalLead->id;
                    if ($targetId !== $sourceId) {
                        $remapped++;
                    }

                    if (! $dryRun) {
                        $target->table('dinx_sync_mappings')
                            ->where('id', $targetId)
                            ->update($this->withoutPrimaryKey($payload));
                    }

                    $updated++;
                    continue;
                }

                $existingById = $target->table('dinx_sync_mappings')
                    ->where('id', $sourceId)
                    ->first();

                if ($existingById) {
                    if (! $dryRun) {
                        $target->table('dinx_sync_mappings')
                            ->where('id', $sourceId)
                            ->update($this->withoutPrimaryKey($payload));
                    }
                    $updated++;
                    continue;
                }

                if (! $dryRun) {
                    $target->table('dinx_sync_mappings')->insert($payload);
                }
                $inserted++;
            }
        };

        if ($dryRun) {
            $runner();
        } else {
            $target->transaction($runner);
        }

        return [
            'source_count' => count($rows),
            'inserted' => $inserted,
            'updated' => $updated,
            'remapped' => $remapped,
        ];
    }

    protected function migrateSyncLogs(\PDO $source, ConnectionInterface $target, array $partnerIdMap, bool $dryRun): array
    {
        $rows = $this->fetchSqliteRows($source, 'SELECT * FROM dinx_sync_logs ORDER BY id ASC');
        $targetColumns = $this->getTargetColumns($target, 'dinx_sync_logs');

        $inserted = 0;
        $updated = 0;
        $remapped = 0;

        $runner = function () use (
            $rows,
            $target,
            $targetColumns,
            $partnerIdMap,
            &$inserted,
            &$updated,
            &$remapped,
            $dryRun
        ) {
            foreach ($rows as $row) {
                $payload = $this->filterToTargetColumns($row, $targetColumns);
                $sourceId = (int) ($payload['id'] ?? 0);
                $deliveryId = trim((string) ($payload['delivery_id'] ?? ''));
                if ($sourceId <= 0 || $deliveryId === '') {
                    continue;
                }

                $payload['delivery_id'] = $deliveryId;
                $payload['partner_id'] = $this->resolvePartnerId($payload['partner_id'] ?? null, $partnerIdMap, true);
                $payload['payload'] = $this->normalizeJsonColumn($payload['payload'] ?? null);
                $payload['headers'] = $this->normalizeJsonColumn($payload['headers'] ?? null);

                $existingByDelivery = $target->table('dinx_sync_logs')
                    ->where('delivery_id', $deliveryId)
                    ->first();

                if ($existingByDelivery) {
                    $targetId = (int) $existingByDelivery->id;
                    if ($targetId !== $sourceId) {
                        $remapped++;
                    }

                    if (! $dryRun) {
                        $target->table('dinx_sync_logs')
                            ->where('id', $targetId)
                            ->update($this->withoutPrimaryKey($payload));
                    }
                    $updated++;
                    continue;
                }

                $existingById = $target->table('dinx_sync_logs')
                    ->where('id', $sourceId)
                    ->first();

                if ($existingById) {
                    if (! $dryRun) {
                        $target->table('dinx_sync_logs')
                            ->where('id', $sourceId)
                            ->update($this->withoutPrimaryKey($payload));
                    }
                    $updated++;
                    continue;
                }

                if (! $dryRun) {
                    $target->table('dinx_sync_logs')->insert($payload);
                }
                $inserted++;
            }
        };

        if ($dryRun) {
            $runner();
        } else {
            $target->transaction($runner);
        }

        return [
            'source_count' => count($rows),
            'inserted' => $inserted,
            'updated' => $updated,
            'remapped' => $remapped,
        ];
    }

    protected function migrateSettings(\PDO $source, ConnectionInterface $target, bool $dryRun): array
    {
        $rows = $this->fetchSqliteRows($source, "SELECT * FROM settings WHERE \"group\" = 'dinx_erp_sync' ORDER BY id ASC");
        $targetColumns = $this->getTargetColumns($target, 'settings');

        $inserted = 0;
        $updated = 0;

        $runner = function () use ($rows, $target, $targetColumns, &$inserted, &$updated, $dryRun) {
            foreach ($rows as $row) {
                $payload = $this->filterToTargetColumns($row, $targetColumns);
                $group = trim((string) ($payload['group'] ?? ''));
                $name = trim((string) ($payload['name'] ?? ''));
                if ($group === '' || $name === '') {
                    continue;
                }

                $payload['group'] = $group;
                $payload['name'] = $name;
                $payload['locked'] = (int) ($payload['locked'] ?? 0);

                $existing = $target->table('settings')
                    ->where('group', $group)
                    ->where('name', $name)
                    ->first();

                if ($existing) {
                    if (! $dryRun) {
                        $target->table('settings')
                            ->where('id', (int) $existing->id)
                            ->update($this->withoutPrimaryKey($payload));
                    }
                    $updated++;
                    continue;
                }

                if (! $dryRun) {
                    $target->table('settings')->insert($this->withoutPrimaryKey($payload));
                }
                $inserted++;
            }
        };

        if ($dryRun) {
            $runner();
        } else {
            $target->transaction($runner);
        }

        return [
            'source_count' => count($rows),
            'inserted' => $inserted,
            'updated' => $updated,
        ];
    }

    protected function fetchSqliteRows(\PDO $source, string $query): array
    {
        $statement = $source->query($query);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    protected function normalizePartnerPayload(array $payload, ConnectionInterface $target): array
    {
        $payload['email'] = $this->normalizeNullableString($payload['email'] ?? null);

        // Keep phase-1 import resilient: partner rows can reference non-migrated tables.
        foreach ($this->partnerForeignKeyMap() as $column => $table) {
            if (! array_key_exists($column, $payload)) {
                continue;
            }

            $payload[$column] = $this->normalizeNullableForeignKey($target, $table, $payload[$column]);
        }

        return $payload;
    }

    protected function partnerForeignKeyMap(): array
    {
        return [
            'parent_id' => 'partners_partners',
            'creator_id' => 'users',
            'user_id' => 'users',
            'title_id' => 'partners_titles',
            'company_id' => 'companies',
            'industry_id' => 'partners_industries',
            'state_id' => 'states',
            'country_id' => 'countries',
        ];
    }

    protected function normalizeNullableForeignKey(ConnectionInterface $target, string $table, mixed $value): ?int
    {
        $id = (int) $value;
        if ($id <= 0) {
            return null;
        }

        $cacheKey = $table.':'.$id;
        if (array_key_exists($cacheKey, $this->foreignKeyExistsCache)) {
            return $this->foreignKeyExistsCache[$cacheKey] ? $id : null;
        }

        $exists = $target->table($table)->where('id', $id)->exists();
        $this->foreignKeyExistsCache[$cacheKey] = $exists;

        return $exists ? $id : null;
    }

    protected function getTargetColumns(ConnectionInterface $target, string $table): array
    {
        return $target->getSchemaBuilder()->getColumnListing($table);
    }

    protected function filterToTargetColumns(array $row, array $targetColumns): array
    {
        $filtered = [];
        foreach ($targetColumns as $column) {
            if (array_key_exists($column, $row)) {
                $filtered[$column] = $row[$column];
            }
        }

        return $filtered;
    }

    protected function withoutPrimaryKey(array $payload): array
    {
        unset($payload['id']);

        return $payload;
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function normalizeJsonColumn(mixed $value): mixed
    {
        if ($value === null) {
            return $value;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);

            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException) {
            return $trimmed;
        }
    }

    protected function resolvePartnerId(mixed $partnerId, array $partnerIdMap, bool $allowNull = false): ?int
    {
        $intId = (int) $partnerId;
        if ($intId <= 0) {
            return $allowNull ? null : 0;
        }

        return $partnerIdMap[$intId] ?? $intId;
    }

    protected function countRows(ConnectionInterface $target, string $table): int
    {
        return (int) $target->table($table)->count();
    }

    protected function countDinxSettingsRows(ConnectionInterface $target): int
    {
        return (int) $target->table('settings')
            ->where('group', 'dinx_erp_sync')
            ->count();
    }

    protected function resetAutoIncrement(ConnectionInterface $target, string $table): void
    {
        if ($target->getDriverName() !== 'mysql') {
            return;
        }

        $maxId = (int) $target->table($table)->max('id');
        $nextId = max(1, $maxId + 1);
        $target->statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$nextId}");
    }
}
