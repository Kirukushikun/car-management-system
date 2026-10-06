<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use ZipArchive;

/**
 * Nightly backup (routes/console.php): one zip in storage/app/backups with the database
 * (SQLite file copy or mysqldump) and every private upload, keeping the last N days.
 * Copy the backups folder off the server as part of the server's own backup routine.
 */
#[Signature('app:backup {--keep=14 : Days of backups to keep}')]
#[Description('Back up the database and uploaded files into storage/app/backups')]
class BackupApplication extends Command
{
    public function handle(): int
    {
        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $archivePath = $directory.'/car-backup-'.now()->format('Ymd-His').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Could not create {$archivePath}.");

            return self::FAILURE;
        }

        $temporaryDump = $this->addDatabase($zip);
        $fileCount = $this->addUploads($zip, storage_path('app/private'));
        $zip->close();

        if ($temporaryDump) {
            File::delete($temporaryDump);
        }

        $pruned = $this->prune($directory, (int) $this->option('keep'));

        $this->info("Backup written to {$archivePath} ({$fileCount} uploaded files). Removed {$pruned} old ".str('backup')->plural($pruned).'.');

        return self::SUCCESS;
    }

    /**
     * Put a copy of the database in the archive. Returns a temporary file to delete afterwards.
     */
    private function addDatabase(ZipArchive $zip): ?string
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if ($config['driver'] === 'sqlite') {
            if ($config['database'] === ':memory:' || ! is_file($config['database'])) {
                $this->warn('The database is in memory — no database file to back up.');

                return null;
            }

            $zip->addFile($config['database'], 'database/database.sqlite');

            return null;
        }

        if (in_array($config['driver'], ['mysql', 'mariadb'], true)) {
            $dump = storage_path('app/backups/database-'.now()->format('Ymd-His').'.sql');
            $result = Process::env(['MYSQL_PWD' => (string) $config['password']])->run([
                env('BACKUP_MYSQLDUMP', 'mysqldump'),
                '--single-transaction', '--routines',
                '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'],
                '--result-file='.$dump,
                $config['database'],
            ]);

            if ($result->failed()) {
                throw new RuntimeException('mysqldump failed: '.$result->errorOutput());
            }

            $zip->addFile($dump, 'database/database.sql');

            return $dump;
        }

        throw new RuntimeException("Backups are not set up for the {$config['driver']} driver.");
    }

    private function addUploads(ZipArchive $zip, string $root): int
    {
        if (! is_dir($root)) {
            return 0;
        }

        $count = 0;

        foreach (File::allFiles($root) as $file) {
            $zip->addFile($file->getPathname(), 'uploads/'.str_replace('\\', '/', $file->getRelativePathname()));
            $count++;
        }

        return $count;
    }

    private function prune(string $directory, int $keepDays): int
    {
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;

        foreach (File::glob($directory.'/car-backup-*.zip') as $backup) {
            if (File::lastModified($backup) < $cutoff) {
                File::delete($backup);
                $removed++;
            }
        }

        return $removed;
    }
}
