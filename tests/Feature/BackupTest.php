<?php

use App\Support\BackupManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Phase 36 (Backups) — a backup only counts once it has been restored. These
| tests take a real mysqldump, restore it into a scratch database, reject a
| tampered archive, and rotate old archives.
|
| mysqldump runs on its own connection and cannot see rows inside the test's
| RefreshDatabase transaction (applied globally in Pest.php), so the probe row
| is written and counted through a second, autocommitting connection and
| removed again afterwards.
*/

beforeEach(function () {
    if (! Schema::hasTable('locations')) {
        $this->artisan('migrate');
    }

    config(['database.connections.backup_probe' => config('database.connections.'.config('database.default'))]);
    $this->probe = DB::connection('backup_probe');

    $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ck-backup-test-'.uniqid();
    config(['backup.path' => $this->dir, 'backup.keep' => 2, 'backup.folders' => [], 'backup.password' => null, 'backup.offsite_disk' => null]);
    $this->locationId = $this->probe->table('locations')->insertGetId(['name' => 'Backup Test Bay', 'slug' => 'backup-test-bay-'.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
});

afterEach(function () {
    $this->probe->table('locations')->where('id', $this->locationId)->delete();
    array_map('unlink', glob($this->dir.DIRECTORY_SEPARATOR.'*') ?: []);
    @rmdir($this->dir);
});

it('backs up and proves the archive restores, row for row', function () {
    $backups = app(BackupManager::class);
    $archive = $backups->create();

    $zip = new ZipArchive;
    $zip->open($archive);
    $manifest = json_decode($zip->getFromName('manifest.json'), true);
    $zip->close();

    expect($manifest['tables'])->toContain('locations', 'bookings', 'journal_entries')
        ->and($manifest['env_included'])->toBeFalse();                       // no password → no .env copy

    $counts = $backups->verify($archive);

    expect($counts['locations'])->toBe($this->probe->table('locations')->count())
        ->and($counts['locations'])->toBeGreaterThanOrEqual(1)
        ->and(collect(DB::select('SHOW DATABASES'))->pluck('Database'))->not->toContain(config('database.connections.mysql.database').'_restore_check');
});

it('refuses a tampered archive', function () {
    $backups = app(BackupManager::class);
    $archive = $backups->create();

    $zip = new ZipArchive;
    $zip->open($archive);
    $zip->addFromString('database.sql', "DROP TABLE users;\n");
    $zip->close();

    expect(fn () => $backups->verify($archive))->toThrow(RuntimeException::class, 'checksum mismatch');
});

it('keeps only the newest archives', function () {
    $backups = app(BackupManager::class);

    foreach (range(1, 3) as $i) {
        $this->travel(1)->seconds();
        $backups->create();
    }

    expect($backups->archives())->toHaveCount(2);
});
