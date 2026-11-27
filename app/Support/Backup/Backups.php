<?php

namespace App\Support\Backup;

use App\Licensing\Features;
use App\Models\Setting;
use App\Support\Turbo\PageCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Modul „Datensicherung“: komplette Sicherung als ZIP (Datenbank als JSON, Uploads, geschützte Dateien, .env).
 * Unabhängig vom Datenbanksystem, d. h. auch Umzug von SQLite zu MySQL und zurück.
 */
class Backups
{
    /** Diese Tabellen werden nicht gesichert bzw. beim Zurückspielen nicht überschrieben */
    private const SKIP_TABLES = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    public const INTERVALS = ['off' => 'Aus', 'daily' => 'Täglich', 'weekly' => 'Wöchentlich'];

    public static function on(): bool
    {
        return Features::on('datensicherung');
    }

    public static function dir(): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0750, true);
            File::put($dir.'/.htaccess', "Require all denied\nDeny from all\n");
        }

        return $dir;
    }

    /** @return Collection<int, array{name: string, size: int, date: Carbon, kind: string}> */
    public static function list(): Collection
    {
        return collect(glob(self::dir().'/nova-sicherung-*.zip') ?: [])
            ->map(fn ($f) => ['name' => basename($f), 'size' => filesize($f), 'date' => Carbon::createFromTimestamp(filemtime($f)),
                'kind' => str_contains(basename($f), '-auto-') ? 'Automatisch' : (str_contains(basename($f), '-vorher-') ? 'Vor Wiederherstellung' : 'Von Hand')])
            ->sortByDesc(fn ($b) => $b['date']->getTimestamp())->values();
    }

    public static function path(string $name): ?string
    {
        if (! preg_match('/^nova-sicherung-[a-z0-9-]+\.zip$/', $name)) {
            return null;
        }
        $path = self::dir().'/'.$name;

        return is_file($path) ? $path : null;
    }

    /** Erstellt eine Sicherung und gibt den Dateinamen zurück. kind: manuell, auto, vorher */
    public function create(string $kind = 'manuell'): string
    {
        @set_time_limit(600);
        $name = 'nova-sicherung-'.now()->format('Y-m-d-His').'-'.$kind.'-'.Str::lower(Str::random(6)).'.zip';
        $path = self::dir().'/'.$name;
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Die Sicherungsdatei konnte nicht angelegt werden. Ist im Ordner storage genug Platz?');
        }

        $tables = [];
        foreach (self::tables() as $table) {
            $tmp = tempnam(sys_get_temp_dir(), 'nova');
            $fh = fopen($tmp, 'w');
            $count = 0;
            $query = DB::table($table);
            $order = Schema::hasColumn($table, 'id') ? 'id' : (Schema::getColumnListing($table)[0] ?? null);
            foreach (($order ? $query->orderBy($order) : $query)->lazy(500) as $row) {
                fwrite($fh, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
                $count++;
            }
            fclose($fh);
            $zip->addFile($tmp, 'database/'.$table.'.jsonl');
            $temps[] = $tmp;
            $tables[$table] = $count;
        }

        $files = 0;
        foreach (self::folders() as $prefix => $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (File::allFiles($dir, true) as $file) {
                $rel = str_replace('\\', '/', $file->getRelativePathname());
                if ($prefix === 'storage' && (str_starts_with($rel, 'backups/') || str_starts_with($rel, 'framework/'))) {
                    continue;
                }
                $zip->addFile($file->getPathname(), 'files/'.$prefix.'/'.$rel);
                $files++;
            }
        }
        if (is_file(base_path('.env'))) {
            $zip->addFile(base_path('.env'), 'env/.env');
        }
        $zip->addFromString('sicherung.json', json_encode([
            'format' => 1, 'version' => config('nova.version'), 'created_at' => now()->toIso8601String(), 'url' => config('app.url'),
            'database' => DB::connection()->getDriverName(), 'tables' => $tables, 'files' => $files,
        ], JSON_PRETTY_PRINT));
        $zip->addFromString('LIESMICH.txt', "Sicherung von Nova CMS\n\nZurückspielen: in der Verwaltung unter „Datensicherung“ die Datei hochladen.\n"
            ."Die Datei env/.env enthält Zugangsdaten (z. B. Datenbank, E-Mail). Bewahren Sie die Sicherung sicher auf.\n");
        $zip->close();
        foreach ($temps ?? [] as $t) {
            @unlink($t);
        }
        Setting::put('backup_last_at', now()->toIso8601String());

        return $name;
    }

    /** Spielt eine Sicherung zurück. Vorher wird der aktuelle Stand gesichert. */
    public function restore(string $path): array
    {
        @set_time_limit(600);
        $zip = new ZipArchive;
        if ($zip->open($path) !== true || ($meta = json_decode((string) $zip->getFromName('sicherung.json'), true)) === null || ($meta['format'] ?? null) !== 1) {
            throw new RuntimeException('Diese Datei ist keine Sicherung von Nova CMS.');
        }
        if (version_compare((string) ($meta['version'] ?? '0'), (string) config('nova.version'), '>')) {
            throw new RuntimeException('Die Sicherung stammt aus einer neueren Version ('.$meta['version'].'). Bitte zuerst Nova CMS aktualisieren.');
        }
        $before = $this->create('vorher');
        // Lizenz und Kennung dieser Installation behalten
        $keep = DB::table('settings')->where(fn ($q) => $q->where('key', 'like', 'license_%')->orWhere('key', 'like', 'backup_%')
            ->orWhereIn('key', ['install_id', 'installed_at']))->get()->map(fn ($r) => (array) $r)->all();

        $current = self::tables();
        $restored = 0;
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($zip, $meta, $current, &$restored) {
                foreach (array_keys($meta['tables'] ?? []) as $table) {
                    if (! in_array($table, $current, true)) {
                        continue;
                    }
                    $columns = array_flip(Schema::getColumnListing($table));
                    DB::table($table)->delete();
                    $stream = $zip->getStream('database/'.$table.'.jsonl');
                    if (! $stream) {
                        continue;
                    }
                    $batch = [];
                    while (($line = fgets($stream)) !== false) {
                        if (($row = json_decode($line, true)) === null) {
                            continue;
                        }
                        $batch[] = array_intersect_key($row, $columns);
                        if (count($batch) >= 200) {
                            DB::table($table)->insert($batch);
                            $restored += count($batch);
                            $batch = [];
                        }
                    }
                    fclose($stream);
                    if ($batch) {
                        DB::table($table)->insert($batch);
                        $restored += count($batch);
                    }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        foreach ($keep as $row) {
            DB::table('settings')->updateOrInsert(['key' => $row['key']], $row);
        }
        Setting::flushCache();
        $this->resetSequences(array_keys($meta['tables'] ?? []));

        $files = 0;
        $targets = self::folders();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (! preg_match('#^files/(uploads|storage)/(.+)$#', $entry, $m) || str_contains($m[2], '..') || str_ends_with($entry, '/')) {
                continue;
            }
            $target = $targets[$m[1]].'/'.$m[2];
            File::ensureDirectoryExists(dirname($target));
            if (($in = $zip->getStream($entry)) && ($out = fopen($target, 'w'))) {
                stream_copy_to_stream($in, $out);
                fclose($out);
                fclose($in);
                $files++;
            }
        }
        $zip->close();
        PageCache::flush();

        return ['rows' => $restored, 'files' => $files, 'before' => $before, 'from' => $meta['created_at'] ?? null];
    }

    /** Automatische Sicherung, wenn fällig. Läuft über die Aufgabenplanung ohne Cronjob. */
    public function runDue(): ?string
    {
        $interval = Setting::get('backup_auto', 'off');
        if (! self::on() || ! isset(self::INTERVALS[$interval]) || $interval === 'off') {
            return null;
        }
        $last = Setting::get('backup_auto_at');
        $due = ! $last || Carbon::parse($last)->lte(now()->subHours($interval === 'daily' ? 23 : 167));
        if (! $due) {
            return null;
        }
        Setting::put('backup_auto_at', now()->toIso8601String());
        $name = $this->create('auto');
        $this->prune();

        return $name;
    }

    /** Behält die neuesten automatischen Sicherungen */
    public function prune(): int
    {
        $keep = max(1, min(60, (int) Setting::get('backup_keep', '7')));
        $old = self::list()->where('kind', 'Automatisch')->slice($keep);
        foreach ($old as $b) {
            @unlink(self::dir().'/'.$b['name']);
        }

        return $old->count();
    }

    /** @return list<string> */
    private static function tables(): array
    {
        return array_values(array_filter(array_map(fn ($t) => is_array($t) ? $t['name'] : $t, Schema::getTables()),
            fn ($t) => ! in_array($t, self::SKIP_TABLES, true) && ! str_starts_with($t, 'sqlite_')));
    }

    /** @return array<string, string> */
    private static function folders(): array
    {
        return ['uploads' => public_path('uploads'), 'storage' => storage_path('app')];
    }

    /** PostgreSQL/MySQL zählen IDs selbst weiter; bei PostgreSQL müssen Sequenzen angepasst werden */
    private function resetSequences(array $tables): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        foreach ($tables as $t) {
            if (Schema::hasColumn($t, 'id')) {
                DB::statement("SELECT setval(pg_get_serial_sequence('{$t}', 'id'), COALESCE(MAX(id), 1)) FROM \"{$t}\"");
            }
        }
    }
}
