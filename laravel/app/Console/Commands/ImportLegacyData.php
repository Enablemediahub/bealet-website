<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportLegacyData extends Command
{
    protected $signature = 'bealet:import-legacy-data
                            {--confirm : Write rows to the configured Laravel database}';

    protected $description = 'Preview or import Bealet legacy MySQL data into the Laravel database';

    private const TABLES = [
        'users',
        'products',
        'appointments',
        'blog_posts',
        'cart',
        'company_branches',
        'contacts',
        'customer_reviews',
        'founder_gallery',
        'gamehub_attempts',
        'gamehub_players',
        'gamehub_questions',
        'gamehub_settings',
        'newsletter',
        'order_items',
        'order_prescriptions',
        'orders',
        'prescription_details',
        'product_images',
        'reviews',
        'site_settings',
        'staff_members',
        'tracking_history',
        'wishlist',
    ];

    public function handle(): int
    {
        $source = DB::connection('legacy');
        $target = DB::connection();
        $sourceSchema = $source->getSchemaBuilder();
        $targetSchema = $target->getSchemaBuilder();
        $sourceDatabase = (string) $source->getDatabaseName();
        $targetDatabase = (string) $target->getDatabaseName();

        if ($sourceDatabase === '' || $targetDatabase === '' || $sourceDatabase === $targetDatabase) {
            throw new RuntimeException('Legacy import requires distinct, explicitly configured source and target databases.');
        }

        if ($sourceDatabase !== 'bealet_website' && ! $this->confirm(
            "The configured legacy source is '{$sourceDatabase}', not 'bealet_website'. Continue?"
        )) {
            return self::FAILURE;
        }

        if ($targetDatabase === 'bealet_website') {
            throw new RuntimeException('Refusing to import into the legacy database.');
        }

        $missingTables = array_values(array_filter(
            self::TABLES,
            static fn (string $table): bool => ! $targetSchema->hasTable($table)
        ));

        if ($missingTables !== []) {
            throw new RuntimeException(
                'Run the Laravel migrations before importing. Missing target tables: '.implode(', ', $missingTables)
            );
        }

        $missingSourceTables = array_values(array_filter(
            self::TABLES,
            static fn (string $table): bool => ! $sourceSchema->hasTable($table)
        ));

        if ($missingSourceTables !== []) {
            throw new RuntimeException(
                'The legacy database is missing expected tables: '.implode(', ', $missingSourceTables)
            );
        }

        $this->info("Source: {$sourceDatabase}");
        $this->info("Target: {$targetDatabase}");

        if (! $this->option('confirm')) {
            $this->warn('Preview only; no rows were changed. Review counts, then rerun with --confirm to import.');
        }

        $rows = [];

        foreach (self::TABLES as $table) {
            $sourceCount = $source->table($table)->count();
            $targetCount = $target->table($table)->count();
            if ($this->option('confirm')) {
                $columns = $sourceSchema->getColumnListing($table);
                if (! in_array('id', $columns, true)) {
                    throw new RuntimeException("Cannot safely import {$table}: no id column was found.");
                }

                $updateColumns = array_values(array_diff($columns, ['id']));
                $source->table($table)
                    ->orderBy('id')
                    ->chunkById(500, function ($records) use ($target, $table, $updateColumns): void {
                        $payload = array_map(
                            static fn (object $record): array => (array) $record,
                            $records->all()
                        );

                        if ($payload !== []) {
                            $target->table($table)->upsert($payload, ['id'], $updateColumns);
                        }
                    });

                $targetCount = $target->table($table)->count();
            }

            $rows[] = [$table, $sourceCount, $targetCount];
        }

        $this->table(['Table', 'Legacy rows', $this->option('confirm') ? 'Laravel rows after import' : 'Laravel rows'], $rows);

        if ($this->option('confirm')) {
            $mismatches = [];
            foreach (self::TABLES as $table) {
                $sourceCount = $source->table($table)->count();
                $targetCount = $target->table($table)->count();
                if ($sourceCount !== $targetCount || ! $this->rowsMatch($source, $target, $table)) {
                    $mismatches[] = "{$table}: source={$sourceCount}, target={$targetCount}, records differ";
                }
            }

            if ($mismatches !== []) {
                $this->error('Import completed, but row counts differ:');
                foreach ($mismatches as $mismatch) {
                    $this->line($mismatch);
                }

                return self::FAILURE;
            }

            $this->info('Import completed; all 24 tables have matching row counts and records.');
        }

        return self::SUCCESS;
    }

    private function rowsMatch($source, $target, string $table): bool
    {
        $matches = true;

        $source->table($table)
            ->orderBy('id')
            ->chunkById(500, function ($records) use ($target, $table, &$matches): void {
                $sourceRecords = $records->keyBy('id');
                $targetRecords = $target->table($table)
                    ->whereIn('id', $sourceRecords->keys())
                    ->orderBy('id')
                    ->get()
                    ->keyBy('id');

                foreach ($sourceRecords as $id => $sourceRecord) {
                    $targetRecord = $targetRecords->get($id);
                    if ($targetRecord === null || $this->normalizeRecord((array) $sourceRecord) !== $this->normalizeRecord((array) $targetRecord)) {
                        $matches = false;

                        return;
                    }
                }
            });

        return $matches;
    }

    private function normalizeRecord(array $record): array
    {
        ksort($record);

        return array_map(
            static fn (mixed $value): mixed => $value === null ? null : (string) $value,
            $record
        );
    }
}
