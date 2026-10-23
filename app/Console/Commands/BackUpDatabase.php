<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;

#[Signature('college:backup {--connection= : Database connection to snapshot (defaults to the default connection)} {--keep= : Override how many recent backups to retain on the disk}')]
#[Description('Snapshot the SQLite database onto the configured backup disk (S3-ready) and prune old backups')]
class BackUpDatabase extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = DB::connection($this->connectionName());

        if ($connection->getDriverName() !== 'sqlite') {
            $this->error('The college:backup command only supports SQLite connections.');

            return self::FAILURE;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk((string) config('college.backup.disk', 'backups'));

        // VACUUM INTO produces a consistent snapshot, even while the app writes.
        // The staging path must not exist yet, so it is built rather than created.
        $staging = sys_get_temp_dir().DIRECTORY_SEPARATOR.'college-backup-'.bin2hex(random_bytes(8)).'.sqlite';

        try {
            $pdo = $this->vacuumPdo($connection);

            if ($pdo->exec("VACUUM INTO '".str_replace("'", "''", $staging)."'") === false) {
                $this->error('Could not snapshot the database with VACUUM INTO.');

                return self::FAILURE;
            }

            if (filesize($staging) === 0) {
                $this->error('The database snapshot came back empty.');

                return self::FAILURE;
            }

            $stored = 'college-backup-'.now()->format('Y-m-d-His').'.sqlite';
            $stream = fopen($staging, 'rb');

            if ($stream === false || $disk->put($stored, $stream) === false) {
                $this->error("Could not write [{$stored}] to the backup disk.");

                return self::FAILURE;
            }

            fclose($stream);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            if (file_exists($staging)) {
                unlink($staging);
            }
        }

        $pruned = $this->pruneOldBackups($disk);

        $this->info("Backup written: {$stored}".($pruned > 0 ? " ({$pruned} old backup(s) pruned)" : ''));

        return self::SUCCESS;
    }

    /**
     * SQLite refuses to VACUUM from within a transaction, so when one is open
     * the snapshot is taken over a committed view through a dedicated handle.
     */
    private function vacuumPdo(Connection $connection): PDO
    {
        if (DB::connection($this->connectionName())->transactionLevel() === 0) {
            return $connection->getPdo();
        }

        $path = $connection->getDatabaseName();

        if ($path === ':memory:' || $path === '') {
            throw new RuntimeException('Cannot snapshot an in-memory database from within a transaction.');
        }

        $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA busy_timeout = 10000');

        return $pdo;
    }

    /**
     * The database connection to snapshot, defaulting to the default connection.
     */
    private function connectionName(): ?string
    {
        $connection = $this->option('connection');

        if (is_array($connection)) {
            return null;
        }

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    /**
     * Retain only the most recent backups on the disk, oldest pruned first.
     */
    private function pruneOldBackups(FilesystemAdapter $disk): int
    {
        $keepOption = $this->option('keep');
        $keep = $keepOption !== null ? (int) $keepOption : (int) config('college.backup.keep', 14);

        if ($keep < 1) {
            return 0;
        }

        $backups = collect($disk->files())
            ->filter(fn (string $file): bool => str_starts_with($file, 'college-backup-') && str_ends_with($file, '.sqlite'))
            ->sort()
            ->values();

        $obsoleteCount = max(0, $backups->count() - $keep);

        return $backups
            ->take($obsoleteCount)
            ->each(fn (string $file): bool => $disk->delete($file))
            ->count();
    }
}
