<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Backups (Phase 36): create, verify (real restore into a scratch database),
 * restore, rotate. One zip per run:
 *
 *   database.sql     mysqldump --single-transaction (consistent snapshot)
 *   files/…          config('backup.folders') (uploads, private attachments)
 *   config/.env      AES-256 encrypted with BACKUP_PASSWORD, or omitted
 *   manifest.json    {app, created_at, database, tables, sha256, files, env_included}
 *
 * DB credentials reach mysqldump/mysql through a temporary option file, never
 * the command line (which other users of a shared host could read).
 */
class BackupManager
{
    public function create(): string
    {
        $dir = config('backup.path');
        is_dir($dir) || mkdir($dir, 0750, true);

        $name = 'ck-'.now()->format('Ymd-His').'.zip';
        $archive = $dir.DIRECTORY_SEPARATOR.$name;
        $dump = tempnam(sys_get_temp_dir(), 'ckdump');

        try {
            $this->runClient(config('backup.mysqldump'), ['--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces', '--hex-blob', $this->database()], stdoutFile: $dump);

            $zip = new ZipArchive;
            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Cannot create {$archive}.");
            }

            $zip->addFile($dump, 'database.sql');
            $files = $this->addFolders($zip);
            $envIncluded = $this->addEncryptedEnv($zip);

            $zip->addFromString('manifest.json', json_encode([
                'app' => config('app.name'),
                'created_at' => now()->toIso8601String(),
                'database' => $this->database(),
                'tables' => $this->tables(),
                'sha256' => ['database.sql' => hash_file('sha256', $dump)],
                'files' => $files,
                'env_included' => $envIncluded,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $zip->close();
        } finally {
            @unlink($dump);
        }

        if ($disk = config('backup.offsite_disk')) {
            Storage::disk($disk)->putFileAs('backups', $archive, $name);
        }

        $this->rotate();

        return $archive;
    }

    /**
     * Prove an archive restores: checksum, import into the scratch database,
     * every table present. Returns row counts per table from the restored copy.
     *
     * @return array<string, int>
     */
    public function verify(?string $archive = null): array
    {
        $archive ??= $this->latest() ?? throw new RuntimeException('No backup found in '.config('backup.path').'.');
        $scratch = config('backup.verify_database') ?: $this->database().'_restore_check';
        [$manifest, $sql] = $this->open($archive);

        DB::statement("CREATE DATABASE IF NOT EXISTS `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        try {
            $this->import($sql, $scratch);

            $restored = collect(DB::select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND table_type = ?', [$scratch, 'BASE TABLE']))->pluck('t');
            $missing = array_values(array_diff($manifest['tables'], $restored->all()));

            if ($missing !== []) {
                throw new RuntimeException('Restore is missing tables: '.implode(', ', $missing));
            }

            return $restored->mapWithKeys(fn ($t) => [$t => (int) DB::selectOne("SELECT COUNT(*) AS c FROM `{$scratch}`.`{$t}`")->c])->all();
        } finally {
            if (! config('backup.verify_database')) {
                DB::statement("DROP DATABASE IF EXISTS `{$scratch}`");
            }
            @unlink($sql);
        }
    }

    /** Restore the database (and optionally files) from an archive into the live app. */
    public function restore(string $archive, bool $withFiles = true): array
    {
        [$manifest, $sql] = $this->open($archive);

        try {
            $this->import($sql, $this->database());
        } finally {
            @unlink($sql);
        }

        if ($withFiles) {
            $zip = new ZipArchive;
            $zip->open($archive);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if (str_starts_with($entry, 'files/') && ! str_ends_with($entry, '/') && ! str_contains($entry, '..')) {
                    $target = base_path(substr($entry, strlen('files/')));
                    is_dir(dirname($target)) || mkdir(dirname($target), 0750, true);
                    file_put_contents($target, $zip->getFromIndex($i));
                }
            }
            $zip->close();
        }

        return $manifest;
    }

    /** Keep the newest `backup.keep` archives. @return list<string> deleted */
    public function rotate(): array
    {
        $all = $this->archives();
        $old = array_slice($all, max(0, (int) config('backup.keep', 14)));
        array_map('unlink', $old);

        return $old;
    }

    public function latest(): ?string
    {
        return $this->archives()[0] ?? null;
    }

    /** @return list<string> newest first */
    public function archives(): array
    {
        $files = glob(config('backup.path').DIRECTORY_SEPARATOR.'ck-*.zip') ?: [];
        rsort($files);

        return $files;
    }

    /** @return array{0: array, 1: string} manifest + path of the extracted, checksum-verified dump */
    private function open(string $archive): array
    {
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException("Cannot open {$archive}.");
        }

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $sql = tempnam(sys_get_temp_dir(), 'cksql');
        file_put_contents($sql, $zip->getFromName('database.sql'));
        $zip->close();

        if (! $manifest || ! hash_equals($manifest['sha256']['database.sql'] ?? '', hash_file('sha256', $sql))) {
            @unlink($sql);
            throw new RuntimeException('Backup checksum mismatch: the archive is corrupt or was altered.');
        }

        return [$manifest, $sql];
    }

    private function import(string $sqlFile, string $database): void
    {
        $this->runClient(config('backup.mysql'), [$database], stdinFile: $sqlFile);
    }

    /** Run mysqldump / mysql with credentials in a temporary 0600 option file. */
    private function runClient(string $binary, array $args, ?string $stdoutFile = null, ?string $stdinFile = null): void
    {
        $c = config('database.connections.'.config('database.default'));
        $options = tempnam(sys_get_temp_dir(), 'ckmy');
        file_put_contents($options, "[client]\nuser=\"{$c['username']}\"\npassword=\"{$c['password']}\"\nhost=\"{$c['host']}\"\nport={$c['port']}\n");
        @chmod($options, 0600);

        try {
            $process = new Process([$binary, '--defaults-extra-file='.$options, ...$args]);
            $process->setTimeout(3600);

            if ($stdinFile) {
                $process->setInput(fopen($stdinFile, 'rb'));
            }

            $out = $stdoutFile ? fopen($stdoutFile, 'wb') : null;
            $process->run(function ($type, $buffer) use ($out) {
                if ($type === Process::OUT && $out) {
                    fwrite($out, $buffer);
                }
            });
            $out && fclose($out);

            if (! $process->isSuccessful()) {
                throw new RuntimeException(basename($binary).' failed: '.trim($process->getErrorOutput()));
            }
        } finally {
            @unlink($options);
        }
    }

    private function addFolders(ZipArchive $zip): int
    {
        $count = 0;
        foreach (config('backup.folders') as $folder) {
            $root = base_path($folder);
            if (! is_dir($root)) {
                continue;
            }
            $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($items as $file) {
                if ($file->isFile() && ! str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR)) {
                    $relative = $folder.'/'.str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
                    $zip->addFile($file->getPathname(), 'files/'.$relative);
                    $count++;
                }
            }
        }

        return $count;
    }

    private function addEncryptedEnv(ZipArchive $zip): bool
    {
        $password = config('backup.password');
        if (! $password || ! is_file(base_path('.env')) || ! method_exists($zip, 'setEncryptionName')) {
            return false;
        }

        $zip->addFile(base_path('.env'), 'config/.env');

        return $zip->setEncryptionName('config/.env', ZipArchive::EM_AES_256, $password);
    }

    private function database(): string
    {
        return (string) config('database.connections.'.config('database.default').'.database');
    }

    /** @return list<string> */
    private function tables(): array
    {
        return collect(DB::select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND table_type = ?', [$this->database(), 'BASE TABLE']))
            ->pluck('t')->sort()->values()->all();
    }
}
