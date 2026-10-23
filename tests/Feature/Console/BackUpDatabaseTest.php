<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Each test snapshots a dedicated temp-file connection of its own. Touching the
// default test connection (:memory:, kept in a transaction by the runner) would
// leak state across the suite, so the command's --connection option is used.

beforeEach(function () {
    config()->set('college.backup.disk', 'backups');
    Storage::fake('backups');
});

/**
 * Point a dedicated SQLite connection at a fresh temp-file database.
 */
function useBackupSourceConnection(): void
{
    // SQLite connectors require the file to exist; an empty file is a valid db.
    $path = sys_get_temp_dir().'/college-backup-test-'.bin2hex(random_bytes(8)).'.sqlite';
    touch($path);

    config()->set('database.connections.backup_test', [
        'driver' => 'sqlite',
        'database' => $path,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    DB::purge('backup_test');
}

afterEach(function () {
    $path = (string) config('database.connections.backup_test.database');

    if ($path !== '' && is_file($path)) {
        unlink($path);
    }
});

function fakeBackupsDiskFiles(): array
{
    return collect(Storage::disk('backups')->files())
        ->filter(fn (string $file): bool => str_starts_with($file, 'college-backup-') && str_ends_with($file, '.sqlite'))
        ->sort()
        ->values()
        ->all();
}

test('the backup command snapshots the database onto the backup disk', function () {
    useBackupSourceConnection();

    DB::connection('backup_test')->statement(
        'CREATE TABLE users (id integer primary key autoincrement not null, name varchar not null, email varchar not null)'
    );
    DB::connection('backup_test')->table('users')->insert([
        'name' => 'Backup Canary',
        'email' => 'canary@college.test',
    ]);

    $this->artisan('college:backup', ['--connection' => 'backup_test'])->assertSuccessful();

    $files = fakeBackupsDiskFiles();

    expect($files)->toHaveCount(1)
        ->and($files[0])->toStartWith('college-backup-')
        ->toEndWith('.sqlite');

    // The stored artifact must carry the committed data of the source database.
    $snapshot = new PDO('sqlite:'.Storage::disk('backups')->path($files[0]), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $rows = $snapshot->query("SELECT count(*) FROM users WHERE email = 'canary@college.test'");

    expect($rows === false ? [] : $rows->fetchAll(PDO::FETCH_COLUMN))->toBe([1]);
});

test('the backup command prunes older backups beyond the retention count', function () {
    useBackupSourceConnection();

    foreach (['college-backup-2026-10-20-020000.sqlite', 'college-backup-2026-10-21-020000.sqlite', 'college-backup-2026-10-22-020000.sqlite'] as $file) {
        Storage::disk('backups')->put($file, str_repeat('x', 32));
    }

    // Keep the two newest artifacts including the one created now.
    config()->set('college.backup.keep', 2);

    $this->artisan('college:backup', ['--connection' => 'backup_test'])->assertSuccessful();

    $files = array_map(fn (string $file): string => basename($file), fakeBackupsDiskFiles());

    expect($files)->toHaveCount(2)
        ->and($files)->toContain('college-backup-2026-10-22-020000.sqlite')
        ->and(end($files))->toStartWith('college-backup-'.now()->format('Y-m-d'))
        ->and(Storage::disk('backups')->exists('college-backup-2026-10-20-020000.sqlite'))->toBeFalse();
});

test('the backup command respects an explicit keep option over the configured value', function () {
    useBackupSourceConnection();

    foreach (['college-backup-2026-10-20-020000.sqlite', 'college-backup-2026-10-21-020000.sqlite'] as $file) {
        Storage::disk('backups')->put($file, str_repeat('x', 32));
    }

    $this->artisan('college:backup', ['--connection' => 'backup_test', '--keep' => 1])->assertSuccessful();

    $files = fakeBackupsDiskFiles();

    expect($files)->toHaveCount(1)
        ->and(basename((string) ($files[0] ?? '')))->toStartWith('college-backup-'.now()->format('Y-m-d'));
});
