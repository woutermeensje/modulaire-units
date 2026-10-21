<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SiteContentSnapshot
{
    /** @var list<string> */
    public const TABLES = [
        'media', 'authors', 'categories', 'unit_terms', 'pages', 'posts', 'units', 'products',
        'vacancies', 'team_members', 'menu_items', 'redirects', 'settings', 'unit_unit_term',
    ];

    /** @var array<string, array<string, string>> */
    private const REFERENCES = [
        'posts' => ['author_id' => 'authors', 'category_id' => 'categories'],
        'unit_unit_term' => ['unit_id' => 'units', 'unit_term_id' => 'unit_terms'],
    ];

    /** @var array<string, list<string>> */
    private const BOOLEANS = [
        'pages' => ['show_title', 'noindex'],
        'posts' => ['noindex'],
        'units' => ['noindex'],
        'products' => ['noindex'],
        'vacancies' => ['is_filled', 'noindex'],
        'team_members' => ['is_visible'],
    ];

    /**
     * @return array{version: int, tables: array<string, list<array<string, mixed>>>}
     */
    public function export(): array
    {
        $tables = [];

        foreach (self::TABLES as $table) {
            $query = DB::table($table);

            if ($table === 'settings') {
                $query->whereIn('key', $this->settingKeys());
            }

            $tables[$table] = $query->orderBy($table === 'unit_unit_term' ? 'unit_id' : 'id')->get()
                ->map(function (object $record) use ($table): array {
                    $row = (array) $record;

                    foreach (self::BOOLEANS[$table] ?? [] as $column) {
                        $row[$column] = (bool) $row[$column];
                    }

                    return $row;
                })->all();
        }

        return ['version' => 1, 'tables' => $tables];
    }

    public function hasContent(): bool
    {
        foreach (self::TABLES as $table) {
            if ($table !== 'settings' && DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Import into an empty website, using generated IDs so SQLite, MySQL and PostgreSQL sequences remain valid.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, int>
     */
    public function import(array $snapshot): array
    {
        $this->validate($snapshot);

        DB::transaction(function () use ($snapshot): void {
            if ($this->hasContent()) {
                throw new RuntimeException('De database bevat al website-inhoud. De import heeft niets overschreven.');
            }

            $identifiers = [];

            foreach (self::TABLES as $table) {
                foreach ($snapshot['tables'][$table] as $row) {
                    $originalId = $row['id'] ?? null;
                    unset($row['id']);

                    if (in_array($table, ['pages', 'menu_items'], true)) {
                        $row['parent_id'] = null;
                    }

                    foreach (self::REFERENCES[$table] ?? [] as $column => $referencedTable) {
                        if (($row[$column] ?? null) !== null) {
                            $row[$column] = $this->reference($identifiers, $referencedTable, $row[$column]);
                        }
                    }

                    if ($table === 'settings') {
                        DB::table($table)->updateOrInsert(['key' => $row['key']], $row);
                    } elseif ($table === 'unit_unit_term') {
                        DB::table($table)->insert($row);
                    } else {
                        $identifiers[$table][$originalId] = DB::table($table)->insertGetId($row);
                    }
                }
            }

            foreach (['pages', 'menu_items'] as $table) {
                foreach ($snapshot['tables'][$table] as $row) {
                    if (($row['parent_id'] ?? null) !== null) {
                        DB::table($table)->where('id', $identifiers[$table][$row['id']])->update([
                            'parent_id' => $this->reference($identifiers, $table, $row['parent_id']),
                        ]);
                    }
                }
            }
        });

        Cache::forget('settings');

        return array_map(count(...), $snapshot['tables']);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function validate(array $snapshot): void
    {
        $tables = $snapshot['tables'] ?? null;

        if (($snapshot['version'] ?? null) !== 1 || ! is_array($tables)
            || array_diff(array_keys($tables), self::TABLES) !== []
            || array_diff(self::TABLES, array_keys($tables)) !== []) {
            throw new RuntimeException('Ongeldige inhoudsexport: de versie of de inhoudstabellen kloppen niet.');
        }

        foreach (self::TABLES as $table) {
            if (! is_array($tables[$table]) || ! array_is_list($tables[$table])) {
                throw new RuntimeException("Ongeldige inhoudsexport voor {$table}.");
            }

            $columns = Schema::getColumnListing($table);
            $identifiers = [];

            foreach ($tables[$table] as $row) {
                if (! is_array($row) || $row === [] || array_diff(array_keys($row), $columns) !== []
                    || count(array_filter($row, fn (mixed $value): bool => $value !== null && ! is_scalar($value))) > 0) {
                    throw new RuntimeException("Ongeldige velden in {$table}.");
                }

                if ($table !== 'unit_unit_term') {
                    $id = $row['id'] ?? null;

                    if (! is_int($id) || $id < 1 || isset($identifiers[$id])) {
                        throw new RuntimeException("Ongeldig of dubbel ID in {$table}.");
                    }

                    $identifiers[$id] = true;
                }

                if ($table === 'settings' && ! in_array($row['key'] ?? null, $this->settingKeys(), true)) {
                    throw new RuntimeException('De export bevat een onbekende website-instelling.');
                }
            }
        }
    }

    /**
     * @param  array<string, array<int, int>>  $identifiers
     */
    private function reference(array $identifiers, string $table, int $id): int
    {
        return $identifiers[$table][$id] ?? throw new RuntimeException("Ontbrekende verwijzing naar {$table} #{$id}.");
    }

    /**
     * @return list<string>
     */
    private function settingKeys(): array
    {
        return array_values(array_diff(array_keys(Setting::FIELDS), ['notification_email']));
    }
}
